<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Campaigns;

use Dashed\DashedNewsletter\Models\NewsletterCampaign;
use Dashed\DashedNewsletter\Models\NewsletterCampaignRecipient;

/**
 * Breekt een vastgelopen campagne af: een worker die omviel, of een
 * ontvangersopbouw die de wachtrij-timeout overschreed, laat een campagne op
 * 'sending' staan zonder dat er nog iets aan gebeurt. CampaignGuard weigert
 * dan zowel opnieuw starten als bewerken, en de beheerder kan er verder
 * alleen nog in de database bij.
 *
 * Alleen zinvol vanuit 'sending': op elke andere status is er niets af te
 * breken.
 */
class CampaignCanceller
{
    public static function cancel(NewsletterCampaign $campaign): void
    {
        // Zelfde vorm als StartCampaignJob::handle() en CampaignSender::send():
        // een voorwaardelijke update, niet een if-check op de status in het
        // geheugen. Zo kan dit veilig twee keer bijna gelijktijdig aangeroepen
        // worden zonder de tellingen dubbel te verstoren.
        $geclaimd = NewsletterCampaign::where('id', $campaign->id)
            ->where('status', NewsletterCampaign::STATUS_SENDING)
            ->update(['status' => NewsletterCampaign::STATUS_CANCELLED]);

        if ($geclaimd === 0) {
            return;
        }

        // Pending en sending krijgen bewust een verschillend eindresultaat,
        // ook al zijn het allebei ontvangers die de campagne nog aan het
        // afhandelen was. Een pending regel is zeker nooit verstuurd: hij
        // stond nog in de wachtrij, geen worker had hem aangeraakt. Skipped
        // is dan de juiste, herstelbare uitkomst - CampaignRecipients::
        // build() beoordeelt 'pending' en 'skipped' bij een herstart gewoon
        // opnieuw, en dat is precies wat je wilt: een wachtende ontvanger die
        // nog steeds actief en niet geblokkeerd is, komt terug op pending.
        //
        // Een sending-regel is een heel ander geval: een worker had hem net
        // geclaimd, en we weten niet of de mail al de deur uit was vóór het
        // afbreken of vóór een omgevallen worker. STATUS_INTERRUPTED is
        // daarom een eigen eindtoestand die CampaignRecipients::build() nooit
        // opnieuw beoordeelt (dezelfde regel als voor 'sent' en 'failed': zie
        // het klassecommentaar bij die constante). Bij die onzekerheid kiezen
        // we voor nooit meer versturen, niet voor het risico op een tweede
        // mail. Was 'sending' hier ook op 'skipped' gezet, dan zou een
        // herstart hem via build() weer op 'pending' zetten en opnieuw
        // claimbaar maken voor CampaignSender - en dat is precies de dubbele
        // mail die dit voorkomt.
        NewsletterCampaignRecipient::where('newsletter_campaign_id', $campaign->id)
            ->where('status', NewsletterCampaignRecipient::STATUS_PENDING)
            ->update([
                'status' => NewsletterCampaignRecipient::STATUS_SKIPPED,
                'skip_reason' => NewsletterCampaignRecipient::SKIP_CANCELLED,
            ]);

        // Een regel die op dit moment al middenin het versturen zit (een
        // worker die niet is omgevallen maar gewoon nog bezig is) kan zijn
        // eigen, ongeconditioneerde update in CampaignSender::send() hierna
        // alsnog naar 'sent' overschrijven, en dat is correct: de mail is dan
        // echt de deur uit, en dat mag de uiteindelijke status winnen van wat
        // hier gezet wordt.
        NewsletterCampaignRecipient::where('newsletter_campaign_id', $campaign->id)
            ->where('status', NewsletterCampaignRecipient::STATUS_SENDING)
            ->update([
                'status' => NewsletterCampaignRecipient::STATUS_INTERRUPTED,
                'skip_reason' => NewsletterCampaignRecipient::SKIP_CANCELLED,
            ]);
    }
}
