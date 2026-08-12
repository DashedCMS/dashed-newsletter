<?php

use Illuminate\Support\Facades\Route;
use Dashed\DashedNewsletter\Http\Controllers\UnsubscribeController;

// GET voor de link in de mail, POST voor de afmeldknop die een mailbox zelf
// toont bij een List-Unsubscribe-Post-kop.
Route::match(['get', 'post'], '/nieuwsbrief/afmelden/{recipient}', UnsubscribeController::class)
    ->name('dashed.frontend.newsletter.unsubscribe')
    ->middleware('web');
