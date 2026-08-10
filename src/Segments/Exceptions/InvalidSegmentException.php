<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Segments\Exceptions;

use RuntimeException;

/**
 * Gemeenschappelijke basis voor alles wat een segment onberekenbaar maakt.
 * Schermen die een segment uitrekenen vangen dit type af en tonen "Ongeldig
 * segment"; door één basis te hebben kan er geen nieuwe reden bijkomen die
 * langs die afvang heen glipt en de pagina laat crashen.
 */
class InvalidSegmentException extends RuntimeException
{
}
