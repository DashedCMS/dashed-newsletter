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
use Illuminate\Auth\Access\Response;
use Dashed\DashedCore\Classes\Sites;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Model;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\RichEditor;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Dashed\DashedNewsletter\Models\NewsletterList;
use Dashed\DashedNewsletter\Models\NewsletterSegment;
use Dashed\DashedNewsletter\Models\NewsletterCampaign;
use Dashed\DashedNewsletter\Filament\Resources\NewsletterCampaignResource\Pages\EditNewsletterCampaign;
use Dashed\DashedNewsletter\Filament\Resources\NewsletterCampaignResource\Pages\ListNewsletterCampaigns;
use Dashed\DashedNewsletter\Filament\Resources\NewsletterCampaignResource\Pages\CreateNewsletterCampaign;

class NewsletterCampaignResource extends Resource
{
    protected static ?string $model = NewsletterCampaign::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-paper-airplane';

    protected static string | UnitEnum | null $navigationGroup = 'Communicatie';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Campagnes';

    protected static ?string $label = 'Campagne';

    protected static ?string $pluralLabel = 'Campagnes';

    public static function statusOptions(): array
    {
        return [
            NewsletterCampaign::STATUS_CONCEPT => 'Concept',
            NewsletterCampaign::STATUS_SCHEDULED => 'Ingepland',
            NewsletterCampaign::STATUS_SENDING => 'Bezig met verzenden',
            NewsletterCampaign::STATUS_SENT => 'Verzonden',
            NewsletterCampaign::STATUS_CANCELLED => 'Gestopt',
            NewsletterCampaign::STATUS_FAILED => 'Mislukt',
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Campagne')->columnSpanFull()->columns(2)->schema([
                // Zelfde vorm als NewsletterListResource: bij één site verborgen
                // en ingevuld door CreateNewsletterCampaign, bij meerdere sites
                // zichtbaar en bepalend voor de lijst-opties hieronder. Zonder
                // deze filter is een lijst van een andere site te kiezen, en dan
                // gaat de campagne naar mensen op een site waar hij niet bij hoort.
                Select::make('site_id')
                    ->label('Actief op site')
                    ->options(collect(Sites::getSites())->pluck('name', 'id')->toArray())
                    ->default(fn () => Sites::getFirstSite()['id'])
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('newsletter_list_id', null))
                    ->hidden(fn (): bool => ! (Sites::getAmountOfSites() > 1)),
                TextInput::make('name')->label('Naam')->required()->maxLength(255)
                    ->helperText('Alleen voor jezelf, dit komt niet in de mail.'),
                Select::make('newsletter_list_id')
                    ->label('Lijst')
                    ->options(fn (Get $get): array => NewsletterList::forSite($get('site_id'))->pluck('name', 'id')->all())
                    ->required()
                    ->live(),
                Select::make('newsletter_segment_id')
                    ->label('Segment')
                    ->placeholder('De hele lijst')
                    ->helperText('Laat leeg om naar iedereen op de lijst te sturen.')
                    ->options(fn (Get $get): array => $get('newsletter_list_id')
                        ? NewsletterSegment::where('newsletter_list_id', $get('newsletter_list_id'))->pluck('name', 'id')->all()
                        : []),
                TextInput::make('subject')->label('Onderwerp')->required()->maxLength(255),
                TextInput::make('preheader')->label('Preheader')->maxLength(255)
                    ->helperText('De regel die een mailbox naast het onderwerp toont.'),
                TextInput::make('from_email')->label('Afzenderadres')->email()
                    ->helperText('Laat leeg om dat van de lijst te gebruiken.'),
                TextInput::make('reply_to_email')->label('Antwoordadres')->email(),
            ]),
            Section::make('Inhoud')->columnSpanFull()->schema([
                RichEditor::make('content')->label('')->required()->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Naam')->searchable()->sortable(),
                TextColumn::make('list.name')->label('Lijst'),
                TextColumn::make('segment.name')->label('Segment')->placeholder('Hele lijst'),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (string $state) => self::statusOptions()[$state] ?? $state),
                TextColumn::make('sent_count')->label('Verzonden')
                    ->state(fn (NewsletterCampaign $record): string => $record->sent_count . ' van ' . $record->recipients_count),
                TextColumn::make('scheduled_at')->label('Ingepland')->dateTime()->placeholder('-'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->modalDescription(
                    fn (NewsletterCampaign $record): string => self::deleteWarningDescription($record)
                ),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNewsletterCampaigns::route('/'),
            'create' => CreateNewsletterCampaign::route('/create'),
            'edit' => EditNewsletterCampaign::route('/{record}/edit'),
        ];
    }

    /**
     * Bewerken mag alleen zolang de campagne nog niet in gang is gezet: concept
     * en ingepland. Bij verzonden en bezig is dat duidelijk: een deel van de
     * ontvangers heeft de oude inhoud al binnen, en bewerken zou de campagne
     * laten afwijken van wat er echt de deur uit is.
     *
     * Geannuleerd en mislukt staan hier om een andere reden op slot, en dat is
     * een keuze en geen gevolg. Vandaag zet alleen SendScheduledCampaigns een
     * campagne op mislukt, en dat gebeurt vóórdat er ook maar één ontvanger is
     * aangeraakt; geannuleerd wordt op dit moment nergens gezet. Er is dus nu
     * nog geen halve verzending om te beschermen. Zodra er wel afgebroken kan
     * worden midden in het verzenden is die er wel, en dan is dit het gedrag
     * dat je wilt. Beide statussen alvast weigeren is de veilige kant, want
     * openzetten kan later alsnog en een verkeerd bewerkte verzonden campagne
     * niet.
     *
     * Dit overschrijft getEditAuthorizationResponse() in plaats van canEdit():
     * canEdit() roept hem al aan (zie Filament\Resources\Resource\Concerns\
     * HasAuthorization), maar EditAction/DeleteAction in een tabel of op de
     * headeractie van een pagina lezen alléén getEditAuthorizationResponse() /
     * getDeleteAuthorizationResponse(). Een canEdit()-override alleen zou de
     * bewerkpagina wel op slot zetten maar de knop in de tabel niet.
     */
    public static function getEditAuthorizationResponse(Model $record): Response
    {
        if (in_array($record->status, [NewsletterCampaign::STATUS_CONCEPT, NewsletterCampaign::STATUS_SCHEDULED], true)) {
            return Response::allow();
        }

        return Response::deny('Deze campagne is al in gang gezet en kan niet meer bewerkt worden.');
    }

    /**
     * Verwijderen mag altijd, behalve terwijl er op dit moment verstuurd
     * wordt: dat zou de portie die net loopt onder de voeten wegtrekken. Een
     * afgeronde campagne (verzonden, geannuleerd, mislukt) mag een beheerder
     * juist wel opruimen, maar de foreign key op de ontvangers staat op
     * cascadeOnDelete, dus de hele verzendgeschiedenis gaat dan mee. Dat mag
     * niet stilzwijgend gebeuren, vandaar de modalDescription hieronder.
     */
    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        if ($record->status === NewsletterCampaign::STATUS_SENDING) {
            return Response::deny('Deze campagne is op dit moment aan het verzenden.');
        }

        return Response::allow();
    }

    /**
     * Zelfde vorm als NewsletterListResource::table()'s modalDescription voor
     * zijn eigen cascade: laat zien wat er precies meegaat, met een ander
     * bericht als er nog niets te verliezen is.
     */
    public static function deleteWarningDescription(NewsletterCampaign $record): string
    {
        $count = $record->recipients()->count();

        if ($count === 0) {
            return 'Deze campagne heeft nog geen ontvangers. Weet je zeker dat je hem wilt verwijderen?';
        }

        return 'Deze campagne heeft ' . $count . ' ' . ($count === 1 ? 'ontvanger' : 'ontvangers')
            . ' in de verzendgeschiedenis. Die gaat mee weg bij het verwijderen. Dit is niet terug te draaien.';
    }
}
