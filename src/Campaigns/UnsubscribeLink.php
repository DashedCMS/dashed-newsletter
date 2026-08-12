<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Campaigns;

use Illuminate\Support\Facades\URL;
use Dashed\DashedNewsletter\Models\NewsletterCampaignRecipient;

/**
 * De persoonlijke afmeldlink in een campagnemail.
 *
 * Ondertekend, zodat niemand met een ander id in de URL een willekeurig contact
 * kan uitschrijven. Zelfde patroon als de afmeldlink bij verlaten winkelwagens.
 */
class UnsubscribeLink
{
    public static function for(NewsletterCampaignRecipient $recipient): string
    {
        // EditNewsletterCampaign::sendTest() bouwt voor een proefmail een
        // ontvanger die nooit is opgeslagen (expliciet, zie de toelichting
        // daar), dus $recipient->exists is dan false. Zo'n regel heeft geen
        // echt id om een ondertekende link naar te bouwen; de vorige poging
        // gaf hem hardcoded id 0 mee, en dat wees altijd naar een 404 bij
        // UnsubscribeController. Een testmail wijst daarom naar een aparte
        // pagina die uitlegt dat er niets is afgemeld, in plaats van naar de
        // echte afmeldroute.
        if (! $recipient->exists) {
            return URL::route('dashed.frontend.newsletter.unsubscribe-test');
        }

        return URL::signedRoute('dashed.frontend.newsletter.unsubscribe', [
            'recipient' => $recipient->id,
        ]);
    }
}
