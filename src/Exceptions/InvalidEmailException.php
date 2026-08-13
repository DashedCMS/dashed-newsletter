<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Exceptions;

use Illuminate\Support\Facades\Log;

/**
 * Een adres dat geen adres is. Dat is geen storing maar gewone invoer: een
 * bezoeker typt iets verkeerds in een formulier of een popup, of een oude
 * bestelling draagt een adres uit een tijd dat er nog niet op gecontroleerd
 * werd. Er is niets stuk en er valt niets te repareren.
 *
 * Erft van InvalidArgumentException zodat bestaande aanroepers die daarop
 * vangen (zoals NewsletterOrderAPI::syncEmail) blijven werken.
 *
 * De report()-methode is wat dit onderscheidt: Laravel's foutafhandelaar
 * roept die aan en slaat, zolang er niet letterlijk false uit komt, de hele
 * meldketen over. Dus geen melding naar de foutmelder en geen error in het
 * log, alleen een regel op info-niveau zodat het spoor blijft bestaan. Zonder
 * dit vulde de foutmelder zich met typefouten van bezoekers, en dan zie je de
 * echte storingen niet meer tussen de ruis.
 */
class InvalidEmailException extends \InvalidArgumentException
{
    public function report(): bool
    {
        Log::info('Nieuwsbrief: aanmelding overgeslagen, geen bruikbaar e-mailadres.', [
            'melding' => $this->getMessage(),
        ]);

        return true;
    }
}
