<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Filament\Resources\NewsletterSubscriberResource\Pages;

use Filament\Actions\DeleteAction;
use Illuminate\Database\Eloquent\Model;
use Filament\Resources\Pages\EditRecord;
use Dashed\DashedNewsletter\Facades\Newsletter;
use Dashed\DashedNewsletter\Filament\Resources\NewsletterSubscriberResource;

class EditNewsletterSubscriber extends EditRecord
{
    protected static string $resource = NewsletterSubscriberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * De veldwaarden zijn geen kolom op het contact, dus die moeten er met de
     * hand bij voordat het formulier gevuld wordt. Zonder dit staan de velden
     * er wel maar zijn ze leeg, wat erger is dan ze niet tonen.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return NewsletterSubscriberResource::withFieldValues($this->getRecord(), $data);
    }

    // Alles wat er bij een bewerking komt kijken (e-mailslot, bron-gebeurtenis,
    // statusovergang met tijdlijn, unsubscribed_at en toestemmingsbewijs) staat
    // in Newsletter::updateFromAdmin(). Deze logica stond eerder letterlijk
    // overgeschreven in SubscribersRelationManager en was al uit elkaar gelopen.
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return Newsletter::updateFromAdmin($record, $data);
    }
}
