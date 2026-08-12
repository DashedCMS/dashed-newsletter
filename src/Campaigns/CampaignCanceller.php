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

        // Alles wat nog niet klaar is (pending) of net geclaimd is door een
        // portie die nog loopt (sending) krijgt hier een eigen skip-reden.
        // Een portie die nog in de wachtrij staat en deze regels straks
        // probeert te claimen vindt ze dan niet meer op 'pending' en doet
        // niets: zie de voorwaardelijke claim in CampaignSender::send().
        // Een regel die op dit moment al middenin het versturen zit (een
        // worker die niet is omgevallen maar gewoon nog bezig is) kan zijn
        // eigen update hierna alsnog naar 'sent' overschrijven, en dat is
        // correct: de mail is dan echt de deur uit.
        NewsletterCampaignRecipient::where('newsletter_campaign_id', $campaign->id)
            ->whereIn('status', [
                NewsletterCampaignRecipient::STATUS_PENDING,
                NewsletterCampaignRecipient::STATUS_SENDING,
            ])
            ->update([
                'status' => NewsletterCampaignRecipient::STATUS_SKIPPED,
                'skip_reason' => NewsletterCampaignRecipient::SKIP_CANCELLED,
            ]);
    }
}
