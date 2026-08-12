<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedNewsletter\Campaigns\CampaignGuard;
use Dashed\DashedNewsletter\Models\NewsletterCampaign;
use Illuminate\Contracts\Queue\ShouldQueue;
use Dashed\DashedNewsletter\Campaigns\CampaignRecipients;
use Dashed\DashedNewsletter\Models\NewsletterCampaignRecipient;

/**
 * Zet een campagne in gang: controleren, ontvangers vastleggen, porties
 * inplannen. Dit is de enige weg naar binnen, of je nu op verzenden drukt of
 * een geplande campagne aan de beurt is.
 */
class StartCampaignJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public int $campaignId)
    {
    }

    public function handle(): void
    {
        $campaign = NewsletterCampaign::find($this->campaignId);

        if (! $campaign) {
            return;
        }

        CampaignGuard::assertSendable($campaign);

        // Voorwaardelijke update in plaats van een gewone save: twee jobs voor
        // dezelfde campagne (een dubbele klik op de verzendknop, of de knop die
        // de planner net voor was) lezen via de guard hierboven allebei
        // dezelfde 'concept'/'scheduled'-status. Een if-check op
        // $campaign->status in het geheugen zou ze dan allebei laten
        // doorgaan. Het aantal geraakte rijen van deze UPDATE is de enige
        // betrouwbare uitspraak over wie deze campagne mag starten: van twee
        // gelijktijdige jobs raakt er precies één een rij, de ander nul.
        // Zelfde patroon als CampaignSender::send() per ontvanger gebruikt,
        // hier één keer op campagneniveau.
        //
        // Deze claim hoort in de job en nergens anders, ook niet in de
        // verzendknop: CampaignGuard::problem() leest de status van de
        // campagne, dus wie die status vóór de guard-check zet, sluit
        // zichzelf buiten - de guard zou dan altijd "al aan het verzenden"
        // teruggeven, ook voor de aanroep die de vlag net zelf zette. De
        // guard moet de oude status zien; de claim mag pas daarna.
        $geclaimd = NewsletterCampaign::where('id', $campaign->id)
            ->whereIn('status', [NewsletterCampaign::STATUS_CONCEPT, NewsletterCampaign::STATUS_SCHEDULED])
            ->update([
                'status' => NewsletterCampaign::STATUS_SENDING,
                'started_at' => now(),
            ]);

        if ($geclaimd === 0) {
            // Een andere job heeft deze campagne net al geclaimd; niets te
            // doen hier, en zeker geen tweede keer ontvangers opbouwen.
            return;
        }

        $campaign->refresh();

        CampaignRecipients::build($campaign);

        $chunkSize = (int) config('dashed-newsletter.chunk_size', 200);

        NewsletterCampaignRecipient::where('newsletter_campaign_id', $campaign->id)
            ->where('status', NewsletterCampaignRecipient::STATUS_PENDING)
            ->pluck('id')
            ->chunk($chunkSize)
            ->each(fn ($ids) => SendCampaignChunkJob::dispatch($campaign->id, $ids->all()));

        // Met een synchrone wachtrij (zoals in tests) is elke portie hierboven
        // al meteen afgehandeld tegen de tijd dat we hier komen, en kan de
        // laatste portie de campagne al op 'sent' hebben gezet. Met een echte
        // wachtrij zijn de porties nog niet gestart. Beide gevallen lopen via
        // dezelfde slotcontrole: niets openstaand, dan is de campagne klaar.
        // 'sending' telt hier mee als nog niet afgehandeld, net als in
        // SendCampaignChunkJob: een regel die net geclaimd is maar nog niet
        // verstuurd, mag de campagne niet als voltooid laten gelden.
        $open = NewsletterCampaignRecipient::where('newsletter_campaign_id', $campaign->id)
            ->whereIn('status', [
                NewsletterCampaignRecipient::STATUS_PENDING,
                NewsletterCampaignRecipient::STATUS_SENDING,
            ])
            ->exists();

        if (! $open && $campaign->fresh()->status === NewsletterCampaign::STATUS_SENDING) {
            $campaign->update([
                'status' => NewsletterCampaign::STATUS_SENT,
                'completed_at' => now(),
            ]);
        }
    }
}
