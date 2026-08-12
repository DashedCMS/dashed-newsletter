<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Listeners;

use Dashed\DashedNewsletter\Models\NewsletterSuppression;

class SuppressBouncedAddress
{
    /**
     * Alleen een harde bounce blokkeert. Postmark noemt de rest SoftBounce of
     * Transient, en dat is meestal een volle mailbox: tijdelijk, en geen reden
     * om iemand voorgoed uit te sluiten.
     */
    public function bounced(object $event): void
    {
        $type = (string) ($event->payload['Type'] ?? '');

        if ($type !== 'HardBounce') {
            return;
        }

        $this->block($event->mail, NewsletterSuppression::REASON_BOUNCE);
    }

    public function complained(object $event): void
    {
        $this->block($event->mail, NewsletterSuppression::REASON_COMPLAINT);
    }

    private function block(object $mail, string $reason): void
    {
        $email = (string) ($mail->to_email ?? '');
        $siteId = (string) ($mail->site_id ?? '');

        if ($email === '' || $siteId === '') {
            return;
        }

        NewsletterSuppression::block($siteId, $email, $reason, 'postmark');
    }
}
