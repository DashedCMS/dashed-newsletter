<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Commands;

use Illuminate\Support\Carbon;
use Illuminate\Console\Command;
use Dashed\DashedNewsletter\Models\NewsletterCampaignClick;

/**
 * Ruimt losse klikregels op. Zelfde vorm als PruneSentEmailsCommand in
 * dashed-core.
 *
 * Alleen de losse klikken verdwijnen. De tellers op de ontvangerregel blijven
 * staan, en dat is de bedoeling: die zijn de cijfers van de campagne, en die
 * horen niet met een opruimtaak te verdampen. Wat je na een jaar verliest is
 * de uitsplitsing per link, niet het totaal.
 */
class PruneCampaignClicks extends Command
{
    protected $signature = 'dashed:prune-campaign-clicks';

    protected $description = 'Verwijder losse kliks van nieuwsbriefcampagnes ouder dan de bewaartermijn.';

    public function handle(): int
    {
        $dagen = (int) config('dashed-newsletter.clicks.retention_days', 365);

        if ($dagen < 1) {
            $dagen = 365;
        }

        $aantal = NewsletterCampaignClick::where('clicked_at', '<', Carbon::now()->subDays($dagen))->delete();

        $this->info("{$aantal} klik(ken) ouder dan {$dagen} dagen verwijderd.");

        return self::SUCCESS;
    }
}
