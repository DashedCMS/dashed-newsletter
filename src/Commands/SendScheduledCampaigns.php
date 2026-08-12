<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Commands;

use Illuminate\Console\Command;
use Dashed\DashedNewsletter\Campaigns\CampaignGuard;
use Dashed\DashedNewsletter\Jobs\StartCampaignJob;
use Dashed\DashedNewsletter\Models\NewsletterCampaign;

class SendScheduledCampaigns extends Command
{
    protected $signature = 'dashed:send-scheduled-campaigns';

    protected $description = 'Start campagnes waarvan het ingeplande tijdstip bereikt is';

    public function handle(): int
    {
        $campaigns = NewsletterCampaign::where('status', NewsletterCampaign::STATUS_SCHEDULED)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->get();

        foreach ($campaigns as $campaign) {
            $problem = CampaignGuard::problem($campaign);

            if ($problem !== null) {
                // Niet stil laten liggen wachten: een campagne die niet
                // verzendbaar is blijft anders elke minuut opnieuw geprobeerd
                // worden zonder dat iemand het ziet.
                $campaign->update(['status' => NewsletterCampaign::STATUS_FAILED]);
                $this->error($campaign->name . ': ' . $problem);

                continue;
            }

            // Bewust dispatch() en niet dispatchSync(): dit command draait
            // binnen schedule:run, dat elke minuut-taak van elk pakket in
            // deze monorepo na elkaar afhandelt. Synchroon starten zou de
            // hele startflow van StartCampaignJob (ontvangers vastleggen,
            // porties versturen) in dat proces laten lopen en zo de
            // minuut-tick blokkeren tot de laatste ontvanger van de grootste
            // wachtende campagne verwerkt is. De scheduler hoort een
            // campagne in gang te zetten, niet uit te voeren.
            //
            // Restrisico: tussen de controle hierboven en het echt draaien
            // van de job kan de campagne alsnog onverzendbaar worden (een
            // gelijktijdige wijziging, een verlopen segment). StartCampaignJob
            // controleert via CampaignGuard opnieuw en gooit dan alsnog
            // CampaignNotSendableException; die mislukking komt in de
            // failed-jobs-tabel terecht in plaats van hier afgevangen te
            // worden. Aanvaard: dat venster is klein en de faalstand is
            // zichtbaar, niet stil.
            StartCampaignJob::dispatch($campaign->id);
            $this->info('Gestart: ' . $campaign->name);
        }

        return self::SUCCESS;
    }
}
