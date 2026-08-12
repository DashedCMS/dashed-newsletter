<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Listeners;

use Dashed\DashedCore\Models\SentEmail;
use Dashed\DashedNewsletter\Models\NewsletterSuppression;
use Dashed\DashedNewsletter\Models\NewsletterSubscriber;
use Dashed\DashedNewsletter\Models\NewsletterCampaignRecipient;

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

    private function block(SentEmail $mail, string $reason): void
    {
        $email = (string) ($mail->to_email ?? '');

        if ($email === '') {
            return;
        }

        foreach ($this->siteIdsFor($mail, $email) as $siteId) {
            NewsletterSuppression::block($siteId, $email, $reason, 'postmark');
        }
    }

    /**
     * $mail->site_id is hier onbruikbaar: dashed__sent_emails.site_id is een
     * unsignedBigInteger, terwijl de site-id's van dit CMS tekst zijn (zoals
     * 'site'). LogSentEmail schrijft daar (int) Sites::getActive() in, en een
     * tekstuele site-id wordt zo altijd 0. De echte, tekstuele site staat wel
     * op de campagne die deze mail verstuurde.
     *
     * @return array<int, string>
     */
    private function siteIdsFor(SentEmail $mail, string $email): array
    {
        // effectiveSiteId() en niet rechtstreeks ->site_id: een campagne kan
        // buiten het scherm om aangemaakt zijn zonder eigen site_id (zie
        // NewsletterCampaign::effectiveSiteId()), en dan is de site van zijn
        // lijst de enige die er nog is.
        $campagneSite = NewsletterCampaignRecipient::where('sent_email_id', $mail->id)
            ->with('campaign.list:id,site_id')
            ->first()
            ?->campaign
            ?->effectiveSiteId();

        if ($campagneSite !== null) {
            return [(string) $campagneSite];
        }

        // Geen campagne bij deze mail, bijvoorbeeld een bestelbevestiging.
        // Blokkeer dan op elke site waar dit adres als nieuwsbriefcontact
        // bestaat: wie hard bounct hoort nergens meer post te krijgen,
        // ongeacht via welke mail dat aan het licht kwam. Bestaat het adres
        // nergens als contact, dan is er niets te blokkeren.
        return NewsletterSubscriber::query()
            ->where('email', NewsletterSuppression::normalize($email))
            ->with('list:id,site_id')
            ->get()
            ->pluck('list.site_id')
            ->filter(fn (?string $siteId): bool => $siteId !== null)
            ->unique()
            ->values()
            ->all();
    }
}
