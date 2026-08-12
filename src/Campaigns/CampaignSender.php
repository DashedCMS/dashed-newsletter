<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Campaigns;

use Illuminate\Support\Facades\Mail;
use Dashed\DashedNewsletter\Mail\NewsletterCampaignMail;
use Dashed\DashedNewsletter\Models\NewsletterSubscriber;
use Dashed\DashedNewsletter\Models\NewsletterSuppression;
use Dashed\DashedNewsletter\Models\NewsletterCampaignRecipient;

/**
 * Verstuurt één regel uit de ontvangerstabel.
 *
 * De controles hier zijn bewust een herhaling van wat CampaignRecipients al
 * deed. Tussen het samenstellen van de lijst en het versturen van de laatste
 * portie kan iemand zich afmelden of geblokkeerd raken, en bij duizenden
 * ontvangers is die tijd lang genoeg dat dat gebeurt.
 */
class CampaignSender
{
    public static function send(NewsletterCampaignRecipient $recipient): void
    {
        // Al afgehandeld: niet nog eens. Dit is de grendel die een herstart van
        // de wachtrij ongevaarlijk maakt.
        if ($recipient->status !== NewsletterCampaignRecipient::STATUS_PENDING) {
            return;
        }

        $subscriber = $recipient->subscriber;
        $campaign = $recipient->campaign;

        if (! $subscriber || $subscriber->status !== NewsletterSubscriber::STATUS_ACTIVE) {
            self::skip($recipient, NewsletterCampaignRecipient::SKIP_UNSUBSCRIBED);

            return;
        }

        $siteId = (string) ($campaign->site_id ?? '');

        if ($siteId !== '' && NewsletterSuppression::blocks($siteId, $recipient->email)) {
            self::skip($recipient, NewsletterCampaignRecipient::SKIP_SUPPRESSED);

            return;
        }

        try {
            Mail::to($recipient->email)->send(new NewsletterCampaignMail($campaign, $recipient));
        } catch (\Throwable $e) {
            $recipient->update([
                'status' => NewsletterCampaignRecipient::STATUS_FAILED,
                'skip_reason' => mb_substr($e->getMessage(), 0, 255),
            ]);
            $campaign->increment('failed_count');

            return;
        }

        $recipient->update([
            'status' => NewsletterCampaignRecipient::STATUS_SENT,
            'sent_at' => now(),
        ]);
        $campaign->increment('sent_count');
    }

    private static function skip(NewsletterCampaignRecipient $recipient, string $reason): void
    {
        $recipient->update([
            'status' => NewsletterCampaignRecipient::STATUS_SKIPPED,
            'skip_reason' => $reason,
        ]);
        $recipient->campaign?->increment('skipped_count');
    }
}
