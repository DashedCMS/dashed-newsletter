<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Campaigns;

use Dashed\DashedNewsletter\Models\NewsletterCampaign;
use Dashed\DashedNewsletter\Models\NewsletterCampaignLink;

/**
 * Schrijft de links in een gerenderde campagne om naar een plaatshouder, en
 * legt onderweg vast welke URL bij welke plaatshouder hoort.
 *
 * De plaatshouder heeft de vorm :click_<id>: en past daarmee in de regex van
 * CampaignRenderer::vervang() (`/:(\w+):/`). De echte, ondertekende URL wordt
 * daar per ontvanger gemaakt: die verschilt per persoon, dus hij kan hier nog
 * niet bestaan.
 */
class LinkRewriter
{
    /**
     * Schema's die geen webadres zijn, plus het anker. Die horen niet door een
     * omleiding te gaan: een mailto die via de webserver loopt opent geen
     * mailprogramma meer.
     */
    private const OVERSLAAN = ['mailto:', 'tel:', 'sms:', '#'];

    public function rewrite(NewsletterCampaign $campaign, string $html): string
    {
        return (string) preg_replace_callback(
            '/href=(["\'])(.*?)\1/i',
            function (array $match) use ($campaign): string {
                $url = trim($match[2]);

                if (! $this->omschrijfbaar($url)) {
                    return $match[0];
                }

                // firstOrCreate en niet create: dezelfde knop kan boven en
                // onder in de mail staan, en dan hoort dat een link te zijn.
                // Anders telt hij als twee in de statistieken.
                $link = NewsletterCampaignLink::firstOrCreate([
                    'newsletter_campaign_id' => $campaign->id,
                    'url' => $url,
                ]);

                return 'href=' . $match[1] . ':click_' . $link->id . ':' . $match[1];
            },
            $html,
        );
    }

    private function omschrijfbaar(string $url): bool
    {
        if ($url === '') {
            return false;
        }

        // Bevat de URL een plaatshouder, dan blijft hij met rust. Twee
        // gevallen, en de tweede is de reden dat dit op "bevat" test en niet
        // op "is".
        //
        // De afmeldlink en de webversie zijn zelf een plaatshouder en vullen
        // zich per ontvanger in; die door de klikroute sturen zou afmelden
        // laten afhangen van een tweede ondertekende omleiding.
        //
        // En een blok kan een absolute URL opleveren met personalisatie erin,
        // zoals https://example.com/?ref=:voornaam:. Die begint gewoon met
        // https, dus de eis hieronder vangt hem niet. Zou hij wel omgeschreven
        // worden, dan staat er een URL met een letterlijke plaatshouder in de
        // linktabel en stuurt de klikroute de ontvanger naar een adres dat
        // niet bestaat.
        if (preg_match('/:\w+:/', $url)) {
            return false;
        }

        foreach (self::OVERSLAAN as $voorvoegsel) {
            if (str_starts_with(mb_strtolower($url), $voorvoegsel)) {
                return false;
            }
        }

        // Alleen absolute webadressen. Een relatief pad in een mail werkt
        // sowieso niet, en zonder host valt er ook niets zinnigs door te
        // sturen.
        return (bool) preg_match('#^https?://#i', $url);
    }
}
