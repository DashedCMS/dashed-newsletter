<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Filament\Pages;

use UnitEnum;
use BackedEnum;
use Filament\Pages\Page;
use Dashed\DashedNewsletter\Models\NewsletterList;
use Dashed\DashedNewsletter\Campaigns\UnsubscribeReasons;
use Dashed\DashedNewsletter\Models\NewsletterCampaignRecipient;

/**
 * Waarom mensen zich afmelden, over alle lijsten heen.
 *
 * Per campagne staat dit op de bekijkpagina van die campagne en per lijst op
 * het lijstscherm. Dit overzicht is er voor de vraag die daar niet te
 * beantwoorden is: valt er over de hele nieuwsbrief heen een patroon te zien.
 */
class UnsubscribeReasonsPage extends Page
{
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-hand-raised';

    protected static string | UnitEnum | null $navigationGroup = 'Communicatie';

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'Afmeldredenen';

    protected static ?string $title = 'Waarom mensen zich afmelden';

    protected string $view = 'dashed-newsletter::filament.unsubscribe-reasons-page';

    /** @var string|int|null */
    public $lijstId = null;

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $lijst = $this->lijstId ? NewsletterList::find($this->lijstId) : null;

        return [
            'lijsten' => NewsletterList::orderBy('name')->pluck('name', 'id')->all(),
            'gekozenLijst' => $lijst,
            'totaal' => UnsubscribeReasons::total(list: $lijst),
            'zonderReden' => UnsubscribeReasons::withoutReason(list: $lijst),
            'redenen' => UnsubscribeReasons::totals(list: $lijst),
            'toelichtingen' => UnsubscribeReasons::comments(list: $lijst),
            'omschrijvingen' => NewsletterCampaignRecipient::unsubscribeReasons(),
        ];
    }
}
