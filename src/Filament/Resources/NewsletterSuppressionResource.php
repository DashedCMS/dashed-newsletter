<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Filament\Resources;

use UnitEnum;
use BackedEnum;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedNewsletter\Models\NewsletterSuppression;
use Dashed\DashedNewsletter\Filament\Resources\NewsletterSuppressionResource\Pages\ListNewsletterSuppressions;

class NewsletterSuppressionResource extends Resource
{
    protected static ?string $model = NewsletterSuppression::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-no-symbol';

    protected static string | UnitEnum | null $navigationGroup = 'Communicatie';

    protected static ?int $navigationSort = 7;

    protected static ?string $navigationLabel = 'Geblokkeerde adressen';

    protected static ?string $label = 'Geblokkeerd adres';

    protected static ?string $pluralLabel = 'Geblokkeerde adressen';

    public static function reasonOptions(): array
    {
        return [
            NewsletterSuppression::REASON_BOUNCE => 'Onbestelbaar',
            NewsletterSuppression::REASON_COMPLAINT => 'Spamklacht',
            NewsletterSuppression::REASON_MANUAL => 'Met de hand',
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            // Zelfde vorm als NewsletterListResource en NewsletterCampaignResource:
            // bij één site verborgen en gevuld met de enige site, bij meerdere
            // sites zichtbaar en verplicht. De blokkadelijst is per site (zie
            // NewsletterSuppression::blocks()), dus een beheerder met meerdere
            // sites moet hier kunnen kiezen voor welke site hij blokkeert.
            Select::make('site_id')
                ->label('Site')
                ->options(collect(Sites::getSites())->pluck('name', 'id')->toArray())
                ->default(fn () => Sites::getFirstSite()['id'])
                ->required()
                ->hidden(fn (): bool => ! (Sites::getAmountOfSites() > 1)),
            TextInput::make('email')->label('E-mailadres')->email()->required()
                ->dehydrateStateUsing(fn (string $state): string => NewsletterSuppression::normalize($state)),
            Select::make('reason')->label('Reden')->options(self::reasonOptions())
                ->default(NewsletterSuppression::REASON_MANUAL)->required(),
            Textarea::make('notes')->label('Aantekening')->rows(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('email')->label('E-mailadres')->searchable(),
                TextColumn::make('site_id')
                    ->label('Site')
                    ->sortable()
                    ->hidden(! (Sites::getAmountOfSites() > 1)),
                TextColumn::make('reason')->label('Reden')->badge()
                    ->formatStateUsing(fn (string $state) => self::reasonOptions()[$state] ?? $state),
                TextColumn::make('source')->label('Bron')->placeholder('-'),
                TextColumn::make('created_at')->label('Sinds')->dateTime()->sortable(),
            ])
            ->headerActions([
                // Het site_id-veld hierboven is verborgen bij één site en leunt
                // dan op zijn default(). Die default wordt bij een gewone
                // create-pagina netjes gedehydreerd, maar een CreateAction als
                // headerAction op een Table mount anders en laat een verborgen
                // veld zonder eigen invoer leeg: zonder deze vangnet crasht het
                // aanmaken op de NOT NULL van site_id. Staat er ook een gekozen
                // waarde (bij meerdere sites), dan wint die gewoon.
                CreateAction::make()
                    ->label('Adres blokkeren')
                    ->mutateDataUsing(function (array $data): array {
                        $data['site_id'] ??= Sites::getActive();

                        return $data;
                    }),
            ])
            ->recordActions([
                // Weghalen mag: een adres dat ooit bounced kan later gewoon weer
                // werken. Maar het is geen neutrale handeling (de vorige keer kwam
                // de mail terug), dus de tekst hieronder zegt dat met zoveel
                // woorden in plaats van een kale "weet je het zeker?".
                DeleteAction::make()
                    ->modalDescription(fn (NewsletterSuppression $record): string => self::deleteWarningDescription($record)),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function deleteWarningDescription(NewsletterSuppression $record): string
    {
        if ($record->reason === NewsletterSuppression::REASON_MANUAL) {
            return 'Dit adres is met de hand geblokkeerd. Verwijderen heft de blokkade op: de eerstvolgende nieuwsbrief gaat er weer naartoe.';
        }

        $reasonLabel = self::reasonOptions()[$record->reason] ?? $record->reason;

        return 'Dit adres is geblokkeerd vanwege "' . $reasonLabel . '". De vorige keer kwam de mail terug; '
            . 'verwijderen betekent dat je aanneemt dat dit adres nu weer werkt. De eerstvolgende nieuwsbrief gaat er dan weer naartoe.';
    }

    public static function getEloquentQuery(): Builder
    {
        // Een adres dat op site A geblokkeerd is, hoort op site B niet in beeld
        // te staan: NewsletterSuppression::blocks() kijkt ook alleen naar de
        // eigen site, en dit scherm hoort daar geen eigen oordeel aan toe te
        // voegen.
        return parent::getEloquentQuery()
            ->where('site_id', (string) Sites::getActive());
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNewsletterSuppressions::route('/'),
        ];
    }
}
