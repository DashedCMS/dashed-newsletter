<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Campaigns;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * Ondertekende links in een nieuwsbriefmail, en de controle daarop.
 *
 * De handtekening dekt alleen het pad en de querystring, niet de host en het
 * schema. Dat is een bewuste verruiming, en de reden staat in de
 * geschiedenis van dit pakket: een handtekening over de volledige URL is
 * ongeldig zodra het adres waarop de site draait ook maar iets afwijkt van
 * wat er bij het versturen bekend was. Vier manieren waarop dat gebeurt,
 * alle vier hier voorgekomen:
 *
 *   - APP_URL staat op http terwijl de site https serveert;
 *   - een omleiding van http naar https vóór Laravel;
 *   - een proxy die het schema niet doorgeeft;
 *   - een wachtrij-worker die APP_URL nog uit een eerdere versie in het
 *     geheugen heeft, en dus mails verstuurt met links die al fout zijn op
 *     het moment dat ze de deur uit gaan.
 *
 * Het gevolg was elke keer hetzelfde en elke keer onzichtbaar: cijfers die
 * op nul bleven staan, en een afmeldlink die 403 gaf. Een verkeerde host is
 * een zichtbare fout (de link komt nergens uit), een ongeldige handtekening
 * was dat niet.
 *
 * Wat de verruiming kost: zo'n link is geldig op elke host die deze
 * applicatie draait. Wat hij niet kost: de inhoud blijft ondertekend, dus
 * sleutelen aan het ontvanger-id of het link-id in het pad wordt nog steeds
 * geweigerd. Dat is wat er werkelijk beschermd moet worden.
 */
class SignedLink
{
    /**
     * Een absolute link waarvan alleen het pad ondertekend is.
     *
     * @param array<string, mixed> $parameters
     */
    public static function to(string $route, array $parameters): string
    {
        // absolute: false levert een pad op ('/nieuwsbrief/pixel/1?signature=..').
        // De host plakken we er daarna zelf voor, want in een mail moet een
        // link absoluut zijn.
        return url(URL::signedRoute($route, $parameters, absolute: false));
    }

    /**
     * Accepteert zowel een link waarvan alleen het pad ondertekend is als een
     * volledig ondertekende link.
     *
     * Die tweede vorm staat er voor de mails die al verstuurd zijn: die dragen
     * een handtekening over de hele URL, en die horen te blijven werken.
     * Anders breekt deze verbetering met terugwerkende kracht elke afmeldlink
     * die al bij iemand in de inbox ligt, en dat is precies de knop die nooit
     * stuk mag.
     */
    public static function isValid(Request $request): bool
    {
        return $request->hasValidSignature(absolute: false)
            || $request->hasValidSignature();
    }
}
