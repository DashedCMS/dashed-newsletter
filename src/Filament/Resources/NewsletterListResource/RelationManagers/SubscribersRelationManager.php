<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Filament\Resources\NewsletterListResource\RelationManagers;

use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Support\Exceptions\Halt;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Filters\SelectFilter;
use Dashed\DashedNewsletter\Facades\Newsletter;
use Dashed\DashedNewsletter\Models\NewsletterSubscriber;
use Filament\Resources\RelationManagers\RelationManager;
use Dashed\DashedNewsletter\Filament\Resources\NewsletterSubscriberResource;

class SubscribersRelationManager extends RelationManager
{
    protected static string $relationship = 'subscribers';

    protected static ?string $title = 'Contacten';

    private static function statusOptions(): array
    {
        return [
            NewsletterSubscriber::STATUS_ACTIVE => 'Actief',
            NewsletterSubscriber::STATUS_UNSUBSCRIBED => 'Uitgeschreven',
            NewsletterSubscriber::STATUS_CLEANED => 'Opgeschoond',
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            // Op slot om dezelfde reden als in NewsletterSubscriberResource: het
            // toestemmingsbewijs hoort bij dit adres. Zonder dit slot leidde een
            // adreswijziging hier bovendien tot een verlopen accountkoppeling
            // (de user_id-hook hangt alleen aan 'creating') en tot een
            // onafgevangen QueryException op de unique index zodra het nieuwe
            // adres al op deze lijst stond.
            TextInput::make('email')
                ->label('E-mailadres')
                ->disabled()
                ->helperText('Het e-mailadres is hier niet te wijzigen. Het toestemmingsbewijs hoort bij dit adres; wijzig je het hier stilletjes, dan verwijst dat bewijs naar een adres dat niet meer klopt met wat er in de kolom staat.'),
            Select::make('status')
                ->label('Status')
                ->options(self::statusOptions())
                ->default(NewsletterSubscriber::STATUS_ACTIVE)
                ->required()
                ->live(),
            TextInput::make('source')
                ->label('Bron')
                ->maxLength(255),
            // Zelfde veld als op het losse bewerkscherm: heractiveren vraagt om
            // een nieuw toestemmingsbewijs, ongeacht via welk scherm het gaat.
            NewsletterSubscriberResource::reactivationConsentField(),
        ]);
    }

    /**
     * Een kolom per veld van deze lijst, standaard verborgen.
     *
     * De velden verschillen per lijst, dus dit kan niet vast in de tabel staan.
     * Verborgen beginnen omdat een lijst met tien velden anders een onleesbare
     * tabel oplevert; via de kolomkiezer haal je erbij wat je nodig hebt.
     *
     * @return array<int, TextColumn>
     */
    private function fieldColumns(): array
    {
        return $this->getOwnerRecord()->fields->map(
            fn ($field): TextColumn => TextColumn::make('field_' . $field->key)
                ->label($field->label)
                ->toggleable(isToggledHiddenByDefault: true)
                ->placeholder('-')
                ->state(fn (NewsletterSubscriber $record) => $record->fieldValues
                    ->firstWhere('newsletter_field_id', $field->id)?->value)
        )->all();
    }

    public function table(Table $table): Table
    {
        return $table
            // De veldkolommen lezen per rij hun waarden, dus die eager loaden;
            // anders staat er een query per contact per pagina.
            ->modifyQueryUsing(fn ($query) => $query->with('fieldValues'))
            ->columns([
                TextColumn::make('email')->label('E-mailadres')->searchable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => self::statusOptions()[$state] ?? $state),
                TextColumn::make('source')->label('Bron'),
                ...$this->fieldColumns(),
                TextColumn::make('subscribed_at')->label('Aangemeld op')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(self::statusOptions()),
            ])
            ->headerActions([
                // Gaat bewust niet via een gewone model-create: alleen
                // Newsletter::subscribe() legt naast het contact ook de
                // subscribed-gebeurtenis en het toestemmingsbewijs vast. Zonder
                // die twee kan een handmatig toegevoegd contact niet aantonen
                // waarom het op de lijst staat.
                CreateAction::make()
                    ->label('Nieuw contact')
                    ->schema([
                        // Bewust geen ->email(): Newsletter::subscribe() is de
                        // enige plek die bepaalt of een adres geldig is, dus
                        // een ongeldig adres moet hier doorheen komen en pas
                        // daar worden afgewezen (zie de catch hieronder).
                        TextInput::make('email')
                            ->label('E-mailadres')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('consent_text')
                            ->label('Toestemmingstekst')
                            ->rows(2)
                            ->helperText('Wat als bewijs van toestemming bewaard wordt, bijvoorbeeld wat de beheerder de klant heeft horen of zien bevestigen. Laat je het leeg, dan wordt de toestemming zelf nog steeds vastgelegd met tijdstip en bron, alleen zonder tekst erbij.'),
                    ])
                    ->using(function (array $data): NewsletterSubscriber {
                        try {
                            return Newsletter::subscribe(
                                email: $data['email'],
                                list: $this->getOwnerRecord(),
                                source: 'handmatig',
                                consentText: $data['consent_text'],
                            );
                        } catch (\InvalidArgumentException $e) {
                            Notification::make()
                                ->title('Contact kon niet worden toegevoegd')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();

                            throw new Halt();
                        }
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    // Alles wat er bij een bewerking komt kijken (e-mailslot,
                    // bron-gebeurtenis, statusovergang met tijdlijn,
                    // unsubscribed_at en toestemmingsbewijs) staat in
                    // Newsletter::updateFromAdmin(), zodat dit scherm en
                    // EditNewsletterSubscriber niet uit elkaar kunnen lopen.
                    ->using(fn (NewsletterSubscriber $record, array $data): NewsletterSubscriber => Newsletter::updateFromAdmin($record, $data)),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('subscribed_at', 'desc');
    }
}
