<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Dashed\DashedNewsletter\Models\NewsletterSubscriber;

/**
 * Iemand heeft zich aangemeld, of is na een uitschrijving weer actief geworden.
 *
 * Bewust alleen bij een echte aanmelding en niet bij een overname uit een andere
 * aanbieder: die loopt via import() en zou anders bij duizenden contacten
 * evenzoveel meldingen opleveren.
 */
class NewsletterSubscribedEvent
{
    use Dispatchable;

    public function __construct(public NewsletterSubscriber $subscriber)
    {
    }
}
