<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Filament\Resources;

use UnitEnum;
use BackedEnum;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Actions\DeleteAction;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Auth\Access\Response;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Builder;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Placeholder;
use Dashed\DashedCore\Mail\EmailBlocks\EmailBlock;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Dashed\DashedNewsletter\Models\NewsletterList;
use Dashed\DashedNewsletter\Models\NewsletterSegment;
use Dashed\DashedNewsletter\Models\NewsletterCampaign;
use Dashed\DashedNewsletter\Campaigns\CampaignCanceller;
use Dashed\DashedNewsletter\Models\NewsletterCampaignRecipient;
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
            // Alleen zichtbaar op een mislukte campagne: vóór deze reparatie
            // stond er in het beheer niets dan de statusnaam "Mislukt", en
            // moest een beheerder in de serverlogs van schedule:run zoeken om
            // te weten wat hij moest repareren.
            Section::make('Mislukt')->columnSpanFull()
                ->visible(fn (?NewsletterCampaign $record): bool => $record?->status === NewsletterCampaign::STATUS_FAILED)
                ->schema([
                    Placeholder::make('failure_reason')
                        ->label('Reden')
                        ->content(fn (?NewsletterCampaign $record): string => $record?->failure_reason ?? 'Onbekend.'),
                ]),
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
                Builder::make('blocks')
                    ->label('Inhoud')
                    ->blocks(fn (): array => self::newsletterBlocks())
                    ->collapsible()
                    ->cloneable()
                    ->columnSpanFull(),
            ]),
        ]);
    }

    /**
     * De blokken die in een nieuwsbrief mogen. De filter op de context is wat
     * een bestelsamenvatting hier buiten houdt: die hoort in een
     * bestelbevestiging en nergens anders.
     *
     * @return array<int, \Filament\Forms\Components\Builder\Block>
     */
    public static function newsletterBlocks(): array
    {
        return collect(cms()->emailBlocks())
            ->filter(fn (string $class) => $class::inContext(EmailBlock::CONTEXT_NEWSLETTER))
            ->map(fn (string $class) => $class::filamentBlock())
            ->values()
            ->all();
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
                // Alleen tonen bij een werkelijk mislukte campagne, niet
                // zomaar bij elke gevulde waarde: StartCampaignJob wist
                // failure_reason bij een herstart, dus dit is voornamelijk
                // een extra slot, geen enige bewaker.
                // Via state() en niet via formatStateUsing(): Filament kijkt of
                // de waarde leeg is vóórdat het formatteren gebeurt, dus een
                // formatter die null teruggeeft levert een leeg vakje op in
                // plaats van het streepje dat alle andere rijen tonen.
                TextColumn::make('failure_reason')->label('Reden mislukt')->placeholder('-')->wrap()
                    ->state(fn (NewsletterCampaign $record): ?string => $record->status === NewsletterCampaign::STATUS_FAILED ? $record->failure_reason : null),
                TextColumn::make('sent_count')->label('Verzonden')
                    ->state(fn (NewsletterCampaign $record): string => $record->sent_count . ' van ' . $record->recipients_count),
                TextColumn::make('scheduled_at')->label('Ingepland')->dateTime()->placeholder('-'),
            ])
            ->recordActions([
                // Alleen zichtbaar tijdens 'sending': de enige status waar iets
                // af te breken valt. Deze knop staat bewust in de tabel en niet
                // (ook) op de bewerkpagina: getEditAuthorizationResponse()
                // hieronder wijst een campagne die aan het verzenden is de hele
                // bewerkpagina al af, dus die knop zou daar nooit te bereiken
                // zijn.
                Action::make('cancel')
                    ->label('Afbreken')
                    ->icon('heroicon-o-stop-circle')
                    ->color('danger')
                    ->visible(fn (NewsletterCampaign $record): bool => $record->status === NewsletterCampaign::STATUS_SENDING)
                    ->requiresConfirmation()
                    ->modalHeading('Campagne afbreken')
                    ->modalDescription(fn (NewsletterCampaign $record): string => self::cancelWarningDescription($record))
                    ->modalSubmitActionLabel('Afbreken')
                    ->action(fn (NewsletterCampaign $record) => CampaignCanceller::cancel($record)),
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
     * Bewerken mag zolang er geen halve verzending te beschermen is: concept,
     * ingepland, geannuleerd, en mislukt. Bij verzonden en bezig is dat
     * duidelijk: een deel van de ontvangers heeft de (huidige) inhoud al
     * binnen of krijgt hem op dit moment, en bewerken zou de campagne laten
     * afwijken van wat er echt de deur uit is of gaat.
     *
     * Geannuleerd en mislukt zaten hier eerder allebei op slot, met als
     * redenering dat zodra afbreken tijdens het verzenden mogelijk werd er
     * alsnog iets te beschermen zou zijn. Dat bleek de verkeerde afweging voor
     * allebei: een afgebroken campagne is precies het geval waarin een
     * beheerder móet kunnen bewerken (bijvoorbeeld het segment of onderwerp
     * herstellen na een vastgelopen verzending), en CampaignCanceller::cancel()
     * laat de al verzonden regels ongemoeid, dus die geschiedenis blijft
     * kloppen ongeacht latere bewerkingen. Mislukt is nog stelliger: dat wordt
     * vandaag alleen gezet door SendScheduledCampaigns, en dat gebeurt vóórdat
     * er ook maar één ontvanger is aangeraakt (zie CampaignGuard::problem()),
     * dus daar valt sowieso niets te beschermen.
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
        if (in_array($record->status, [
            NewsletterCampaign::STATUS_CONCEPT,
            NewsletterCampaign::STATUS_SCHEDULED,
            NewsletterCampaign::STATUS_CANCELLED,
            NewsletterCampaign::STATUS_FAILED,
        ], true)) {
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

    /**
     * Legt uit wat afbreken concreet betekent, met echte aantallen: wie de
     * mail al had blijft dat houden, wie nog moest komen krijgt hem niet meer.
     */
    public static function cancelWarningDescription(NewsletterCampaign $record): string
    {
        $verzonden = $record->recipients()->where('status', NewsletterCampaignRecipient::STATUS_SENT)->count();
        $openstaand = $record->recipients()->whereIn('status', [
            NewsletterCampaignRecipient::STATUS_PENDING,
            NewsletterCampaignRecipient::STATUS_SENDING,
        ])->count();

        $verzondenZin = $verzonden === 0
            ? 'Nog niemand heeft deze campagne gehad.'
            : $verzonden . ' ' . ($verzonden === 1 ? 'ontvanger heeft' : 'ontvangers hebben')
                . ' deze campagne al gehad; dat blijft zo, er wordt niets teruggedraaid.';

        return $verzondenZin . ' De resterende ' . $openstaand . ' ' . ($openstaand === 1 ? 'ontvanger' : 'ontvangers')
            . ' ' . ($openstaand === 1 ? 'krijgt' : 'krijgen') . ' hem niet meer. Dit is niet terug te draaien.';
    }
}
