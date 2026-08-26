<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Een link uit een campagnemail.
 *
 * Deze rij is de enige bron voor de doelURL van de klikroute. Zou die URL uit
 * het verzoek komen, dan is de klikroute een open redirect en staat er binnen
 * een week een phishinglink in omloop met het domein van de webshop ervoor.
 */
class NewsletterCampaignLink extends Model
{
    protected $table = 'dashed__newsletter_campaign_links';

    protected $guarded = [];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(NewsletterCampaign::class, 'newsletter_campaign_id');
    }

    public function clicks(): HasMany
    {
        return $this->hasMany(NewsletterCampaignClick::class, 'newsletter_campaign_link_id');
    }
}
