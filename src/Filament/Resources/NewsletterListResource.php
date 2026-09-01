<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Filament\Resources;

use UnitEnum;
use BackedEnum;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Actions\DeleteAction;
use Dashed\DashedCore\Classes\Sites;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Builder;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\ColorPicker;
use Dashed\DashedCore\Models\Customsetting;
use Filament\Infolists\Components\ViewEntry;
use Dashed\DashedNewsletter\Models\NewsletterList;
use Dashed\DashedNewsletter\Campaigns\UnsubscribeReasons;
use Dashed\DashedNewsletter\Models\NewsletterCampaignRecipient;
use Dashed\DashedNewsletter\Filament\Resources\NewsletterListResource\Pages\EditNewsletterList;
use Dashed\DashedNewsletter\Filament\Resources\NewsletterListResource\Pages\ListNewsletterLists;
use Dashed\DashedNewsletter\Filament\Resources\NewsletterListResource\Pages\CreateNewsletterList;
use Dashed\DashedNewsletter\Filament\Resources\NewsletterListResource\RelationManagers\FieldsRelationManager;
use Dashed\DashedNewsletter\Filament\Resources\NewsletterListResource\RelationManagers\SegmentsRelationManager;
use Dashed\DashedNewsletter\Filament\Resources\NewsletterListResource\RelationManagers\SubscribersRelationManager;

class NewsletterListResource extends Resource
{
    protected static ?string $model = NewsletterList::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-envelope-open';

    protected static string | UnitEnum | null $navigationGroup = 'Communicatie';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Nieuwsbrieflijsten';

    protected static ?string $label = 'Nieuwsbrieflijst';

