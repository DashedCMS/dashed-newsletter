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
    /**
     * Naar de bewerkpagina en niet naar de bekijkpagina.
     *
     * Filament kijkt in CreateRecord::getRedirectUrl() eerst of er een
     * view-pagina bestaat en pas daarna naar edit. Sinds de bekijkpagina er is
     * kwam je na het aanmaken dus op een scherm dat de verstuurde mail en de
     * cijfers toont, en die zijn er bij een vers concept allebei niet. Wie net
     * een campagne aanmaakt wil beginnen met schrijven.
     */
    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['site_id'] = $data['site_id'] ?? Sites::getFirstSite()['id'];

        return $data;
    }
}
