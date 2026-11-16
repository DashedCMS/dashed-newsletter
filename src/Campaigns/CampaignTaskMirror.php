<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Campaigns;

use Dashed\DashedCore\Models\BackgroundTask;
use Dashed\DashedNewsletter\Models\NewsletterCampaign;
use Dashed\DashedNewsletter\Models\NewsletterCampaignRecipient;

/**
 * Spiegelt het verzenden van een campagne naar het takenicoon van
 * dashed-core: verstuurd van het totaal, per portie bijgewerkt. Achter
 * class_exists, want een oudere dashed-core kent BackgroundTask nog niet.
 */
class CampaignTaskMirror
{
    public static function sync(NewsletterCampaign $campaign): void
    {
        if (! class_exists(BackgroundTask::class)) {
            return;
        }

        rescue(function () use ($campaign) {
            $counts = NewsletterCampaignRecipient::query()
                ->where('newsletter_campaign_id', $campaign->id)
                ->selectRaw('count(*) as total')
                ->selectRaw('sum(case when status in (?, ?) then 1 else 0 end) as open', [
                    NewsletterCampaignRecipient::STATUS_PENDING,
                    NewsletterCampaignRecipient::STATUS_SENDING,
                ])
                ->first();

            $total = (int) ($counts->total ?? 0);
            $done = $total - (int) ($counts->open ?? 0);

            $status = match ($campaign->status) {
                NewsletterCampaign::STATUS_SENT => BackgroundTask::STATUS_COMPLETED,
                // Afgebroken telt als mislukt: anders blijft de taak als lopend
                // in de topbalk staan tot markStale() hem na twee uur opruimt.
                NewsletterCampaign::STATUS_FAILED, NewsletterCampaign::STATUS_CANCELLED => BackgroundTask::STATUS_FAILED,
                default => BackgroundTask::STATUS_RUNNING,
            };

            $message = match (true) {
                $campaign->status === NewsletterCampaign::STATUS_CANCELLED => __('Verzenden afgebroken: :aantal van :totaal ontvangers verwerkt.', ['aantal' => $done, 'totaal' => $total]),
                $status === BackgroundTask::STATUS_FAILED => $campaign->failure_reason,
                $status === BackgroundTask::STATUS_COMPLETED => __(':aantal ontvangers verwerkt.', ['aantal' => $total]),
                default => null,
            };

            BackgroundTask::mirror($campaign, array_merge([
                'type' => 'newsletter',
                'label' => __('Nieuwsbrief versturen: :naam', ['naam' => $campaign->name]),
                'status' => $status,
                'done' => $done,
                'total' => $total,
                'message' => $message,
                'notify' => false,
            ], $status === BackgroundTask::STATUS_RUNNING
                // Een afgebroken campagne die opnieuw gestart wordt, loopt
                // weer: dan hoort er geen eindtijd meer op de taak.
                ? ['finished_at' => null]
                : []));
        }, report: true);
    }
}
