<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsletterSubscriberEvent extends Model
{
    public const TYPE_SUBSCRIBED = 'subscribed';
    public const TYPE_CONFIRMED = 'confirmed';
    public const TYPE_UPDATED = 'updated';
    public const TYPE_UNSUBSCRIBED = 'unsubscribed';
    public const TYPE_IMPORTED = 'imported';
    public const TYPE_CLEANED = 'cleaned';

    protected $table = 'dashed__newsletter_subscriber_events';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $event): void {
            $event->created_at = $event->created_at ?: now();
        });
    }

    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(NewsletterSubscriber::class, 'newsletter_subscriber_id');
    }
}
