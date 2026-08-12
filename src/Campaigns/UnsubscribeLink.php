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
        return URL::signedRoute('dashed.frontend.newsletter.unsubscribe', [
            'recipient' => $recipient->id,
        ]);
    }
}
