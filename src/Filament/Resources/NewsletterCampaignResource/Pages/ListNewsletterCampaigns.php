<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Filament\Resources\NewsletterCampaignResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Dashed\DashedNewsletter\Filament\Resources\NewsletterCampaignResource;

class ListNewsletterCampaigns extends ListRecords
{
    protected static string $resource = NewsletterCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
