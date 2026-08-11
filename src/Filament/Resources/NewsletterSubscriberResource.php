<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Filament\Resources;

use UnitEnum;
use BackedEnum;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Filters\SelectFilter;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Utilities\Get;
use Dashed\DashedNewsletter\Models\NewsletterList;
use Filament\Infolists\Components\RepeatableEntry;
use Dashed\DashedNewsletter\Models\NewsletterField;
use Dashed\DashedNewsletter\Models\NewsletterSubscriber;
use Dashed\DashedNewsletter\Models\NewsletterSubscriberEvent;
use Dashed\DashedNewsletter\Filament\Resources\NewsletterSubscriberResource\Pages\EditNewsletterSubscriber;
use Dashed\DashedNewsletter\Filament\Resources\NewsletterSubscriberResource\Pages\ViewNewsletterSubscriber;
use Dashed\DashedNewsletter\Filament\Resources\NewsletterSubscriberResource\Pages\ListNewsletterSubscribers;

class NewsletterSubscriberResource extends Resource
{
    protected static ?string $model = NewsletterSubscriber::class;

    protected static ?string $recordTitleAttribute = 'email';

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-user-group';

    protected static string | UnitEnum | null $navigationGroup = 'Communicatie';

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'Contacten';

    protected static ?string $label = 'Contact';

    protected static ?string $pluralLabel = 'Contacten';

