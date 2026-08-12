<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter;

use Filament\Panel;
use Filament\Contracts\Plugin;
use Dashed\DashedNewsletter\Filament\Resources\NewsletterListResource;
use Dashed\DashedNewsletter\Filament\Resources\NewsletterCampaignResource;
use Dashed\DashedNewsletter\Filament\Resources\NewsletterSubscriberResource;
use Dashed\DashedNewsletter\Filament\Pages\Settings\DashedNewsletterSettingsPage;

class DashedNewsletterPlugin implements Plugin
{
    public function getId(): string
    {
        return 'dashed-newsletter';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources([
                NewsletterListResource::class,
                NewsletterSubscriberResource::class,
                NewsletterCampaignResource::class,
            ])
            // Zonder deze regel bestaat de route van de instellingenpagina niet,
            // ook al staat hij wel in het instellingenoverzicht. Het overzicht
            // en het paneel zijn twee losse registraties.
            ->pages([
                DashedNewsletterSettingsPage::class,
            ]);
    }

    public function boot(Panel $panel): void
    {

    }
}
