<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Filament\Actions;

use Filament\Actions\Action;
use Dashed\DashedAi\Facades\Ai;
use Filament\Support\Enums\Width;
use Illuminate\Support\HtmlString;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\CheckboxList;
use Dashed\DashedNewsletter\Ai\CampaignPlan;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Dashed\DashedNewsletter\Ai\CampaignPlanner;
use Dashed\DashedNewsletter\Ai\CampaignBriefing;
use Dashed\DashedNewsletter\Ai\CampaignComposer;
use Dashed\DashedNewsletter\Models\NewsletterList;
use Dashed\DashedCore\Classes\ContentStudio\BlockCatalog;
use Dashed\DashedNewsletter\Ai\Exceptions\AiGenerationFailedException;
use Dashed\DashedNewsletter\Filament\Resources\NewsletterCampaignResource;

/**
 * De twee knoppen van de AI-generator.
 *
 * Twee acties en geen wizard: de goedkeuringsstap zit tussen twee AI-aanroepen
 * in, en in een wizard zou fase 1 in Step::afterValidation() moeten draaien.
 * Die stap is in een Livewire-test niet te bereiken, want callAction() dient de
 * hele modal in een keer in. Twee losse acties zijn elk wel aan te roepen, dus
 * de hele weg van briefing tot ingevuld formulier is te testen.
 *
 * Het voorstel reist tussen de twee knoppen door via het formulierveld
 * ai_plan, dat op dehydrated(false) staat: er is geen kolom voor en die hoort
 * er ook niet te komen. Wie het scherm sluit zonder opslaan, verandert niets.
 */
