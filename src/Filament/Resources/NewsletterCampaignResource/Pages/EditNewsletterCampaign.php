<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Filament\Resources\NewsletterCampaignResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Dashed\DashedNewsletter\Filament\Resources\NewsletterCampaignResource;

class EditNewsletterCampaign extends EditRecord
{
    protected static string $resource = NewsletterCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
