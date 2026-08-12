<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Campaigns;

use Dashed\DashedNewsletter\Segments\SegmentQuery;
use Dashed\DashedNewsletter\Models\NewsletterCampaign;
use Dashed\DashedNewsletter\Segments\Exceptions\InvalidSegmentException;
use Dashed\DashedNewsletter\Campaigns\Exceptions\CampaignNotSendableException;

/**
 * Waarom een campagne niet verzonden mag worden, of null als er niets aan de
 * hand is. Bewust luid: hier is de prijs van doorgaan een mail die naar de
 * verkeerde mensen gaat, en die krijg je niet terug.
 */
class CampaignGuard
{
    public static function problem(NewsletterCampaign $campaign): ?string
    {
        if (in_array($campaign->status, [NewsletterCampaign::STATUS_SENT, NewsletterCampaign::STATUS_SENDING], true)) {
            return 'Deze campagne is al verzonden of is op dit moment aan het verzenden.';
        }

        if (blank($campaign->subject)) {
            return 'De campagne heeft geen onderwerp.';
        }

        if (blank($campaign->content)) {
            return 'De campagne heeft geen inhoud.';
        }

        if (blank($campaign->effectiveFromEmail())) {
            return 'Er is geen afzenderadres: niet op de campagne, niet op de lijst en niet bij de algemene instellingen.';
        }

        if ($campaign->segment) {
            // De uitzondering van SegmentQuery hier bewust niet wegslikken zoals
            // het beheerscherm dat doet: daar is een onbruikbaar segment een
            // weergaveprobleem, hier is het het verschil tussen niemand en
            // iedereen mailen.
            try {
                SegmentQuery::for($campaign->segment);
            } catch (InvalidSegmentException $e) {
                return 'Het gekozen segment is niet bruikbaar: ' . $e->getMessage();
            }
        }

        return null;
    }

    public static function assertSendable(NewsletterCampaign $campaign): void
    {
        $problem = static::problem($campaign);

        if ($problem !== null) {
            throw new CampaignNotSendableException($problem);
        }
    }
}
