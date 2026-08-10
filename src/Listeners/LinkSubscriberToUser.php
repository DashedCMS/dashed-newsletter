<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Listeners;

use Dashed\DashedCore\Models\User;
use Dashed\DashedNewsletter\Models\NewsletterSubscriber;

class LinkSubscriberToUser
{
    public function handle(User $user): void
    {
        if (! $user->email) {
            return;
        }

        // Geen LOWER() om de kolom: hoofdletterongevoeligheid komt al uit de
        // _ci-collatie van de database, en een functie om de kolom heen zou
        // de index op dit e-mailadres onbruikbaar maken.
        NewsletterSubscriber::where('email', $user->email)
            ->whereNull('user_id')
            ->update(['user_id' => $user->id]);
    }
}
