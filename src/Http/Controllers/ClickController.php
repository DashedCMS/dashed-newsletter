<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Dashed\DashedNewsletter\Campaigns\SignedLink;
use Dashed\DashedNewsletter\Models\NewsletterCampaignLink;
use Dashed\DashedNewsletter\Models\NewsletterCampaignClick;
use Dashed\DashedNewsletter\Models\NewsletterCampaignRecipient;

/**
 * De klikroute uit een campagnemail.
 *
 * Twee dingen liggen hier vast, en ze beschermen tegen twee verschillende
 * aanvallen. Ze zijn niet inwisselbaar.
 *
 * 1. De bestemming komt ALTIJD uit dashed__newsletter_campaign_links en nooit
 *    uit het verzoek. Zou de URL als parameter meekomen, dan is deze route een
 *    open redirect: iemand zet er zijn eigen adres in, verstuurt die link, en
 *    het slachtoffer ziet het domein van de webshop in de balk staan voordat
 *    hij op een namaak-inlogpagina belandt.
 * 2. De handtekening beschermt tegen sleutelen aan de ontvanger, zodat niemand
 *    klikken op naam van een ander kan zetten.
 *
 * Een ongeldige handtekening leidt bewust niet tot een 403. Een mailprogramma
 * kan een URL verminken, en dan is de ontvanger niet de schuldige; die hoort
 * gewoon op de pagina uit te komen. We laten dan de meting vallen, niet de
 * bezoeker.
 */
class ClickController
{
    public function __invoke(Request $request, int $link, int $recipient)
    {
        $linkRegel = NewsletterCampaignLink::find($link);

        if (! $linkRegel) {
            abort(404);
        }

        if (SignedLink::isValid($request)) {
            $this->legVast($linkRegel, $recipient);
        } else {
            $this->meldOngeldigeHandtekening($request, 'klik');
        }

        // away() en niet to(): dit is een externe bestemming. De waarde komt
        // uit onze eigen tabel, zie de toelichting hierboven.
        return redirect()->away($linkRegel->url);
    }

    private function legVast(NewsletterCampaignLink $link, int $recipient): void
    {
        // Ook op campagne matchen en niet alleen op id: verdediging in de
        // diepte naast de handtekening, zodat een geldig ondertekende link van
        // campagne A nooit een klik op campagne B oplevert.
        $regel = NewsletterCampaignRecipient::where('id', $recipient)
            ->where('newsletter_campaign_id', $link->newsletter_campaign_id)
            ->first();

        if (! $regel) {
            return;
        }

        NewsletterCampaignClick::create([
            'newsletter_campaign_id' => $link->newsletter_campaign_id,
            'newsletter_campaign_link_id' => $link->id,
            'newsletter_campaign_recipient_id' => $regel->id,
            'clicked_at' => now(),
        ]);

        $regel->forceFill([
            'clicked_at' => $regel->clicked_at ?? now(),
            'click_count' => $regel->click_count + 1,
        ])->save();
    }

    /**
     * Een ongeldige handtekening gaat stil door naar de bezoeker, maar niet
     * stil langs de beheerder.
     *
     * Staat APP_URL op http terwijl de site https serveert, dan is elke
     * handtekening ongeldig: hij is over de ene URL gezet en over de andere
     * gecontroleerd. Alle cijfers blijven dan nul en de afmeldlink geeft 403,
     * zonder een enkel spoor om op af te gaan. Deze regel is de draad waar je
     * dan aan kunt trekken.
     */
    private function meldOngeldigeHandtekening(Request $request, string $route): void
    {
        Log::warning('Nieuwsbrief: handtekening klopt niet, meting overgeslagen.', [
            'route' => $route,
            'url' => $request->url(),
            'app_url' => config('app.url'),
            'tip' => 'Komt de APP_URL exact overeen met het adres waarop de site draait, inclusief http of https?',
        ]);
    }
}
