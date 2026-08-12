<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Filament\Resources\NewsletterCampaignResource\Pages;

use Dashed\DashedCore\Classes\Sites;
use Filament\Resources\Pages\CreateRecord;
use Dashed\DashedNewsletter\Filament\Resources\NewsletterCampaignResource;

class CreateNewsletterCampaign extends CreateRecord
{
    protected static string $resource = NewsletterCampaignResource::class;

    /**
     * Zelfde vorm als CreateNewsletterList: bij één site staat er geen siteveld
     * in het formulier, dus vullen we het hier.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['site_id'] = $data['site_id'] ?? Sites::getFirstSite()['id'];

        return $data;
    }
}