class GenerateCampaignWithAiAction
{
    public static function plan(): Action
    {
        return Action::make('generateCampaignPlanWithAi')
            ->label('Opstellen met AI')
            ->icon('heroicon-o-sparkles')
            ->modalWidth(Width::TwoExtraLarge)
            ->modalHeading('Waar gaat deze nieuwsbrief over?')
            ->modalDescription('De AI zoekt zelf in je webshop en artikelen en komt met een voorstel. Je ziet dat voorstel voordat er iets geschreven wordt.')
            ->modalSubmitActionLabel('Zoek en stel voor')
            ->visible(fn (): bool => self::beschikbaar())
            ->schema([
                TextInput::make('audience')
                    ->label('Voor wie')
                    ->placeholder('Bijvoorbeeld: vaste klanten die vorig jaar tuinmeubels kochten')
                    ->required(),
                TextInput::make('occasion')
                    ->label('Aanleiding')
                    ->placeholder('Bijvoorbeeld: de zomeractie begint')
                    ->required(),
                TextInput::make('promote')
                    ->label('Wat promoten')
                    ->placeholder('Bijvoorbeeld: het nieuwe tuinmeubilair'),
                Radio::make('length')
                    ->label('Gewenste lengte')
                    ->options(CampaignBriefing::LENGTHS)
                    ->default('gemiddeld')
                    ->required(),
                Textarea::make('instruction')
                    ->label('Eigen aanwijzing')
                    ->placeholder('Bijvoorbeeld: geen uitroeptekens, en noem de gratis verzending')
                    ->rows(3),
            ])
            ->action(function (array $data, Get $get, Set $set): void {
                try {
                    $plan = app(CampaignPlanner::class)->plan(
                        CampaignBriefing::fromFormData($data),
                        self::siteId($get),
                    );
                } catch (AiGenerationFailedException $e) {
                    // Alles of niets: er verandert niets aan de campagne.
                    Notification::make()
                        ->title('Het opstellen lukte niet')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                $set('ai_plan', $plan->toArray());
                $set('ai_briefing', $data);

                Notification::make()
                    ->title('Er ligt een voorstel')
                    ->body('Klik op "AI-voorstel bekijken" om het na te lopen en de nieuwsbrief te laten schrijven.')
                    ->success()
                    ->send();
            });
    }

    public static function apply(): Action
    {
        return Action::make('applyCampaignAiPlan')
            ->label('AI-voorstel bekijken')
            ->icon('heroicon-o-clipboard-document-check')
            ->color('gray')
            ->modalWidth(Width::TwoExtraLarge)
            ->modalHeading('Het voorstel')
            ->modalDescription('Haal eruit wat je niet wilt. Alleen wat je laat staan mag in de mail komen.')
            ->modalSubmitActionLabel('Schrijf de nieuwsbrief')
            ->visible(fn (Get $get): bool => self::beschikbaar() && filled($get('ai_plan')))
            ->fillForm(fn ($livewire): array => [
                // Alles staat aan: de redacteur haalt eruit, hij bouwt niet op.
                'keep_products' => self::planVanScherm($livewire)->productIds(),
                'keep_articles' => self::planVanScherm($livewire)->articleIds(),
            ])
            ->schema([
                Placeholder::make('outline')
                    ->label('Voorgestelde opbouw')
                    ->content(fn ($livewire): HtmlString => self::opbouw($livewire)),
                CheckboxList::make('keep_products')
                    ->label('Producten')
                    ->options(fn ($livewire): array => self::keuzes($livewire, 'products'))
                    ->descriptions(fn ($livewire): array => self::redenen($livewire, 'products'))
                    ->bulkToggleable()
                    ->visible(fn ($livewire): bool => self::keuzes($livewire, 'products') !== []),
                CheckboxList::make('keep_articles')
                    ->label('Artikelen')
                    ->options(fn ($livewire): array => self::keuzes($livewire, 'articles'))
                    ->descriptions(fn ($livewire): array => self::redenen($livewire, 'articles'))
                    ->bulkToggleable()
                    ->visible(fn ($livewire): bool => self::keuzes($livewire, 'articles') !== []),
                Textarea::make('adjustment')
                    ->label('Bijsturen')
                    ->placeholder('Bijvoorbeeld: maak het korter, en begin met het artikel')
                    ->rows(3),
            ])
            ->action(function (array $data, $livewire, Set $set): void {
                $plan = self::planVanScherm($livewire)->only(
                    (array) ($data['keep_products'] ?? []),
                    (array) ($data['keep_articles'] ?? []),
                );

                try {
                    $concept = app(CampaignComposer::class)->compose(
                        $plan,
                        self::briefingVanScherm($livewire),
                        (new BlockCatalog())->fromBlocks(NewsletterCampaignResource::newsletterBlocks()),
                        trim((string) ($data['adjustment'] ?? '')),
                    );
                } catch (AiGenerationFailedException $e) {
                    // Het voorstel blijft staan, zodat de redacteur het opnieuw
                    // kan proberen met een andere bijsturing zonder de hele
                    // zoekronde over te doen.
                    Notification::make()
                        ->title('Het schrijven lukte niet')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                $set('name', $concept->name);
                $set('subject', $concept->subject);
                $set('preheader', $concept->preheader);
                $set('blocks', $concept->blocks);

                // Het voorstel is verzilverd; de knop hoort weer weg te gaan.
                $set('ai_plan', null);
                $set('ai_briefing', null);

                Notification::make()
                    ->title('De nieuwsbrief staat in het formulier')
                    ->body('Lees hem na voordat je hem verstuurt, en sla daarna op. Er is nog niets opgeslagen.')
                    ->success()
                    ->send();
            });
    }

    private static function beschikbaar(): bool
    {
        return class_exists(Ai::class) && Ai::hasProvider();
    }

    /**
     * Dezelfde keten als NewsletterCampaign::effectiveSiteId(), maar op de
     * formulierstaat: bij een site staat het siteveld verborgen, en bij een
     * campagne zonder site is de lijst leidend.
     */
    private static function siteId(Get $get): ?string
    {
        $siteId = $get('site_id');

        if ($siteId) {
            return (string) $siteId;
        }

        return NewsletterList::find($get('newsletter_list_id'))?->site_id;
    }

    /**
     * Het voorstel uit de formulierstaat van het scherm.
     *
     * Bewust via $livewire en niet via Get: binnen het schema van een modal
     * wijst Get naar de staat van die modal, niet naar het formulier
     * eronder. In action() wijst Get wel naar het formulier, maar dat is een
     * ander evaluatiepunt. Stond dit op Get, dan waren de twee vinklijsten
     * altijd leeg en dus verborgen, en dan kwam er niets aangevinkt terug:
     * fase 2 kreeg een leeg plan en gooide elk productblok weg.
     */
    private static function planVanScherm(mixed $livewire): CampaignPlan
    {
        return CampaignPlan::fromArray(data_get($livewire, 'data.ai_plan'));
    }

    private static function briefingVanScherm(mixed $livewire): CampaignBriefing
    {
        return CampaignBriefing::fromFormData((array) data_get($livewire, 'data.ai_briefing'));
    }

    /** @return array<int, string> */
    private static function keuzes(mixed $livewire, string $soort): array
    {
        $plan = self::planVanScherm($livewire);

        return collect($soort === 'products' ? $plan->products : $plan->articles)
            ->mapWithKeys(fn (array $regel): array => [$regel['id'] => $regel['name']])
            ->all();
    }

    /** @return array<int, string> */
    private static function redenen(mixed $livewire, string $soort): array
    {
        $plan = self::planVanScherm($livewire);

        return collect($soort === 'products' ? $plan->products : $plan->articles)
            ->mapWithKeys(fn (array $regel): array => [$regel['id'] => $regel['reason']])
            ->all();
    }

    /**
     * Een HtmlString en geen kale tekst: Placeholder zet de inhoud
     * ongewijzigd in de HTML, en dan gaan losse regeleindes verloren. De
     * stappen komen van het model, dus ze gaan eerst langs e().
     */
    private static function opbouw(mixed $livewire): HtmlString
    {
        $plan = self::planVanScherm($livewire);

        if ($plan->outline === []) {
            return new HtmlString('De AI stelde geen opbouw voor.');
        }

        return new HtmlString(implode('<br>', array_map(
            fn (int $nummer, string $stap): string => ($nummer + 1) . '. ' . e($stap),
            array_keys($plan->outline),
            $plan->outline,
        )));
    }
}
