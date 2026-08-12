<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Campaigns;

use Dashed\DashedNewsletter\Segments\SegmentQuery;
use Dashed\DashedNewsletter\Models\NewsletterCampaign;
use Dashed\DashedNewsletter\Models\NewsletterSubscriber;
use Dashed\DashedNewsletter\Models\NewsletterSuppression;
use Dashed\DashedNewsletter\Models\NewsletterCampaignRecipient;

/**
 * Legt de ontvangers van een campagne één keer vast.
 *
 * Wat afvalt wordt niet weggelaten maar vastgelegd met een reden, zodat
 * achteraf te zien is waarom iemand geen post kreeg.
 */
class CampaignRecipients
{
    public static function build(NewsletterCampaign $campaign): int
    {
        // Een segment vertaalt zichzelf naar een query; zonder segment is het de
        // hele lijst. Een leeg of ongeldig segment gooit hier, en dat hoort:
        // zie CampaignGuard.
        $query = $campaign->segment
            ? SegmentQuery::for($campaign->segment)
            : NewsletterSubscriber::where('newsletter_list_id', $campaign->newsletter_list_id);

        $siteId = (string) ($campaign->site_id ?? '');
        $pending = 0;
        $skipped = 0;

        $query->chunkById(500, function ($subscribers) use ($campaign, $siteId, &$pending, &$skipped): void {
            foreach ($subscribers as $subscriber) {
                [$status, $reason] = self::verdict($subscriber, $siteId);

                NewsletterCampaignRecipient::updateOrCreate(
                    [
                        'newsletter_campaign_id' => $campaign->id,
                        'newsletter_subscriber_id' => $subscriber->id,
                    ],
                    [
                        'email' => $subscriber->email,
                        'status' => $status,
                        'skip_reason' => $reason,
                    ]
                );

                $status === NewsletterCampaignRecipient::STATUS_PENDING ? $pending++ : $skipped++;
            }
        });

        $campaign->update([
            'recipients_count' => $pending,
            'skipped_count' => $skipped,
        ]);

        return $pending;
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    private static function verdict(NewsletterSubscriber $subscriber, string $siteId): array
    {
        // Zelf op status filteren en niet op het segment vertrouwen: een segment
        // zonder statusvoorwaarde levert ook uitgeschreven contacten op.
        if ($subscriber->status !== NewsletterSubscriber::STATUS_ACTIVE) {
            return [NewsletterCampaignRecipient::STATUS_SKIPPED, NewsletterCampaignRecipient::SKIP_UNSUBSCRIBED];
        }

        if (! filter_var($subscriber->email, FILTER_VALIDATE_EMAIL)) {
            return [NewsletterCampaignRecipient::STATUS_SKIPPED, NewsletterCampaignRecipient::SKIP_INVALID_EMAIL];
        }

        if ($siteId !== '' && NewsletterSuppression::blocks($siteId, $subscriber->email)) {
            return [NewsletterCampaignRecipient::STATUS_SKIPPED, NewsletterCampaignRecipient::SKIP_SUPPRESSED];
        }

        return [NewsletterCampaignRecipient::STATUS_PENDING, null];
    }
}
