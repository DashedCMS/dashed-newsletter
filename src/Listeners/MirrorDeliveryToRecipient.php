<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Listeners;

use Dashed\DashedCore\Models\SentEmail;
use Dashed\DashedNewsletter\Models\NewsletterCampaignRecipient;

/**
 * Spiegelt bezorging, bounces en klachten van een verzonden mail naar de
 * ontvangerregel van de campagne.
 *
 * Waarom spiegelen en niet gewoon joinen op sent_email_id: dashed__sent_emails
 * wordt na negentig dagen opgeruimd (PruneSentEmailsCommand). Statistieken die
 * live uit die tabel komen werken drie maanden en staan daarna stilzwijgend op
 * nul, met een oude campagne die beweert dat er nooit iets is aangekomen.
 */
class MirrorDeliveryToRecipient
{
    public function delivered(object $event): void
    {
        // Alleen zetten als hij nog leeg is: Postmark kan een gebeurtenis
        // opnieuw aanbieden, en het eerste bezorgmoment is het echte.
        $this->stempel($event->mail, fn (NewsletterCampaignRecipient $regel): array => [
            'delivered_at' => $regel->delivered_at ?? now(),
        ]);
    }

    public function bounced(object $event): void
    {
        $reden = $event->payload['Description'] ?? ($event->payload['Details'] ?? null);

        $this->stempel($event->mail, fn (NewsletterCampaignRecipient $regel): array => [
            'bounced_at' => $regel->bounced_at ?? now(),
            'bounce_reason' => $reden !== null ? mb_substr((string) $reden, 0, 255) : $regel->bounce_reason,
        ]);
    }

    public function complained(object $event): void
    {
        $this->stempel($event->mail, fn (NewsletterCampaignRecipient $regel): array => [
            'complained_at' => $regel->complained_at ?? now(),
        ]);
    }

    /**
     * @param callable(NewsletterCampaignRecipient): array<string, mixed> $waarden
     */
    private function stempel(SentEmail $mail, callable $waarden): void
    {
        // Geen ontvangerregel betekent: dit was geen campagnemail maar
        // bijvoorbeeld een bestelbevestiging. Dan valt er niets te spiegelen.
        $regel = NewsletterCampaignRecipient::where('sent_email_id', $mail->id)->first();

        if (! $regel) {
            return;
        }

        $regel->forceFill($waarden($regel))->save();
    }
}
