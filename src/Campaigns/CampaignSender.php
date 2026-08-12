<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Campaigns;

use Illuminate\Mail\SentMessage;
use Illuminate\Support\Facades\Mail;
use Dashed\DashedCore\Models\SentEmail;
use Dashed\DashedNewsletter\Mail\NewsletterCampaignMail;
use Dashed\DashedNewsletter\Models\NewsletterCampaign;
use Dashed\DashedNewsletter\Models\NewsletterSubscriber;
use Dashed\DashedNewsletter\Models\NewsletterSuppression;
use Dashed\DashedNewsletter\Models\NewsletterCampaignRecipient;

/**
 * Verstuurt één regel uit de ontvangerstabel.
 *
 * De controles hier zijn bewust een herhaling van wat CampaignRecipients al
 * deed. Tussen het samenstellen van de lijst en het versturen van de laatste
 * portie kan iemand zich afmelden of geblokkeerd raken, en bij duizenden
 * ontvangers is die tijd lang genoeg dat dat gebeurt.
 */
class CampaignSender
{
    public static function send(NewsletterCampaignRecipient $recipient): void
    {
        // Claim de regel in de database, niet als vergelijking op het geladen
        // object. Twee workers die dezelfde regel oppakken lezen allebei
        // 'pending' in het geheugen; een gewone if-check op $recipient->status
        // zou ze dan allebei laten versturen. De voorwaardelijke update
        // hieronder raakt bij maar één van de twee een rij, en dat aantal
        // geraakte rijen is de enige betrouwbare uitspraak over wie de regel
        // mag afhandelen.
        //
        // Valt de worker om ná deze claim maar vóór of tijdens het versturen,
        // dan blijft de regel op 'sending' staan en pakt niemand hem opnieuw
        // op: die ene ontvanger mist dan de mail. Dat is bewust de kant waarop
        // dit moet mislukken. Een gemiste nieuwsbrief is vervelend en met de
        // hand te herstellen; een dubbele is niet terug te draaien.
        $geclaimd = NewsletterCampaignRecipient::where('id', $recipient->id)
            ->where('status', NewsletterCampaignRecipient::STATUS_PENDING)
            ->update(['status' => NewsletterCampaignRecipient::STATUS_SENDING]);

        if ($geclaimd === 0) {
            return;
        }

        $campaign = $recipient->campaign;

        // Vers uit de database, niet de campagne die met de portiejob is
        // meegeladen: die kan intussen afgebroken zijn (CampaignCanceller)
        // terwijl deze portie al in de wachtrij stond. Zonder deze controle
        // zou zo'n portie de campagne alsnog claimen en versturen op basis
        // van een status die niet meer klopt.
        if (! $campaign || NewsletterCampaign::where('id', $campaign->id)
            ->where('status', NewsletterCampaign::STATUS_SENDING)
            ->doesntExist()) {
            self::skip($recipient, NewsletterCampaignRecipient::SKIP_CANCELLED);

            return;
        }

        // Vers opgevraagd en niet $recipient->subscriber: SendCampaignChunkJob
        // laadt die relatie mee bij het begin van de hele portie, en bij
        // tweehonderd mails per portie is dat lang genoeg voor iemand om zich
        // tussentijds af te melden. De blokkadelijst hieronder wordt al wel
        // per ontvanger vers bevraagd; de status van het contact hoort dat
        // net zo goed te zijn. Ontbreekt het contact (verwijderd), dan is de
        // waarde null en dat is net zo min 'active'.
        $subscriberStatus = NewsletterSubscriber::where('id', $recipient->newsletter_subscriber_id)
            ->value('status');

        if ($subscriberStatus !== NewsletterSubscriber::STATUS_ACTIVE) {
            self::skip($recipient, NewsletterCampaignRecipient::SKIP_UNSUBSCRIBED);

            return;
        }

        // effectiveSiteId(), zelfde reden als CampaignRecipients::build():
        // $campaign->site_id is nullable, en zonder deze afleiding zou een
        // campagne zonder eigen site_id hier ook aan het versturen zelf
        // stilzwijgend langs de blokkadelijst glippen.
        $siteId = (string) ($campaign->effectiveSiteId() ?? '');

        if ($siteId !== '' && NewsletterSuppression::blocks($siteId, $recipient->email)) {
            self::skip($recipient, NewsletterCampaignRecipient::SKIP_SUPPRESSED);

            return;
        }

        try {
            // Deze catch vangt ook een listener die pas ná de aflevering gooit,
            // waardoor een wél afgeleverde mail hier als mislukt geboekt kan
            // worden; niets verstuurt een failed-regel opnieuw, dus dat kost
            // alleen een verkeerd label, geen dubbele mail.
            $sentMessage = Mail::to($recipient->email)->send(new NewsletterCampaignMail($campaign, $recipient));
        } catch (\Throwable $e) {
            $recipient->update([
                'status' => NewsletterCampaignRecipient::STATUS_FAILED,
                'skip_reason' => mb_substr($e->getMessage(), 0, 255),
            ]);
            $campaign->increment('failed_count');

            return;
        }

        $recipient->update([
            'status' => NewsletterCampaignRecipient::STATUS_SENT,
            'sent_at' => now(),
            'sent_email_id' => self::sentEmailId($sentMessage),
        ]);
        $campaign->increment('sent_count');
    }

    /**
     * Koppelt deze regel aan zijn rij in dashed__sent_emails, zodat een latere
     * bounce of klacht via SuppressBouncedAddress terug kan naar de campagne
     * (en dus naar de juiste, tekstuele site_id: zie het klassecommentaar
     * daar). LogSentEmail schrijft die rij synchroon op de MessageSent-
     * gebeurtenis die Mail::send() vóór teruggeven al afvuurt, dus hij staat
     * er al als we hier komen. Kan null zijn als het loggen in dashed-core
     * uitstaat of de mail geen SentMessage opleverde (bijvoorbeeld een fake
     * mailer in een test).
     */
    private static function sentEmailId(?SentMessage $sentMessage): ?int
    {
        $messageId = $sentMessage?->getMessageId();

        if (! $messageId) {
            return null;
        }

        return SentEmail::where('message_id', $messageId)->value('id');
    }

    private static function skip(NewsletterCampaignRecipient $recipient, string $reason): void
    {
        $recipient->update([
            'status' => NewsletterCampaignRecipient::STATUS_SKIPPED,
            'skip_reason' => $reason,
        ]);
        $recipient->campaign?->increment('skipped_count');
    }
}
