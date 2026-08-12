<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Campaigns;

use Dashed\DashedNewsletter\Segments\SegmentQuery;
use Dashed\DashedNewsletter\Models\NewsletterCampaign;
use Dashed\DashedNewsletter\Models\NewsletterSubscriber;
use Dashed\DashedNewsletter\Models\NewsletterSuppression;
use Dashed\DashedNewsletter\Models\NewsletterCampaignRecipient;

/**
 * Legt de ontvangers van een campagne vast, en mag herhaald draaien: dat
 * gebeurt echt bij een afgebroken campagne die na reparatie opnieuw start
 * (zie CampaignCanceller). Wat al opgepakt, verzonden of onderbroken is
 * blijft dan met rust; alleen pending en skipped worden opnieuw beoordeeld.
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

        // effectiveSiteId() en niet rechtstreeks $campaign->site_id: die kolom
        // is nullable, en zonder deze afleiding sloeg een campagne zonder
        // eigen site_id (elke aanmaak buiten het scherm om, want daar staat
        // hij altijd vast) de blokkadelijst hieronder stilzwijgend over. Zie
        // NewsletterCampaign::effectiveSiteId().
        $siteId = (string) ($campaign->effectiveSiteId() ?? '');

        $query->chunkById(500, function ($subscribers) use ($campaign, $siteId): void {
            foreach ($subscribers as $subscriber) {
                $recipient = NewsletterCampaignRecipient::firstOrNew([
                    'newsletter_campaign_id' => $campaign->id,
                    'newsletter_subscriber_id' => $subscriber->id,
                ]);

                // build() mag herhaald worden (vandaag alleen bereikbaar via
                // een afgebroken campagne die opnieuw start), en dan mag een
                // regel die al opgepakt (sending), afgerond (sent, failed) of
                // onderbroken (interrupted, zie NewsletterCampaignRecipient::
                // STATUS_INTERRUPTED) is niet worden aangeraakt: dat zou een
                // verzonden of mogelijk-verzonden mail ongedaan maken en de
                // regel opnieuw claimbaar maken voor CampaignSender. Alleen
                // pending en skipped, en een nieuwe regel, mogen (opnieuw)
                // beoordeeld worden: dat vangt bijvoorbeeld een adres dat pas
                // ná de eerste opbouw geblokkeerd raakte, of een wachtende
                // ontvanger die de vorige, afgebroken poging nooit bereikte.
                if ($recipient->exists && ! in_array($recipient->status, [
                    NewsletterCampaignRecipient::STATUS_PENDING,
                    NewsletterCampaignRecipient::STATUS_SKIPPED,
                ], true)) {
                    continue;
                }

                [$status, $reason] = self::verdict($subscriber, $siteId);

                $recipient->fill([
                    'email' => $subscriber->email,
                    'status' => $status,
                    'skip_reason' => $reason,
                ])->save();
            }
        });

        return self::refreshCounts($campaign);
    }

    /**
     * recipients_count is het werkelijke aantal ontvangers van de campagne,
     * niet alleen de regels die déze aanroep van build() beoordeelde. Dat
     * onderscheid doet er bij een verse campagne niet toe (alle regels zijn
     * dan nieuw), maar wel bij een herstart na afbreken: regels die al
     * 'sent'/'interrupted' zijn slaat de lus hierboven bewust over, en zonder
     * deze herberekening zou recipients_count dan alleen het opnieuw
     * beoordeelde restje tellen. Dat gaf "2 van 1 verzonden" op het scherm:
     * sent_count bleef doortellen terwijl recipients_count kromp. Een verse
     * telling over de hele tabel voorkomt dat, ongeacht hoe vaak build()
     * al draaide.
     */
    private static function refreshCounts(NewsletterCampaign $campaign): int
    {
        $totaal = NewsletterCampaignRecipient::where('newsletter_campaign_id', $campaign->id)->count();
        $skipped = NewsletterCampaignRecipient::where('newsletter_campaign_id', $campaign->id)
            ->where('status', NewsletterCampaignRecipient::STATUS_SKIPPED)
            ->count();
        $pending = NewsletterCampaignRecipient::where('newsletter_campaign_id', $campaign->id)
            ->where('status', NewsletterCampaignRecipient::STATUS_PENDING)
            ->count();

        $campaign->update([
            'recipients_count' => $totaal - $skipped,
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
