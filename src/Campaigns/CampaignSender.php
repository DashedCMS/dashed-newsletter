<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Campaigns;

use Illuminate\Support\Facades\Mail;
use Dashed\DashedNewsletter\Mail\NewsletterCampaignMail;
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

        $subscriber = $recipient->subscriber;
        $campaign = $recipient->campaign;

        if (! $subscriber || $subscriber->status !== NewsletterSubscriber::STATUS_ACTIVE) {
            self::skip($recipient, NewsletterCampaignRecipient::SKIP_UNSUBSCRIBED);

            return;
        }

        $siteId = (string) ($campaign->site_id ?? '');

        if ($siteId !== '' && NewsletterSuppression::blocks($siteId, $recipient->email)) {
            self::skip($recipient, NewsletterCampaignRecipient::SKIP_SUPPRESSED);

            return;
        }

        try {
            // Deze catch vangt ook een listener die pas ná de aflevering gooit,
            // waardoor een wél afgeleverde mail hier als mislukt geboekt kan
            // worden; niets verstuurt een failed-regel opnieuw, dus dat kost
            // alleen een verkeerd label, geen dubbele mail.
            Mail::to($recipient->email)->send(new NewsletterCampaignMail($campaign, $recipient));
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
        ]);
        $campaign->increment('sent_count');
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
