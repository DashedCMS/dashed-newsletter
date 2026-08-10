<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Filament\Resources\NewsletterListResource\Pages;

use Dashed\DashedCore\Classes\Sites;
use Filament\Resources\Pages\CreateRecord;
use Dashed\DashedNewsletter\Filament\Resources\NewsletterListResource;

class CreateNewsletterList extends CreateRecord
{
    protected static string $resource = NewsletterListResource::class;

    /**
     * Bij één site staat het siteveld verborgen en komt er dus niets uit het
     * formulier. Een lijst zonder site_id valt buiten forSite() en is daarmee
     * onvindbaar voor alles wat per site werkt, dus vullen we hem hier. Zelfde
     * vorm als CreateAutomationRule en CreateGiftcard in dashed-ecommerce-core.
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
