<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Commands;

use Illuminate\Console\Command;
use Dashed\DashedNewsletter\Jobs\StartCampaignJob;
use Dashed\DashedNewsletter\Models\NewsletterCampaign;
use Dashed\DashedNewsletter\Campaigns\Exceptions\CampaignNotSendableException;

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
            try {
                StartCampaignJob::dispatchSync($campaign->id);
                $this->info('Gestart: ' . $campaign->name);
            } catch (CampaignNotSendableException $e) {
                // Niet stil laten liggen wachten: een campagne die niet
                // verzendbaar is blijft anders elke minuut opnieuw geprobeerd
                // worden zonder dat iemand het ziet.
                $campaign->update(['status' => NewsletterCampaign::STATUS_FAILED]);
                $this->error($campaign->name . ': ' . $e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
