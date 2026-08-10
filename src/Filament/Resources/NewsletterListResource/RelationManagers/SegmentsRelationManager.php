<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Filament\Resources\NewsletterListResource\RelationManagers;

use RuntimeException;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Illuminate\Support\HtmlString;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Actions\DeleteBulkAction;
use Filament\Schemas\Components\Group;
use Filament\Forms\Components\Repeater;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Dashed\DashedNewsletter\Segments\SegmentQuery;
use Dashed\DashedNewsletter\Models\NewsletterSegment;
use Dashed\DashedNewsletter\Models\NewsletterSubscriber;
use Filament\Resources\RelationManagers\RelationManager;
use Dashed\DashedNewsletter\Filament\Forms\SegmentRuleBuilder;
use Dashed\DashedNewsletter\Segments\SegmentConditionRegistry;
use Dashed\DashedNewsletter\Segments\Contracts\SegmentCondition;
use Dashed\DashedNewsletter\Segments\Exceptions\InvalidSegmentException;

class SegmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'segments';

    protected static ?string $title = 'Segmenten';

    /**
     * Gegroepeerde optielijst voor de conditie-select in de repeater: elke
     * groep wordt een optgroup, zodat "Contactvelden" en "Aanmelding"
     * zichtbaar apart staan in plaats van als één platte lijst.
     *
     * @return array<string, array<string, string>>
     */
    private static function conditionOptions(): array
    {
        return collect(SegmentRuleBuilder::conditionsByGroup())
            ->map(fn (array $conditions): array => collect($conditions)
                ->map(fn (SegmentCondition $condition): string => $condition->label())
                ->all())
            ->all();
    }

    /**
     * Bouwt het formulierstukje voor de gekozen conditie. FieldCondition kent
     * de lijst niet en vult zijn velddropdown standaard met alle velden van
     * alle lijsten (NewsletterField::pluck() zonder scope): twee lijsten met
     * dezelfde veldsleutel zouden dan tot één keuze samenklappen, en een
     * beheerder zou hier een veld kunnen kiezen dat op deze lijst niet eens
     * bestaat. Omdat de relatiebeheerder de lijst wél kent (via
     * getOwnerRecord()), wordt de optielijst van dat ene veld hier -na
     * afloop- overschreven, zonder FieldCondition zelf aan te passen.
     *
     * @return array<int, mixed>
     */
    private function conditionSchemaFor(?string $conditionKey): array
    {
        $registry = app(SegmentConditionRegistry::class);

        if (! $conditionKey || ! $registry->has($conditionKey)) {
            return [];
        }

        $schema = $registry->get($conditionKey)->schema();

        if ($conditionKey === 'field') {
            $keyField = null;

            foreach ($schema as $component) {
                if ($component instanceof Select && $component->getName() === 'key') {
                    $keyField = $component;
                }
            }

            // Stil doorgaan zou hier precies het lek zijn dat deze scoping
            // moet dichten: zonder dit veld valt er niets te overschrijven,
            // en levert schema() zijn ongescoopte NewsletterField::pluck()
            // over alle lijsten heen gewoon terug, zonder enig signaal.
            if (! $keyField) {
                throw new RuntimeException(
                    'De veldkeuze van de segmentconditie "field" kan niet worden beperkt tot de velden van '
                    . 'deze lijst: FieldCondition::schema() levert geen veld met de naam "key" meer op. '
                    . 'Waarschijnlijk is die veldnaam gewijzigd zonder dat '
                    . 'SegmentsRelationManager::conditionSchemaFor() is bijgewerkt.'
                );
            }

            $keyField->options($this->getOwnerRecord()->fields()->pluck('label', 'key'));
        }

        return $schema;
    }

    private static function previewSubscribersContent(NewsletterSegment $record): HtmlString
    {
        try {
            $subscribers = SegmentQuery::for($record)->limit(50)->get();
        } catch (InvalidSegmentException $e) {
            return new HtmlString('<p>' . e($e->getMessage()) . '</p>');
        }

        if ($subscribers->isEmpty()) {
            return new HtmlString('<p>Geen contacten gevonden voor dit segment.</p>');
        }

        $rows = $subscribers
            ->map(fn (NewsletterSubscriber $subscriber): string => '<tr><td class="px-3 py-2">'
                . e($subscriber->email) . '</td><td class="px-3 py-2">' . e($subscriber->status) . '</td></tr>')
            ->implode('');

        return new HtmlString(
            '<table class="w-full text-sm"><thead><tr>'
            . '<th class="px-3 py-2 text-left">E-mailadres</th><th class="px-3 py-2 text-left">Status</th>'
            . '</tr></thead><tbody>' . $rows . '</tbody></table>'
        );
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            // Overgeslagen condities zouden het segment stilzwijgend de hele
            // lijst maken (zie SegmentQuery::applyCondition), dus dit moet
            // zichtbaar zijn in plaats van pas te knallen bij verzenden.
            Placeholder::make('missing_conditions_warning')
                ->hiddenLabel()
                ->visible(fn (?NewsletterSegment $record): bool => $record
                    && SegmentRuleBuilder::missingConditionKeys($record->rules ?? []) !== [])
                ->content(fn (?NewsletterSegment $record) => new HtmlString(
                    '<div class="text-sm text-warning-600">Dit segment gebruikt voorwaarden die niet meer '
                    . 'bestaan: ' . e(implode(', ', SegmentRuleBuilder::missingConditionKeys($record?->rules ?? [])))
                    . '. Waarschijnlijk is het package dat deze voorwaarde levert niet meer geïnstalleerd. '
                    . 'Verwijder de regel hieronder of vervang hem door een bestaande voorwaarde.</div>'
                ))
                ->columnSpanFull(),

            // Een segment kijkt naar de regels en verder nergens naar. Zonder
            // statusvoorwaarde zitten uitgeschreven contacten er dus gewoon in.
            // Dat blijft zo, want "toon mij de uitgeschrevenen" moet ook een
            // segment kunnen zijn; stil filteren zou die vraag onmogelijk maken.
            // Wat wel moet, is dat de redacteur het wéét voordat er ooit een
            // campagne overheen gaat. Voor deelproject 3 geldt daarbovenop een
            // harde eis: verzenden mag niet naar wie is uitgeschreven.
            Placeholder::make('status_condition_warning')
                ->hiddenLabel()
                ->visible(fn (?NewsletterSegment $record): bool => $record !== null
                    && ! SegmentRuleBuilder::usesConditionKey($record->rules ?? [], 'subscriber.status'))
                ->content(new HtmlString(
                    '<div class="text-sm text-warning-600">Dit segment heeft geen voorwaarde op de status. '
                    . 'Uitgeschreven en opgeschoonde contacten tellen dus mee. Voeg de voorwaarde '
                    . '<strong>Status</strong> toe met de waarde <strong>Actief</strong> als je dat niet wilt.</div>'
                ))
                ->columnSpanFull(),

            TextInput::make('name')
                ->label('Naam')
                ->required()
                ->maxLength(255),

            Select::make('rules.operator')
                ->label('Combineer voorwaarden met')
                ->options([
                    'and' => 'En (alle voorwaarden moeten kloppen)',
                    'or' => 'Of (één voorwaarde is al genoeg)',
                ])
                ->default('and')
                ->required(),

            Repeater::make('rules.children')
                ->label('Voorwaarden')
                ->addActionLabel('Voorwaarde toevoegen')
                ->reorderableWithButtons()
                ->collapsible()
                ->defaultItems(0)
                // Een segment zonder voorwaarden zou de hele lijst opleveren
                // (zie SegmentQuery::for). Hier tegenhouden is vriendelijker dan
                // de beheerder pas bij het uitrekenen tegen een uitzondering
                // laten aanlopen, maar die uitzondering blijft het sluitstuk:
                // dit scherm is niet de enige manier om regels weg te krijgen.
                ->minItems(1)
                ->schema([
                    Select::make('condition')
                        ->label('Voorwaarde')
                        ->options(fn (): array => self::conditionOptions())
                        ->required()
                        ->live()
                        ->searchable()
                        // Bij een andere conditie horen andere velden; oude
                        // waarden laten staan zou een operator of waarde van
                        // de vorige conditie stilzwijgend laten meelopen.
                        ->afterStateUpdated(function (Set $set): void {
                            $set('key', null);
                            $set('operator', null);
                            $set('value', null);
                        }),
                    Group::make()
                        ->columns(2)
                        ->schema(fn (Get $get): array => $this->conditionSchemaFor($get('condition'))),
                ])
                ->columns(1)
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Naam')->searchable(),
                TextColumn::make('contact_count')
                    ->label('Aantal contacten')
                    // Bewust cachedCount(), niet count(): anders draait er bij
                    // elke render van deze tabel een zware query per rij.
                    ->state(function (NewsletterSegment $record): string {
                        try {
                            return (string) SegmentQuery::cachedCount($record);
                        } catch (InvalidSegmentException) {
                            return 'Ongeldig segment';
                        }
                    })
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Ongeldig segment' ? 'danger' : 'gray'),
            ])
            ->headerActions([
                CreateAction::make()->label('Nieuw segment'),
            ])
            ->recordActions([
                Action::make('refreshCount')
                    ->label('Ververs telling')
                    ->icon('heroicon-o-arrow-path')
                    ->action(function (NewsletterSegment $record): void {
                        try {
                            $count = SegmentQuery::cachedCount($record, forget: true);
                        } catch (InvalidSegmentException $e) {
                            Notification::make()
                                ->title('Ongeldig segment')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Telling ververst')
                            ->body($count . ' ' . ($count === 1 ? 'contact voldoet' : 'contacten voldoen') . ' aan dit segment.')
                            ->success()
                            ->send();
                    }),
                Action::make('previewSubscribers')
                    ->label('Toon eerste vijftig')
                    ->icon('heroicon-o-eye')
                    ->modalHeading('Eerste vijftig contacten in dit segment')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Sluiten')
                    ->modalContent(fn (NewsletterSegment $record) => self::previewSubscribersContent($record)),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
