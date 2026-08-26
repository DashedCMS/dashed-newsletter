<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Filament\Resources\NewsletterCampaignResource\RelationManagers;

use Filament\Tables\Table;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Resources\RelationManagers\RelationManager;
use Dashed\DashedNewsletter\Models\NewsletterCampaignRecipient;

/**
 * Wie wat kreeg, en waarom niet.
 *
 * Geen bewerkacties: dit is een logboek van een verzending die geweest is. Wat
 * hier staat hoort te blijven kloppen met wat er werkelijk gebeurd is.
 */
class RecipientsRelationManager extends RelationManager
{
    protected static string $relationship = 'recipients';

    protected static ?string $title = 'Ontvangers';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('email')->label('Adres')->searchable()->sortable(),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (string $state): string => self::statusLabels()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        NewsletterCampaignRecipient::STATUS_SENT => 'success',
                        NewsletterCampaignRecipient::STATUS_FAILED => 'danger',
                        NewsletterCampaignRecipient::STATUS_SKIPPED => 'warning',
                        default => 'gray',
                    }),
                // Twee redenen in een kolom: skip_reason draagt zowel de reden
                // van overslaan als de foutmelding bij mislukken (zie
                // CampaignSender), en een bounce heeft zijn eigen reden. Voor
                // wie dit scherm opent is het een vraag: waarom kreeg deze
                // persoon niets.
                TextColumn::make('reden')->label('Reden')->wrap()->placeholder('-')
                    ->state(fn (NewsletterCampaignRecipient $record): ?string => $record->bounce_reason
                        ?: (self::skipLabels()[$record->skip_reason] ?? $record->skip_reason)),
                TextColumn::make('sent_at')->label('Verzonden')->dateTime()->placeholder('-')->sortable(),
                TextColumn::make('delivered_at')->label('Bezorgd')->dateTime()->placeholder('-')->sortable(),
                TextColumn::make('opened_at')->label('Geopend')->dateTime()->placeholder('-')->sortable()
                    ->description(fn (NewsletterCampaignRecipient $record): ?string => $record->open_count > 1
                        ? $record->open_count . ' keer'
                        : null),
                TextColumn::make('clicked_at')->label('Geklikt')->dateTime()->placeholder('-')->sortable()
                    ->description(fn (NewsletterCampaignRecipient $record): ?string => $record->click_count > 1
                        ? $record->click_count . ' keer'
                        : null),
                IconColumn::make('unsubscribed_at')->label('Afgemeld')->boolean()
                    ->state(fn (NewsletterCampaignRecipient $record): bool => $record->unsubscribed_at !== null),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(self::statusLabels()),
            ])
            ->defaultSort('email')
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }

    /** @return array<string, string> */
    public static function statusLabels(): array
    {
        return [
            NewsletterCampaignRecipient::STATUS_PENDING => 'In de wachtrij',
            NewsletterCampaignRecipient::STATUS_SENDING => 'Bezig',
            NewsletterCampaignRecipient::STATUS_SENT => 'Verzonden',
            NewsletterCampaignRecipient::STATUS_SKIPPED => 'Overgeslagen',
            NewsletterCampaignRecipient::STATUS_FAILED => 'Mislukt',
            NewsletterCampaignRecipient::STATUS_INTERRUPTED => 'Onderbroken',
        ];
    }

    /** @return array<string, string> */
    public static function skipLabels(): array
    {
        return [
            NewsletterCampaignRecipient::SKIP_UNSUBSCRIBED => 'Uitgeschreven',
            NewsletterCampaignRecipient::SKIP_SUPPRESSED => 'Geblokkeerd',
            NewsletterCampaignRecipient::SKIP_INVALID_EMAIL => 'Ongeldig adres',
            NewsletterCampaignRecipient::SKIP_CANCELLED => 'Campagne afgebroken',
        ];
    }
}
