<?php

use Illuminate\Support\Facades\Route;
use Dashed\DashedNewsletter\Http\Controllers\UnsubscribeController;
use Dashed\DashedNewsletter\Http\Controllers\TestUnsubscribeController;

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
