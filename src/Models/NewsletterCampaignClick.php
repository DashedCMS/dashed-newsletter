<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Een klik. Een rij per klik en geen teller, zodat totaal, uniek en per link
 * alle drie te beantwoorden zijn.
 */
class NewsletterCampaignClick extends Model
{
    protected $table = 'dashed__newsletter_campaign_clicks';

    protected $guarded = [];

    protected $casts = [
        'clicked_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(NewsletterCampaign::class, 'newsletter_campaign_id');
    }

    public function link(): BelongsTo
    {
        return $this->belongsTo(NewsletterCampaignLink::class, 'newsletter_campaign_link_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(NewsletterCampaignRecipient::class, 'newsletter_campaign_recipient_id');
    }
}
