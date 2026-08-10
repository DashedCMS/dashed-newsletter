<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Segments\Exceptions;

class UnknownSegmentConditionException extends InvalidSegmentException
{
    public static function forKey(string $key): self
    {
        return new self(
            'Onbekende segmentconditie "' . $key . '". Het segment kan niet worden ' .
            'uitgerekend zolang die conditie ontbreekt. Waarschijnlijk is het package ' .
            'dat deze conditie levert niet meer geinstalleerd.'
        );
    }
}
