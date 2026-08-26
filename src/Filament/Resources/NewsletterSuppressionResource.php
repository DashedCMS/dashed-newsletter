<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Filament\Resources;

use Closure;
use UnitEnum;
use BackedEnum;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Dashed\DashedCore\Classes\Sites;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Builder;
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
            NewsletterSuppression::REASON_MARKETPLACE => 'Marktplaats',
        ];
    }

    public static function form(Schema $schema): Schema
    {
        // Bewust geen site-select, anders dan NewsletterListResource en
        // NewsletterCampaignResource: die tonen juist alle sites naast elkaar.
        // Deze lijst is operationeel en hoort bij de site waar je nu in werkt
        // (getEloquentQuery() hieronder filtert daar altijd op). Een keuzeveld
        // zou een beheerder toestaan een adres op een andere site te
        // blokkeren, waarna de nieuwe regel meteen uit de gefilterde lijst
        // verdwijnt: dat oogt als een mislukte actie terwijl hij gelukt is.
        // site_id wordt daarom altijd gevuld met Sites::getActive() in
        // mutateDataUsing hieronder, niet met een keuze uit het formulier.
        return $schema->components([
            TextInput::make('email')->label('E-mailadres')->email()->required()
                ->dehydrateStateUsing(fn (string $state): string => NewsletterSuppression::normalize($state))
                // Zonder dit vangt de unieke index op site_id + email de
                // dubbele regel pas af bij het opslaan, met een ruwe
                // databasefout op het scherm. blocks() is ook hier de ene
                // waarheid: dezelfde check als verzenden gebruikt, niet een
                // losse (case-gevoelige) unique() op de kolom.
                ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                    if (NewsletterSuppression::blocks(Sites::getActive(), (string) $value)) {
                        $fail('Dit e-mailadres staat al op de blokkadelijst.');
                    }
                }),
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
                TextColumn::make('reason')->label('Reden')->badge()
                    ->formatStateUsing(fn (string $state) => self::reasonOptions()[$state] ?? $state),
                TextColumn::make('source')->label('Bron')->placeholder('-'),
                TextColumn::make('created_at')->label('Sinds')->dateTime()->sortable(),
            ])
            ->headerActions([
                // Geen site-select om uit te lezen (zie form()): site_id komt
                // hier altijd van de actieve site, ongeacht wat er verder in
                // $data staat.
                CreateAction::make()
                    ->label('Adres blokkeren')
                    ->mutateDataUsing(function (array $data): array {
                        $data['site_id'] = Sites::getActive();

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
        // Drie takken, niet twee: een spamklacht is het spiegelbeeld van een
        // bounce. Bij een bounce kwam de mail niet aan en is verwijderen een
        // gok dat het adres nu weer werkt. Bij een klacht kwam de mail juist
        // wel aan: de ontvanger heeft hem gezien en zelf gemeld als spam.
        // Verwijderen is daar geen gok over bereikbaarheid maar het terugzetten
        // van iemand die met zoveel woorden gezegd heeft dit niet te willen,
        // en dat weegt zwaarder dan een bounce. De bounce-tekst hieronder
        // dient ook als terugvaltekst voor een reden die hier niet expliciet
        // benoemd is.
        return match ($record->reason) {
            NewsletterSuppression::REASON_MANUAL => 'Dit adres is met de hand geblokkeerd. Verwijderen heft de blokkade op: de eerstvolgende nieuwsbrief gaat er weer naartoe.',
            NewsletterSuppression::REASON_MARKETPLACE => 'Dit adres kwam via een marktplaats zoals Bol.com binnen. Die klant is klant van de marktplaats en heeft jou geen toestemming gegeven, dus die mag geen nieuwsbrief krijgen. Verwijder deze regel alleen als je zeker weet dat deze persoon zich daarnaast zelf heeft aangemeld.',
            NewsletterSuppression::REASON_COMPLAINT => 'Dit adres is geblokkeerd vanwege een spamklacht. Anders dan bij een onbestelbaar adres kwam deze mail wel aan: '
                . 'de ontvanger heeft hem gezien en zelf als spam gemeld. Verwijderen zet dit adres tegen zijn eigen wil terug op de lijst. '
                . 'De eerstvolgende nieuwsbrief gaat er dan weer naartoe.',
            default => 'Dit adres is geblokkeerd vanwege "' . (self::reasonOptions()[$record->reason] ?? $record->reason) . '". De vorige keer kwam de mail terug; '
                . 'verwijderen betekent dat je aanneemt dat dit adres nu weer werkt. De eerstvolgende nieuwsbrief gaat er dan weer naartoe.',
        };
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
