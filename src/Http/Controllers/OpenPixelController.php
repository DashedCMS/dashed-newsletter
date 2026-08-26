<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Dashed\DashedNewsletter\Campaigns\SignedLink;
use Dashed\DashedNewsletter\Models\NewsletterCampaignRecipient;

/**
 * Het onzichtbare plaatje waarmee een opening gemeten wordt.
 *
 * Geeft altijd een geldig plaatje terug, ook bij een ongeldige handtekening of
 * een ontvanger die niet meer bestaat. Een kapot plaatje is een zichtbare fout
 * in de inbox van een ander, en een gemiste meting is dat niet waard.
 */
class OpenPixelController
{
    /** Een doorzichtige gif van een bij een pixel. */
    private const GIF = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    public function __invoke(Request $request, int $recipient)
    {
        if (SignedLink::isValid($request)) {
            $this->legVast($recipient);
        } else {
            $this->meldOngeldigeHandtekening($request, 'pixel');
        }

        // Zonder deze koppen kan een proxy of een mailclient het plaatje
        // bewaren, en dan wordt een tweede opening nooit meer gemeten.
        return response(base64_decode(self::GIF), 200, [
            'Content-Type' => 'image/gif',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    private function legVast(int $recipient): void
    {
        $regel = NewsletterCampaignRecipient::find($recipient);

        if (! $regel) {
            return;
        }

        $regel->forceFill([
            'opened_at' => $regel->opened_at ?? now(),
            'open_count' => $regel->open_count + 1,
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
