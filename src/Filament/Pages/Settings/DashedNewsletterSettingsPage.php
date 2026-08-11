<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Filament\Pages\Settings;

use UnitEnum;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Dashed\DashedCore\Classes\Sites;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Tabs;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Tabs\Tab;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedNewsletter\Facades\Newsletter;
use Dashed\DashedNewsletter\Models\NewsletterList;
use Dashed\DashedCore\Traits\HasSettingsPermission;

/**
 * Bewust een magere pagina. Afzender, antwoordadres, soort aanmelding en de
 * meldingen bij aan- en afmelden horen bij een lijst en staan daar ook, dus
 * hier staat alleen wat over de lijsten heen gaat.
 */
class DashedNewsletterSettingsPage extends Page
{
    use HasSettingsPermission;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-envelope-open';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationLabel = 'Nieuwsbrief instellingen';

    protected static string | UnitEnum | null $navigationGroup = 'Systeem';

    protected static ?string $title = 'Nieuwsbrief instellingen';

    protected string $view = 'dashed-core::settings.pages.default-settings';

    public array $data = [];

    public function mount(): void
    {
        $formData = [];

        foreach (Sites::getSites() as $site) {
            $formData["newsletter_default_list_id_{$site['id']}"] = Customsetting::get('newsletter_default_list_id', $site['id']);
        }

        $this->form->fill($formData);
    }

    /**
     * Knoppen die andere pakketten hebben aangemeld, per site. Het
     * nieuwsbriefpakket weet niet welke dat zijn: dat het overnemen uit Laposta
     * hier staat, is iets wat dashed-laposta zelf regelt. Zou deze pagina die
     * koppeling bij naam kennen, dan hing het nieuwsbriefpakket aan een
     * aanbieder, en dat is precies wat het niet moet.
     */
    protected function getHeaderActions(): array
    {
        $actions = [];

        foreach (Sites::getSites() as $site) {
            foreach (Newsletter::settingsActions($site['id']) as $action) {
                $actions[] = Sites::getAmountOfSites() > 1
                    ? $action->label($action->getLabel() . ' (' . $site['name'] . ')')
                    : $action;
            }
        }

        return $actions;
    }

    public function form(Schema $schema): Schema
    {
        $tabs = [];

        foreach (Sites::getSites() as $site) {
            $tabs[] = Tab::make($site['id'])
                ->label(ucfirst($site['name']))
                ->schema([
                    Select::make("newsletter_default_list_id_{$site['id']}")
                        ->label('Standaardlijst')
                        ->helperText('De lijst die vooraf gekozen staat als je een formulier aan de nieuwsbrief koppelt, en waar aanmeldingen op uitkomen die geen lijst meegeven. Laat leeg als je dat per keer wilt kiezen.')
                        ->options(NewsletterList::forSite($site['id'])->pluck('name', 'id')->all())
                        ->placeholder('Geen standaardlijst')
                        ->columnSpanFull(),
                ]);
        }

        return $schema->schema([Tabs::make('Sites')->tabs($tabs)])->statePath('data');
    }

    public function submit(): void
    {
        $formState = $this->form->getState();

        foreach (Sites::getSites() as $site) {
            Customsetting::set(
                'newsletter_default_list_id',
                $formState["newsletter_default_list_id_{$site['id']}"] ?? null,
                $site['id']
            );
        }

        $this->form->fill($formState);

        Notification::make()->title('De nieuwsbrief instellingen zijn opgeslagen')->success()->send();
    }
}
