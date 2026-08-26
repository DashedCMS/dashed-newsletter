<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Commands;

use Illuminate\Console\Command;

/**
 * Verouderd: campagnekliks staan aangemeld in het bewaartermijnenregister en
 * worden nu opgeruimd door dashed:prune. Dit command blijft bestaan als
 * alias, zodat een bestaande cronregel op een productieserver niet in één
 * klap kapot gaat.
 *
 * Alleen de losse klikken verdwijnen. De tellers op de ontvangerregel blijven
 * staan, en dat is de bedoeling: die zijn de cijfers van de campagne, en die
 * horen niet met een opruimtaak te verdampen. Wat je na een jaar verliest is
 * de uitsplitsing per link, niet het totaal.
 */
class PruneCampaignClicks extends Command
{
    protected $signature = 'dashed:prune-campaign-clicks';

    protected $description = 'Verouderd. Gebruik dashed:prune. Verwijder losse kliks van nieuwsbriefcampagnes ouder dan de bewaartermijn.';

    public function handle(): int
    {
        return $this->call('dashed:prune', [
            '--only' => 'campaign_clicks',
        ]);
    }
}
