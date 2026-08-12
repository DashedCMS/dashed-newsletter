<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Http\Controllers;

use Illuminate\Routing\Controller;

/**
 * De afmeldlink in een testmail (zie EditNewsletterCampaign::sendTest() en
 * UnsubscribeLink::for()). Een testmail heeft geen opgeslagen ontvangerregel
 * om een ondertekende link naar te bouwen, dus wijst zijn afmeldlink hierheen
 * in plaats van naar UnsubscribeController. Deze pagina meldt niemand af: er
 * is niemand om af te melden, en dat mag ook nooit per ongeluk gebeuren.
 */
class TestUnsubscribeController extends Controller
{
    public function __invoke()
    {
        return response()->view('dashed-newsletter::test-unsubscribed');
    }
}
