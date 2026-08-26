<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Campaigns;

use Illuminate\Support\Facades\DB;
use Dashed\DashedNewsletter\Models\NewsletterCampaign;
use Dashed\DashedNewsletter\Models\NewsletterCampaignClick;
use Dashed\DashedNewsletter\Models\NewsletterCampaignRecipient;

/**
 * De cijfers van een campagne, geteld uit de ontvangerregels en de klikken.
 *
 * Bewust niet uit dashed__sent_emails: die tabel wordt na negentig dagen
 * opgeruimd, en dan zou een campagne van vorig jaar beweren dat er nooit iets
 * is aangekomen.
 *
 * Percentages zijn null als de noemer nul is, nooit 0.0. Het scherm moet "niet
 * gemeten" kunnen onderscheiden van "nul procent"; dat verschil is de hele
 * reden dat dit project bestaat.
 */
class CampaignStatistics
{
    public function __construct(private NewsletterCampaign $campaign)
    {
    }

    /** @return array<string, mixed> */
    public function totals(): array
    {
        $regels = NewsletterCampaignRecipient::where('newsletter_campaign_id', $this->campaign->id);

        $recipients = (clone $regels)->count();
        $sent = (clone $regels)->where('status', NewsletterCampaignRecipient::STATUS_SENT)->count();
        $failed = (clone $regels)->where('status', NewsletterCampaignRecipient::STATUS_FAILED)->count();
        $skipped = (clone $regels)->where('status', NewsletterCampaignRecipient::STATUS_SKIPPED)->count();
        $delivered = (clone $regels)->whereNotNull('delivered_at')->count();
        $bounced = (clone $regels)->whereNotNull('bounced_at')->count();
        $complained = (clone $regels)->whereNotNull('complained_at')->count();
        $openers = (clone $regels)->whereNotNull('opened_at')->count();
        $opens = (int) (clone $regels)->sum('open_count');
        $clickers = (clone $regels)->whereNotNull('clicked_at')->count();
        $clicks = (int) (clone $regels)->sum('click_count');
        $unsubscribed = (clone $regels)->whereNotNull('unsubscribed_at')->count();

        $list = $this->campaign->list;

        // Bezorging, bounces en klachten zijn de enige cijfers die we niet
        // zelf kunnen weten: of een mailserver een bericht aannam, weet alleen
        // de verzendende infrastructuur. Ze komen binnen via de
        // Postmark-webhook, en die bereikt geen ontwikkelmachine en geen
        // installatie zonder koppeling.
        //
        // Alle andere cijfers meten we wel zelf. Zou de noemer onvoorwaardelijk
        // 'bezorgd' blijven, dan is die zonder webhook nul en verdwijnt alles
        // wat we zelf gemeten hebben achter een streepje: een percentage dat
        // volledig van onszelf is, zou dan alsnog van een externe koppeling
        // afhangen. Vandaar de terugval op verzonden.
        //
        // Alles gebounced telt wel als informatie. Dan is bezorgd terecht nul
        // en hoort een openingspercentage leeg te blijven: niemand heeft de
        // mail ontvangen, en nul procent zou suggereren dat mensen hem kregen
        // en niet openden.
        $hasDeliveryInfo = $delivered > 0 || $bounced > 0 || $complained > 0;
        $basis = $hasDeliveryInfo ? $delivered : $sent;

        return [
            'recipients' => $recipients,
            'sent' => $sent,
            'failed' => $failed,
            'skipped' => $skipped,
            'delivered' => $delivered,
            'bounced' => $bounced,
            'complained' => $complained,
            'openers' => $openers,
            'opens' => $opens,
            'clickers' => $clickers,
            'clicks' => $clicks,
            'unsubscribed' => $unsubscribed,
            'hasDeliveryInfo' => $hasDeliveryInfo,
            'percentageBase' => $hasDeliveryInfo ? 'delivered' : 'sent',
            // Leeg zonder bezorginformatie, en niet nul procent: nul zou
            // betekenen dat er niets aankwam, en dat weten we niet. We weten
            // alleen dat niemand het ons verteld heeft.
            'deliveredPercentage' => $hasDeliveryInfo ? $this->deel($delivered, $sent) : null,
            'openPercentage' => $this->deel($openers, $basis),
            'clickPercentage' => $this->deel($clickers, $basis),
            // Over de openers en niet over de bezorgden: dit zegt iets over de
            // inhoud van de mail, niet over de bezorgbaarheid van de lijst.
            'clickToOpenPercentage' => $this->deel($clickers, $openers),
            'unsubscribePercentage' => $this->deel($unsubscribed, $basis),
            'durationInSeconds' => $this->campaign->started_at && $this->campaign->completed_at
                ? (int) abs($this->campaign->completed_at->diffInSeconds($this->campaign->started_at))
                : null,
            'tracksOpens' => (bool) $list?->track_opens,
            'tracksClicks' => (bool) $list?->track_clicks,
        ];
    }

    /** @return array<int, array{url: string, clicks: int, clickers: int, share: float}> */
    public function links(): array
    {
        $rijen = NewsletterCampaignClick::query()
            ->join(
                'dashed__newsletter_campaign_links as links',
                'links.id',
                '=',
                'dashed__newsletter_campaign_clicks.newsletter_campaign_link_id',
            )
            ->where('dashed__newsletter_campaign_clicks.newsletter_campaign_id', $this->campaign->id)
            ->groupBy('links.id', 'links.url')
            ->select([
                'links.url',
                DB::raw('COUNT(*) as klikken'),
                DB::raw('COUNT(DISTINCT dashed__newsletter_campaign_clicks.newsletter_campaign_recipient_id) as klikkers'),
            ])
            ->get();

        $totaal = (int) $rijen->sum('klikken');

        return $rijen
            ->map(fn ($rij): array => [
                'url' => (string) $rij->url,
                'clicks' => (int) $rij->klikken,
                'clickers' => (int) $rij->klikkers,
                'share' => $totaal > 0 ? round(((int) $rij->klikken / $totaal) * 100, 1) : 0.0,
            ])
            // Sorteren op unieke klikkers en niet op het totaal: drie klikken
            // van een persoon zegt minder dan twee van twee.
            ->sortByDesc('clickers')
            ->values()
            ->all();
    }

    private function deel(int $teller, int $noemer): ?float
    {
        if ($noemer === 0) {
            return null;
        }

        return round(($teller / $noemer) * 100, 1);
    }
}
