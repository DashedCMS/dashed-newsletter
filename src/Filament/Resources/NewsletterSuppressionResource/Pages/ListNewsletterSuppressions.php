<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Filament\Resources\NewsletterSuppressionResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Dashed\DashedNewsletter\Filament\Resources\NewsletterSuppressionResource;

class ListNewsletterSuppressions extends ListRecords
{
    protected static string $resource = NewsletterSuppressionResource::class;
}