    public static function statusOptions(): array
    {
        return [
            NewsletterSubscriber::STATUS_ACTIVE => 'Actief',
            NewsletterSubscriber::STATUS_UNSUBSCRIBED => 'Uitgeschreven',
            NewsletterSubscriber::STATUS_CLEANED => 'Opgeschoond',
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['email'];
    }

    /**
     * Een contact terugzetten op actief is geen gewone veldwijziging: het
     * herstelt de aanmelding, en een actief contact hoort altijd een geldig
     * toestemmingsbewijs te hebben. Het bewijs van vóór de uitschrijving dekt
     * die heractivering niet, dus vraagt dit veld de beheerder om een korte
     * motivering die als consent_text wordt bewaard. Staat hier zodat beide
     * beheerschermen hetzelfde veld gebruiken.
     */
    public static function reactivationConsentField(): Textarea
    {
        $isReactivation = fn (Get $get, ?NewsletterSubscriber $record): bool => $record !== null
            && $record->status !== NewsletterSubscriber::STATUS_ACTIVE
            && $get('status') === NewsletterSubscriber::STATUS_ACTIVE;

        return Textarea::make('reactivation_consent_text')
            ->label('Reden van heractivering')
            ->rows(2)
            ->columnSpanFull()
            ->visible($isReactivation)
            ->required($isReactivation)
            ->helperText('Dit contact was uitgeschreven. Leg vast waarom het weer actief mag worden, bijvoorbeeld wat de klant je heeft laten weten. De tekst wordt letterlijk als nieuw toestemmingsbewijs opgeslagen.');
    }

    /**
     * De invoervelden voor de zelf gedefinieerde velden van een lijst.
     *
     * Ze heten field_<sleutel> zodat ze niet botsen met een kolom op het
     * contact, en NewsletterManager::updateFromAdmin() haalt ze er op dat
     * voorvoegsel weer uit. Zo staat het wegschrijven op één plek en doen het
     * bewerkscherm van een lijst en het losse bewerkscherm hetzelfde.
     *
     * @return array<int, mixed>
     */
    public static function fieldComponents(?NewsletterList $list): array
    {
        if (! $list) {
            return [];
        }

        return $list->fields->map(function (NewsletterField $field) {
            $name = 'field_' . $field->key;

            $component = match ($field->type) {
                NewsletterField::TYPE_NUMBER => TextInput::make($name)->numeric(),
                NewsletterField::TYPE_DATE => DatePicker::make($name),
                NewsletterField::TYPE_SELECT => Select::make($name)
                    ->options(collect($field->options ?? [])->mapWithKeys(fn ($o) => [$o => $o])->all()),
                NewsletterField::TYPE_CHECKBOX => Toggle::make($name),
                default => TextInput::make($name)->maxLength(255),
            };

            return $component
                ->label($field->label)
                ->required((bool) $field->required);
        })->all();
    }

    /**
     * Vult de bestaande veldwaarden bij de formuliergegevens.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function withFieldValues(NewsletterSubscriber $record, array $data): array
    {
        foreach ($record->fieldValues()->with('field')->get() as $value) {
            if ($value->field) {
                $data['field_' . $value->field->key] = $value->value;
            }
        }

        return $data;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Contact')->columnSpanFull()->schema([
                TextInput::make('email')
                    ->label('E-mailadres')
                    ->disabled()
                    ->helperText('Het e-mailadres is hier niet te wijzigen. Het toestemmingsbewijs (zie hieronder) hoort bij dit adres; wijzig je het hier stilletjes, dan verwijst dat bewijs naar een adres dat niet meer klopt met wat er in de kolom staat.'),
                Select::make('status')
                    ->label('Status')
                    ->options(self::statusOptions())
                    ->required()
                    ->live(),
                TextInput::make('source')
                    ->label('Bron')
                    ->maxLength(255),
                self::reactivationConsentField(),
            ])->columns(2),

            // De velden van de lijst waar dit contact op staat. Zonder deze
            // sectie kon je ze op dit scherm niet zien en niet wijzigen.
            Section::make('Velden')
                ->columnSpanFull()
                ->visible(fn (?NewsletterSubscriber $record): bool => ($record?->list?->fields()->exists()) ?? false)
                ->schema(fn (?NewsletterSubscriber $record): array => self::fieldComponents($record?->list))
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('email')->label('E-mailadres')->searchable(),
                TextColumn::make('list.name')->label('Lijst')->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => self::statusOptions()[$state] ?? $state),
                TextColumn::make('source')->label('Bron'),
                TextColumn::make('subscribed_at')->label('Aangemeld op')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('newsletter_list_id')
                    ->label('Lijst')
                    ->relationship('list', 'name'),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(self::statusOptions()),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('subscribed_at', 'desc');
    }

    private static function eventTypeLabels(): array
    {
        return [
            NewsletterSubscriberEvent::TYPE_SUBSCRIBED => 'Aangemeld',
            NewsletterSubscriberEvent::TYPE_CONFIRMED => 'Bevestigd',
            NewsletterSubscriberEvent::TYPE_UPDATED => 'Bijgewerkt',
            NewsletterSubscriberEvent::TYPE_UNSUBSCRIBED => 'Uitgeschreven',
            NewsletterSubscriberEvent::TYPE_IMPORTED => 'Geïmporteerd',
            NewsletterSubscriberEvent::TYPE_CLEANED => 'Opgeschoond',
        ];
    }

    /**
     * Eén regel toelichting per gebeurtenis in de tijdlijn. De payload verschilt
     * per soort: een aanmelding draagt 'source', een statuswijziging 'from' en
     * 'to', en een bronwijziging daarnaast 'field'. Eén vaste sleutel uitlezen
     * liet daardoor bij de helft van de regels een streepje zien terwijl de
     * gegevens er wel degelijk stonden.
     *
     * @param array<string, mixed>|null $payload
     */
    public static function eventDescription(?array $payload): string
    {
        $payload ??= [];
        $from = $payload['from'] ?? null;
        $to = $payload['to'] ?? null;
        $source = $payload['source'] ?? null;

        if (($payload['field'] ?? null) === 'source') {
            return 'Bron gewijzigd van ' . self::orDash($from) . ' naar ' . self::orDash($to);
        }

        if ($from !== null || $to !== null) {
            $line = 'Status van ' . self::orDash(self::statusOptions()[$from] ?? $from)
                . ' naar ' . self::orDash(self::statusOptions()[$to] ?? $to);

            return filled($source) ? $line . ' (bron: ' . $source . ')' : $line;
        }

        return filled($source) ? 'Bron: ' . $source : '-';
    }

    private static function orDash(mixed $value): string
    {
        return filled($value) ? (string) $value : '-';
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Contact')->columnSpanFull()->schema([
                TextEntry::make('email')->label('E-mailadres'),
                TextEntry::make('list.name')->label('Lijst'),
                TextEntry::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => self::statusOptions()[$state] ?? $state),
                TextEntry::make('source')->label('Bron')->default('-'),
                TextEntry::make('subscribed_at')->label('Aangemeld op')->dateTime(),
                TextEntry::make('unsubscribed_at')->label('Uitgeschreven op')->dateTime()->placeholder('-'),
            ])->columns(3),

            // Zonder deze sectie is een veldwaarde nergens in het beheer te zien
            // en lijkt een import de gegevens te hebben laten liggen, terwijl ze
            // er wel staan. Lege waarden krijgen een streepje in plaats van te
            // verdwijnen: het verschil tussen "leeg" en "niet gevraagd" is hier
            // precies wat je wilt zien.
            Section::make('Velden')
                ->columnSpanFull()
                ->visible(fn (NewsletterSubscriber $record): bool => $record->list?->fields()->exists() ?? false)
                ->schema([
                    RepeatableEntry::make('fieldValues')
                        ->label('')
                        ->columnSpanFull()
                        ->schema([
                            TextEntry::make('field.label')->label('Veld'),
                            TextEntry::make('value')->label('Waarde')->placeholder('-'),
                        ])
                        ->columns(2),
                ]),

            // events() staat al aflopend gesorteerd op het model: de
            // nieuwste gebeurtenis staat bovenaan de tijdlijn.
            Section::make('Tijdlijn')
                ->columnSpanFull()
                ->schema([
                    RepeatableEntry::make('events')
                        ->label('')
                        ->columnSpanFull()
                        ->schema([
                            TextEntry::make('type')
                                ->label('Type')
                                ->formatStateUsing(fn (string $state) => self::eventTypeLabels()[$state] ?? $state),
                            TextEntry::make('payload')
                                ->label('Toelichting')
                                ->getStateUsing(fn ($record) => self::eventDescription($record->payload)),
                            TextEntry::make('created_at')->label('Tijdstip')->dateTime(),
                        ])
                        ->columns(3),
                ]),

            Section::make('Toestemmingsbewijs')
                ->columnSpanFull()
                ->schema([
                    RepeatableEntry::make('consents')
                        ->label('')
                        ->columnSpanFull()
                        ->schema([
                            TextEntry::make('given_at')->label('Tijdstip')->dateTime(),
                            TextEntry::make('ip')->label('IP')->default('-'),
                            TextEntry::make('source')->label('Bron')->default('-'),
                            TextEntry::make('consent_text')->label('Toestemmingstekst')->default('-')->columnSpanFull(),
                        ])
                        ->columns(3),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNewsletterSubscribers::route('/'),
            'edit' => EditNewsletterSubscriber::route('/{record}/edit'),
            'view' => ViewNewsletterSubscriber::route('/{record}'),
        ];
    }
}
