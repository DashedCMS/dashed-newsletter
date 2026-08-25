<?php

use Illuminate\Support\Facades\Route;
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
Route::match(['get', 'post'], '/nieuwsbrief/afmelden/{recipient}', UnsubscribeController::class)
    ->name('dashed.frontend.newsletter.unsubscribe');

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
