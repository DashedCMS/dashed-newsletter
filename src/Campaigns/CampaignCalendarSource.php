<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Campaigns;

use Illuminate\Support\Carbon;
use Dashed\DashedCore\Classes\Trash\Trash;
use Dashed\DashedNewsletter\Models\NewsletterCampaign;
use Dashed\DashedCore\Classes\Calendar\CalendarEvent;
use Dashed\DashedCore\Classes\Calendar\CampaignCalendar;

/**
 * Nieuwsbrieven op de campagnekalender: ingepland op de geplande dag,
 * verzonden op de dag van verzenden. Twee campagnes op dezelfde dag naar
 * dezelfde lijst zijn een conflict.
 */
class CampaignCalendarSource
{
    public static function register(): void
    {
        if (! class_exists(CampaignCalendar::class)) {
            return;
        }

        CampaignCalendar::source('newsletters', __('Nieuwsbrieven'), '#0ea5e9', function (Carbon $from, Carbon $to, ?string $siteId) {
            return NewsletterCampaign::query()
                ->whereIn('status', [NewsletterCampaign::STATUS_SCHEDULED, NewsletterCampaign::STATUS_SENDING, NewsletterCampaign::STATUS_SENT])
                ->where(fn ($q) => $q->whereBetween('scheduled_at', [$from, $to])->orWhereBetween('started_at', [$from, $to]))
                ->when($siteId, fn ($q) => $q->where('site_id', $siteId))
                ->limit(200)
                ->get()
                ->map(function (NewsletterCampaign $campaign) {
                    $day = Carbon::parse($campaign->started_at ?? $campaign->scheduled_at);

                    return new CalendarEvent(
                        source: 'newsletters',
                        title: __('Nieuwsbrief: :naam', ['naam' => $campaign->name]),
                        start: $day->copy()->startOfDay(),
                        end: $day->copy()->endOfDay(),
                        url: Trash::editUrl($campaign),
                        overlapKeys: ['newsletter:list:'.$campaign->newsletter_list_id],
                    );
                })
                ->all();
        });
    }
}
