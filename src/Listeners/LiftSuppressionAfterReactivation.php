<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Listeners;

use Dashed\DashedNewsletter\Models\NewsletterSuppression;

/**
 * Een beheerder heeft een adres bij Postmark geheractiveerd. Dan hoort onze
 * eigen blokkade op een bounce of klacht ook weg, anders krijgt het adres van
 * de nieuwsbrief alsnog niets.
 *
 * Over alle sites heen, want Postmark heeft één lijst per server. Een
 * marktplaatsadres (Bol) en een handmatige blokkade blijven altijd staan: dat
 * zijn keuzes, geen bezorgfeiten, en die heft Postmark niet voor ons op.
 */
class LiftSuppressionAfterReactivation
{
    public const LIFTABLE_REASONS = [
        NewsletterSuppression::REASON_BOUNCE,
        NewsletterSuppression::REASON_COMPLAINT,
    ];

    public function handle(object $event): void
    {
        NewsletterSuppression::query()
            ->where('email', NewsletterSuppression::normalize((string) $event->email))
            ->whereIn('reason', self::LIFTABLE_REASONS)
            ->delete();
    }
}
