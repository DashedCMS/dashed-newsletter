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

        $campaign->update([
            'status' => NewsletterCampaign::STATUS_SENDING,
            'started_at' => now(),
        ]);

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
