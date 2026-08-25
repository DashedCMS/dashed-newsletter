<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Ai\Exceptions;

use RuntimeException;

/**
 * Een soort fout voor beide fasen. De reden staat in de boodschap en gaat
 * ongewijzigd naar de melding op het scherm: de redacteur moet kunnen zien wat
 * er misging, niet dat er iets misging.
 *
 * Alles of niets: wie deze fout vangt laat de campagne staan zoals hij was.
 */
class AiGenerationFailedException extends RuntimeException
{
}
