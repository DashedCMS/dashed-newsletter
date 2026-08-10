<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Segments\Exceptions;

class EmptySegmentException extends InvalidSegmentException
{
    public static function forSegment(string $name): self
    {
        return new self(
            'Het segment "' . $name . '" bevat geen enkele voorwaarde en kan daarom niet ' .
            'worden uitgerekend: zonder voorwaarden zou het de hele lijst opleveren, en ' .
            'dan gaat er straks een mail naar iedereen in plaats van naar de bedoelde ' .
            'selectie. Voeg minstens één voorwaarde toe aan dit segment.'
        );
    }
}
