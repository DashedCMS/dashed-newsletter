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
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Dashed\DashedNewsletter\Models\NewsletterList;
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
                TextInput::make('from_email')->label('Afzenderadres')->email()->required(),
                TextInput::make('reply_to_email')->label('Antwoordadres')->email(),
                Toggle::make('notify_on_subscribe')->label('Melding bij aanmelding'),
                Toggle::make('notify_on_unsubscribe')->label('Melding bij afmelding'),
            ])->columns(2),
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
                TextColumn::make('from_email')->label('Afzender')->searchable(),
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
