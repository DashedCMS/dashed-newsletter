<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Campaigns;

use Illuminate\Database\Eloquent\Builder;
use Dashed\DashedNewsletter\Models\NewsletterList;
use Dashed\DashedNewsletter\Models\NewsletterCampaign;
use Dashed\DashedNewsletter\Models\NewsletterCampaignRecipient;

/**
 * Wat mensen opgeven als ze zich afmelden, over alles heen, per lijst of per
 * campagne.
 *
 * De reden staat op de ontvangerregel, en die weet al bij welke campagne en
 * welke lijst hij hoort. Daarom is dit alleen een kwestie van tellen en heeft
 * het geen eigen tabel nodig.
 *
 * Een afmelding die is teruggedraaid telt niet mee: de controller wist bij
 * opnieuw aanmelden zowel het tijdstip als de reden, dus het filter op
 * unsubscribed_at doet dat vanzelf goed.
 */
class UnsubscribeReasons
{
    /**
     * Aantal per reden, aflopend. Redenen waar niemand voor koos ontbreken;
     * een rij met nul erachter zegt niets en maakt het scherm alleen langer.
     *
     * @return array<string, int>
     */
    public static function totals(?NewsletterList $list = null, ?NewsletterCampaign $campaign = null): array
    {
        return self::query($list, $campaign)
            ->whereNotNull('unsubscribe_reason')
            ->selectRaw('unsubscribe_reason, COUNT(*) as aantal')
            ->groupBy('unsubscribe_reason')
            ->pluck('aantal', 'unsubscribe_reason')
            ->map(fn ($aantal): int => (int) $aantal)
            ->sortDesc()
            ->all();
    }

    /** Alle afmeldingen, met en zonder reden. */
    public static function total(?NewsletterList $list = null, ?NewsletterCampaign $campaign = null): int
    {
        return self::query($list, $campaign)->count();
    }

    /**
     * Hoeveel mensen zich afmeldden zonder een reden te kiezen.
     *
     * Apart tellen en niet weglaten: zonder dit getal lijkt het alsof iedereen
     * een reden gaf, en dan overschat je hoe representatief die redenen zijn.
     */
    public static function withoutReason(?NewsletterList $list = null, ?NewsletterCampaign $campaign = null): int
    {
        return self::query($list, $campaign)->whereNull('unsubscribe_reason')->count();
    }

    /**
     * De vrije toelichtingen, nieuwste eerst.
     *
     * @return array<int, array{email: string, reason: ?string, comment: string, at: ?string, campaign: ?string}>
     */
    public static function comments(?NewsletterList $list = null, ?NewsletterCampaign $campaign = null, int $limit = 100): array
    {
        return self::query($list, $campaign)
            ->whereNotNull('unsubscribe_comment')
            ->with('campaign:id,name')
            ->orderByDesc('unsubscribed_at')
            ->limit($limit)
            ->get()
            ->map(fn (NewsletterCampaignRecipient $regel): array => [
                'email' => (string) $regel->email,
                'reason' => $regel->unsubscribe_reason,
                'comment' => (string) $regel->unsubscribe_comment,
                'at' => $regel->unsubscribed_at?->format('d-m-Y H:i'),
                'campaign' => $regel->campaign?->name,
            ])
            ->all();
    }

    private static function query(?NewsletterList $list, ?NewsletterCampaign $campaign): Builder
    {
        $query = NewsletterCampaignRecipient::query()->whereNotNull('unsubscribed_at');

        if ($campaign) {
            return $query->where('newsletter_campaign_id', $campaign->id);
        }

        if ($list) {
            // Via de campagnes van de lijst: de ontvangerregel kent de lijst
            // niet rechtstreeks, en dat hoort ook niet, want die staat al op
            // de campagne.
            $query->whereIn(
                'newsletter_campaign_id',
                NewsletterCampaign::where('newsletter_list_id', $list->id)->select('id'),
            );
        }

        return $query;
    }
}
