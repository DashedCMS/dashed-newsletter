<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Filament\Resources\NewsletterSubscriberResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Dashed\DashedNewsletter\Filament\Resources\NewsletterSubscriberResource;

class ListNewsletterSubscribers extends ListRecords
{
    protected static string $resource = NewsletterSubscriberResource::class;
}
