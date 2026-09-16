<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Exceptions;

use Illuminate\Support\Facades\Log;
use Dashed\DashedNewsletter\Models\NewsletterSuppression;

/**
 * Een adres dat op de blokkadelijst staat en dus nooit op een lijst mag
 * komen, langs welke weg dan ook. Erft van InvalidEmailException zodat elke
 * aanroeper die een typefout al netjes afhandelt (formulier, popup, app,
 * handmatig, bestelling) dit ook afhandelt: het is voor hen hetzelfde
 * geval, een adres dat niet ingeschreven wordt. Geen melding naar de
 * foutmelder, wel een regel in het log zodat het spoor blijft.
 */
class SuppressedEmailException extends InvalidEmailException
{
    public static function for(NewsletterSuppression $suppression): self
    {
        return new self(sprintf(
            'Dit adres staat op de blokkadelijst (%s) en wordt niet ingeschreven.',
            NewsletterSuppression::reasonLabel((string) $suppression->reason),
        ));
    }

    public function report(): bool
    {
        Log::info('Nieuwsbrief: aanmelding geweigerd, adres staat op de blokkadelijst.', [
            'melding' => $this->getMessage(),
        ]);

        return true;
    }
}
