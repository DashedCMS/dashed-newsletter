<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Filament\Resources\NewsletterSubscriberResource\Pages;

use Illuminate\Database\Eloquent\Model;
use Filament\Resources\Pages\ViewRecord;
use Dashed\DashedNewsletter\Filament\Resources\NewsletterSubscriberResource;

class ViewNewsletterSubscriber extends ViewRecord
{
    protected static string $resource = NewsletterSubscriberResource::class;

    // Zonder eager loading tikt de tijdlijn en het toestemmingsbewijs los
    // per rij door naar de database. events() staat al aflopend gesorteerd
    // (->latest()) op het model, dus hier is geen aparte sortering nodig.
    protected function resolveRecord(int | string $key): Model
    {
        return static::getResource()::getEloquentQuery()
            ->with(['events', 'consents', 'list'])
            ->findOrFail($key);
    }
}
