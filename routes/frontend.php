<?php

use Illuminate\Support\Facades\Route;
use Dashed\DashedNewsletter\Http\Controllers\ClickController;
use Dashed\DashedNewsletter\Http\Controllers\OpenPixelController;
use Dashed\DashedNewsletter\Http\Controllers\UnsubscribeController;
use Dashed\DashedNewsletter\Http\Controllers\TestUnsubscribeController;
use Dashed\DashedNewsletter\Http\Controllers\CampaignWebVersionController;

// Specifiekere route eerst en bewust op een eigen pad: Laravel matcht op
// registratievolgorde, dus zou deze ná '/nieuwsbrief/afmelden/{recipient}'
// staan, dan ving die generieke route 'proefmail' net zo goed op als elk
// ander (niet-numeriek) segment. Zie UnsubscribeLink::for(): een testmail
// heeft geen opgeslagen ontvangerregel, dus geen geldig id om naar te
// ondertekenen, en wijst hierheen in plaats van naar de gewone afmeldlink.
Route::match(['get', 'post'], '/nieuwsbrief/afmelden/proefmail', TestUnsubscribeController::class)
    ->name('dashed.frontend.newsletter.unsubscribe-test');

// GET voor de link in de mail, POST voor de afmeldknop die een mailbox zelf
// toont bij een List-Unsubscribe-Post-kop.
//
// Bewust buiten de web-groep: die groep bevat CSRF-verificatie, en een
// one-click-afmelding vanuit Gmail of Yahoo (RFC 8058) komt binnen als een
// server-naar-server POST zonder sessie en zonder token. Onder de web-groep
// zou CSRF die aanvraag afwijzen voordat de controller de handtekening ooit
// kan controleren. De beveiliging van deze route is de ondertekende URL, geen
// sessie: er is geen formulier en geen flash-bericht nodig.
// GET en POST doen hier bewust iets verschillends, zie UnsubscribeController.
// Kort: de POST is de one-click-afmelding die Gmail en Yahoo zelf uitvoeren
// (RFC 8058) en die moet direct uitschrijven; de GET is de link in de mail en
// die hoort eerst een scherm te tonen, al was het maar omdat scanners van
// bedrijfsmail links vooruit ophalen.
Route::get('/nieuwsbrief/afmelden/{recipient}', [UnsubscribeController::class, 'show'])
    ->whereNumber('recipient')
    ->name('dashed.frontend.newsletter.unsubscribe');

Route::post('/nieuwsbrief/afmelden/{recipient}', [UnsubscribeController::class, 'oneClick'])
    ->whereNumber('recipient')
    ->name('dashed.frontend.newsletter.unsubscribe-one-click');

// De knop op onze eigen bevestigingspagina. Een eigen pad, zodat de route
// hierboven zuiver de one-click van RFC 8058 blijft.
Route::post('/nieuwsbrief/afmelden/{recipient}/bevestigen', [UnsubscribeController::class, 'confirm'])
    ->whereNumber('recipient')
    ->name('dashed.frontend.newsletter.unsubscribe-confirm');

Route::post('/nieuwsbrief/afmelden/{recipient}/opnieuw', [UnsubscribeController::class, 'resubscribe'])
    ->whereNumber('recipient')
    ->name('dashed.frontend.newsletter.resubscribe');

// Ook buiten de web-groep: dezelfde reden als hierboven bij de afmeldroute.
// De webversie toont de gepersonaliseerde tekst van één ontvanger, en de
// ondertekende URL is daarvoor de enige bescherming. CSRF beschermt hier
// niets (geen formulier, geen sessie) en zou in de weg zitten.
Route::get('/nieuwsbrief/bekijken/{recipient}', CampaignWebVersionController::class)
    ->name('dashed-newsletter.campaign.web-version');

// Ook buiten de web-groep: zelfde reden als hierboven. Bewust géén
// type-hinted model in de closure-signature: zonder de web-groep loopt
// SubstituteBindings niet mee, dus impliciete route-model-binding gebeurt
// hier niet — Laravel zou dan stilzwijgend een lege NewsletterCampaign()
// uit de container maken in plaats van de aangevraagde campagne op te
// zoeken (transformDependency() in ResolvesRouteDependencies valt terug op
// container->make() zodra de parameter nog geen model-instantie is). Vandaar
// de int + handmatige findOrFail(), net als bij de routes hierboven.
Route::get('/nieuwsbrief/preview/{campaign}', function (int $campaign) {
    $campaign = \Dashed\DashedNewsletter\Models\NewsletterCampaign::findOrFail($campaign);

    return response(
        app(\Dashed\DashedNewsletter\Campaigns\CampaignRenderer::class)->renderTemplate($campaign)
    )->header('Content-Type', 'text/html');
})->middleware('signed')->name('dashed-newsletter.campaign.preview');

// Buiten de web-groep, om dezelfde reden als de afmeldroute hierboven: er is
// geen sessie en geen formulier, de ondertekende URL is de beveiliging, en
// CSRF zou hier alleen in de weg zitten. Een klik komt bovendien uit een
// mailprogramma, dus er is per definitie geen token mee te sturen.
Route::get('/nieuwsbrief/klik/{link}/{recipient}', ClickController::class)
    ->whereNumber(['link', 'recipient'])
    ->name('dashed-newsletter.campaign.click');

Route::get('/nieuwsbrief/pixel/{recipient}', OpenPixelController::class)
    ->whereNumber('recipient')
    ->name('dashed-newsletter.campaign.open');
