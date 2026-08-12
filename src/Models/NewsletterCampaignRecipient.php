<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eén ontvanger van één campagne.
 *
 * Deze tabel maakt verzenden herhaalbaar. Valt de wachtrij halverwege om, dan
 * staat hier wie er al post heeft gehad, en kan een herstart gewoon verder
 * zonder iemand twee keer te mailen.
 */
class NewsletterCampaignRecipient extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_SENDING = 'sending';
    public const STATUS_SENT = 'sent';
    public const STATUS_SKIPPED = 'skipped';
    public const STATUS_FAILED = 'failed';

    public const SKIP_UNSUBSCRIBED = 'unsubscribed';
    public const SKIP_SUPPRESSED = 'suppressed';
    public const SKIP_INVALID_EMAIL = 'invalid_email';

    protected $table = 'dashed__newsletter_campaign_recipients';

    protected $guarded = [];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(NewsletterCampaign::class, 'newsletter_campaign_id');
    }

    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(NewsletterSubscriber::class, 'newsletter_subscriber_id');
    }
}
