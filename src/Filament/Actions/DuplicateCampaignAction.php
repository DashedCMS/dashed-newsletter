<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Filament\Actions;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Dashed\DashedNewsletter\Models\NewsletterCampaign;
use Dashed\DashedNewsletter\Filament\Resources\NewsletterCampaignResource;

/**
 * Dupliceren. De schone lei zit in NewsletterCampaign::duplicate(); deze knop
 * doet niets anders dan hem aanroepen en je naar de kopie sturen.
 */
class DuplicateCampaignAction
{
    public static function make(): Action
    {
        return Action::make('duplicateCampaign')
            ->label(__('Dupliceren'))
            ->icon('heroicon-o-document-duplicate')
            ->color('gray')
            ->modalHeading(__('Campagne dupliceren'))
            ->modalDescription(__('De kopie krijgt dezelfde inhoud, lijst en afzender, en begint als concept. Er gaan geen ontvangers en geen cijfers mee.'))
            ->modalSubmitActionLabel(__('Dupliceren'))
            ->requiresConfirmation()
            ->action(function (NewsletterCampaign $record) {
                $kopie = $record->duplicate();

                Notification::make()
                    ->title(__('Kopie gemaakt'))
                    ->body(__('Je werkt nu in de kopie. Het origineel is ongewijzigd.'))
                    ->success()
                    ->send();

                return redirect(NewsletterCampaignResource::getUrl('edit', ['record' => $kopie]));
            });
    }
}
