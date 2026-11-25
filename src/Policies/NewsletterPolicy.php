<?php

namespace Dashed\DashedNewsletter\Policies;

use Dashed\DashedCore\Policies\BaseResourcePolicy;

/**
 * Eén set rechten voor de hele nieuwsbrief: lijsten, contacten, campagnes en
 * de blokkadelijst. Ze horen bij hetzelfde werk, en losse rechten per scherm
 * zouden een rol vooral half laten werken (een campagne zonder zijn lijst).
 */
class NewsletterPolicy extends BaseResourcePolicy
{
    protected function resourceName(): string
    {
        return 'Newsletter';
    }
}