    protected static ?string $pluralLabel = 'Nieuwsbrieflijsten';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Lijst')->columnSpanFull()->schema([
                // Zelfde vorm als de andere resources in het CMS: bij één site
                // is dit veld verborgen en vult CreateNewsletterList de waarde
                // in. Het beheer toont bewust alle sites, zoals overal hier; de
                // site bepaalt van wie de lijst is, niet wie hem mag zien.
                Select::make('site_id')
                    ->label('Actief op site')
                    ->options(collect(Sites::getSites())->pluck('name', 'id')->toArray())
                    ->default(fn () => Sites::getFirstSite()['id'])
                    ->required()
                    ->hidden(fn (): bool => ! (Sites::getAmountOfSites() > 1)),
                TextInput::make('name')->label('Naam')->required()->maxLength(255),
                Select::make('locale')
                    ->label('Taal')
                    ->options(['nl' => 'Nederlands', 'en' => 'Engels', 'de' => 'Duits']),
                TextInput::make('from_name')->label('Afzendernaam')->maxLength(255),
                TextInput::make('from_email')
                    ->label('Afzenderadres')
                    ->email()
                    ->placeholder(fn (): ?string => Customsetting::get('site_from_email', Sites::getActive()) ?: config('mail.from.address'))
                    ->helperText('Laat leeg om het adres uit de algemene instellingen te gebruiken.'),
                TextInput::make('reply_to_email')->label('Antwoordadres')->email(),
                Toggle::make('notify_on_subscribe')->label('Melding bij aanmelding'),
                Toggle::make('notify_on_unsubscribe')->label('Melding bij afmelding'),
                TextInput::make('send_rate_per_minute')
                    ->label('Verzendtempo')
                    ->numeric()
                    ->minValue(0)
                    ->suffix('mails per minuut')
                    ->placeholder('Volg de site-instelling (' . (int) config('dashed-newsletter.send_rate_per_minute', 0) . ')')
                    ->helperText('Een campagne wordt in porties over de tijd uitgesmeerd. Leeg laten volgt de instelling van de site; 0 betekent geen begrenzing. Spreiden beschermt je bezorgbaarheid: duizenden mails in een ruk vanaf een domein dat normaal weinig verstuurt, is precies het patroon waar spamfilters op letten.'),
            ])->columns(2),
            // Alleen op een bestaande lijst: bij een nieuwe valt er niets te
            // tellen, en een lege tabel op een aanmaakscherm is ruis.
            Section::make('Waarom mensen zich afmeldden')
                ->columnSpanFull()
                ->collapsible()
                ->collapsed()
                ->visible(fn (?NewsletterList $record): bool => $record !== null)
                ->schema([
                    ViewEntry::make('afmeldredenen')
                        ->view('dashed-newsletter::filament.unsubscribe-reasons')
                        ->viewData(fn (?NewsletterList $record): array => [
                            'totaal' => $record ? UnsubscribeReasons::total(list: $record) : 0,
                            'zonderReden' => $record ? UnsubscribeReasons::withoutReason(list: $record) : 0,
                            'redenen' => $record ? UnsubscribeReasons::totals(list: $record) : [],
                            'toelichtingen' => $record ? UnsubscribeReasons::comments(list: $record) : [],
                            'omschrijvingen' => NewsletterCampaignRecipient::unsubscribeReasons(),
                        ]),
                ]),

            Section::make('Vormgeving van de mail')
                ->description('Laat leeg om de instellingen van de site aan te houden. De header staat boven elke campagne van deze lijst, de footer eronder.')
                ->collapsed()
                ->schema([
                    mediaHelper()->field('mail_logo', 'Logo', false, false, true)
                        ->helperText('Laat leeg om het logo uit de e-mailinstellingen te gebruiken.'),
                    ColorPicker::make('mail_primary_color')->label('Primaire kleur'),
                    ColorPicker::make('mail_text_color')->label('Tekstkleur op primaire kleur'),
                    ColorPicker::make('mail_background_color')->label('Achtergrondkleur'),
                    Toggle::make('track_opens')
                        ->label('Openen meten')
                        ->default(true)
                        ->helperText('Zet een onzichtbaar plaatje in de mail. Let op: Apple Mail haalt dat standaard op zonder dat iemand kijkt, dus openingspercentages vallen structureel te hoog uit.'),
                    Toggle::make('track_clicks')
                        ->label('Klikken meten')
                        ->default(true)
                        ->helperText('Stuurt de links in de mail via deze website, zodat je ziet waarop geklikt wordt. De ontvanger komt op dezelfde pagina uit.'),
                    Builder::make('header_blocks')
                        ->label('Header')
                        ->blocks(fn (): array => NewsletterCampaignResource::newsletterBlocks())
                        ->collapsible()
                        ->columnSpanFull(),
                    Builder::make('footer_blocks')
                        ->label('Footer')
                        ->helperText('Zet hier het afmeldblok als je de afmeldregel zelf wilt vormgeven. Doe je dat niet, dan komt er automatisch een standaardregel onderaan: zonder afmeldlink mag een nieuwsbrief niet verstuurd worden.')
                        ->blocks(fn (): array => NewsletterCampaignResource::newsletterBlocks())
                        ->collapsible()
                        ->columnSpanFull(),
                ])
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Naam')->searchable()->sortable(),
                TextColumn::make('site_id')
                    ->label('Actief op site')
                    ->sortable()
                    ->hidden(! (Sites::getAmountOfSites() > 1)),
                // De kolom toont het adres dat werkelijk gebruikt wordt, ook als
                // de lijst zelf leeg is. Een leeg vakje zou de indruk wekken dat
                // er geen afzender is.
                TextColumn::make('from_email')
                    ->label('Afzender')
                    ->searchable()
                    ->state(fn (NewsletterList $record): ?string => $record->effectiveFromEmail())
                    ->description(fn (NewsletterList $record): ?string => $record->from_email ? null : 'uit de algemene instellingen'),
                TextColumn::make('subscribers_count')
                    ->label('Contacten')
                    ->counts('subscribers')
                    ->sortable(),
                TextColumn::make('created_at')->label('Aangemaakt')->dateTime()->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                // Het verwijderen van een lijst neemt sinds de sleutels in de
                // migraties ook de contacten, velden, segmenten en het
                // toestemmingsbewijs mee. Dat is wat je bedoelt als je een lijst
                // weggooit, maar niet iets om er per ongeluk achter te komen,
                // dus staat het aantal in de bevestiging.
                DeleteAction::make()
                    ->modalDescription(function (NewsletterList $record): string {
                        $count = $record->subscribers()->count();

                        if ($count === 0) {
                            return 'Deze lijst heeft geen contacten. Weet je zeker dat je hem wilt verwijderen?';
                        }

                        return 'Deze lijst heeft ' . $count . ' ' . ($count === 1 ? 'contact' : 'contacten')
                            . '. Die worden samen met de velden, de segmenten en het toestemmingsbewijs '
                            . 'mee verwijderd. Dit is niet terug te draaien.';
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNewsletterLists::route('/'),
            'create' => CreateNewsletterList::route('/create'),
            'edit' => EditNewsletterList::route('/{record}/edit'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            FieldsRelationManager::class,
            SubscribersRelationManager::class,
            SegmentsRelationManager::class,
        ];
    }
}
