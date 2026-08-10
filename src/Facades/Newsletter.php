<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Facades;

use Illuminate\Support\Facades\Facade;

class Newsletter extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'newsletter';
    }
}
