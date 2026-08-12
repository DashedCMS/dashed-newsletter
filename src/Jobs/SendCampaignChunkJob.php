<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedNewsletter\Campaigns\CampaignSender;
use Dashed\DashedNewsletter\Models\NewsletterCampaign;
use Dashed\DashedNewsletter\Models\NewsletterCampaignRecipient;

class SendCampaignChunkJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** @param array<int, int> $recipientIds */
    public function __construct(public int $campaignId, public array $recipientIds)
    {
    }

    public function handle(): void
    {
        NewsletterCampaignRecipient::with(['subscriber', 'campaign'])
            ->whereIn('id', $this->recipientIds)
            ->get()
            ->each(fn (NewsletterCampaignRecipient $recipient) => CampaignSender::send($recipient));

        $campaign = NewsletterCampaign::find($this->campaignId);

        if (! $campaign) {
            return;
        }

        // Klaar zodra er geen regel meer op pending of sending staat. Elke
        // portie controleert dat zelf, want welke portie de laatste is ligt
        // niet vast. 'sending' telt hier mee als nog niet afgehandeld: dat is
        // een regel die een andere, gelijktijdig lopende portie net geclaimd
        // heeft (zie CampaignSender::send()) maar nog niet klaar is met
        // versturen. Zonder die toevoeging zou deze portie, die toevallig als
        // laatste haar eigen pending-regels leegmaakt, de campagne als
        // voltooid kunnen markeren terwijl die andere portie nog aan het
        // versturen is.
        $open = NewsletterCampaignRecipient::where('newsletter_campaign_id', $campaign->id)
            ->whereIn('status', [
                NewsletterCampaignRecipient::STATUS_PENDING,
                NewsletterCampaignRecipient::STATUS_SENDING,
            ])
            ->exists();

        if (! $open && $campaign->status === NewsletterCampaign::STATUS_SENDING) {
            $campaign->update([
                'status' => NewsletterCampaign::STATUS_SENT,
                'completed_at' => now(),
            ]);
        }
    }
}
