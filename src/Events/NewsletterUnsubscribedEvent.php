<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Dashed\DashedNewsletter\Models\NewsletterSubscriber;

/**
 * Iemand heeft zich uitgeschreven.
 *
 * Alleen bij een echte overgang naar uitgeschreven, niet bij het opnieuw
 * opslaan van een contact dat al uitgeschreven was.
 */
class NewsletterUnsubscribedEvent
{
    use Dispatchable;

    public function __construct(public NewsletterSubscriber $subscriber)
    {
    }
}
