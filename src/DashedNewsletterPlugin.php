<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter;

use Filament\Panel;
use Filament\Contracts\Plugin;
use Dashed\DashedNewsletter\Filament\Resources\NewsletterListResource;
use Dashed\DashedNewsletter\Filament\Resources\NewsletterSubscriberResource;

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
            ]);
    }

    public function boot(Panel $panel): void
    {

    }
}
