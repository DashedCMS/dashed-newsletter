<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Filament\Resources\NewsletterListResource\RelationManagers;

use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Dashed\DashedNewsletter\Models\NewsletterField;
use Dashed\DashedNewsletter\Models\NewsletterFieldValue;
use Filament\Resources\RelationManagers\RelationManager;

class FieldsRelationManager extends RelationManager
{
    protected static string $relationship = 'fields';

    protected static ?string $title = 'Velden';

    /**
     * De velden waar vrijwel elke nieuwsbrief mee begint.
     *
     * Het e-mailadres staat er bewust niet bij. Dat is bij ons geen zelf
     * gedefinieerd veld maar een kolom op het contact, met een unieke sleutel
     * per lijst. Zou je het hier als veld aanmaken, dan komt er een tweede adres
     * naast te staan dat door geen enkele aanmeldweg wordt bijgewerkt, en dan
     * segmenteer je later op het verkeerde.
     *
     * @return array<int, array{key: string, label: string}>
     */
    private static function defaultFields(): array
    {
        return [
            ['key' => 'voornaam', 'label' => 'Voornaam'],
            ['key' => 'achternaam', 'label' => 'Achternaam'],
        ];
    }

    private static function typeOptions(): array
    {
        return [
            NewsletterField::TYPE_TEXT => 'Tekst',
            NewsletterField::TYPE_NUMBER => 'Getal',
            NewsletterField::TYPE_DATE => 'Datum',
            NewsletterField::TYPE_SELECT => 'Selectie',
            NewsletterField::TYPE_CHECKBOX => 'Checkbox',
        ];
    }

    private static function hasValues(?NewsletterField $record): bool
    {
        if (! $record) {
            return false;
        }

        return NewsletterFieldValue::where('newsletter_field_id', $record->id)->exists();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('label')
                ->label(__('Label'))
                ->required()
                ->maxLength(255),
            TextInput::make('key')
                ->label(__('Sleutel'))
                ->required()
                ->maxLength(255)
                ->helperText(fn (Get $get) => __('Relatievariabele in een campagne: :variabele', ['variabele' => ':' . ($get('key') ?: __('sleutel')) . ':'])),
            Select::make('type')
                ->label(__('Type'))
                ->options(self::typeOptions())
                ->required()
                ->live()
                ->default(NewsletterField::TYPE_TEXT)
                ->disabled(fn (?NewsletterField $record) => self::hasValues($record))
                ->helperText(fn (?NewsletterField $record) => self::hasValues($record)
                    ? __('Dit veld heeft al ingevulde waarden. Het type kan niet meer gewijzigd worden, anders blijven value_number en value_date op de oude leest staan en gaat segmentatie stil verkeerd.')
                    : null),
            Toggle::make('required')
                ->label(__('Verplicht')),
            TagsInput::make('options')
                ->label(__('Opties'))
                ->helperText(__('Alleen van toepassing bij het type Selectie.'))
                ->visible(fn (Get $get) => $get('type') === NewsletterField::TYPE_SELECT),
            TextInput::make('default_value')
                ->label(__('Standaardwaarde'))
                ->maxLength(255),
            Toggle::make('show_in_signup_form')
                ->label(__('Tonen in aanmeldformulier'))
                ->default(true),
            TextInput::make('sort')
                ->label(__('Sortering'))
                ->numeric()
                ->default(0),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label')->label(__('Label'))->searchable(),
                TextColumn::make('key')->label(__('Sleutel'))->searchable(),
                TextColumn::make('type')
                    ->label(__('Type'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => self::typeOptions()[$state] ?? $state),
                IconColumn::make('required')->label(__('Verplicht'))->boolean(),
            ])
            ->headerActions([
                CreateAction::make(),
                // Alleen zichtbaar zolang de lijst nog leeg is. Daarna zou de
                // knop of niets doen of iets terugzetten wat iemand net bewust
                // heeft weggehaald.
                Action::make('createDefaultFields')
                    ->label(__('Standaardvelden aanmaken'))
                    ->icon('heroicon-o-sparkles')
                    ->visible(fn (): bool => $this->getOwnerRecord()->fields()->doesntExist())
                    ->requiresConfirmation()
                    ->modalHeading(__('Standaardvelden aanmaken'))
                    ->modalDescription(__('Dit maakt de velden Voornaam en Achternaam aan. Het e-mailadres staat al op het contact zelf en is geen apart veld.'))
                    ->modalSubmitActionLabel(__('Aanmaken'))
                    ->action(function (): void {
                        foreach (self::defaultFields() as $sort => $field) {
                            $this->getOwnerRecord()->fields()->firstOrCreate(
                                ['key' => $field['key']],
                                [
                                    'label' => $field['label'],
                                    'type' => NewsletterField::TYPE_TEXT,
                                    'sort' => $sort,
                                ]
                            );
                        }

                        Notification::make()
                            ->title(__('Standaardvelden aangemaakt'))
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort');
    }
}
