<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Models;

use Dashed\DashedCore\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsletterSubscriber extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_UNSUBSCRIBED = 'unsubscribed';
    public const STATUS_CLEANED = 'cleaned';

    protected $table = 'dashed__newsletter_subscribers';

    protected $guarded = [];

    protected $casts = [
        'subscribed_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'unsubscribed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $subscriber): void {
            $subscriber->subscribed_at = $subscriber->subscribed_at ?: now();

            // De koppeling met een account is een gevolg, geen invoer: een contact
            // hoort ook te bestaan zonder dat er ooit een account bij komt.
            // Geen LOWER() om de kolom: hoofdletterongevoeligheid komt al uit de
            // _ci-collatie van de database, en een functie om de kolom heen zou
            // de index op users.email onbruikbaar maken.
            if (! $subscriber->user_id) {
                $subscriber->user_id = User::where('email', $subscriber->email)->value('id');
            }
        });
    }

    public function list(): BelongsTo
    {
        return $this->belongsTo(NewsletterList::class, 'newsletter_list_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fieldValues(): HasMany
    {
        return $this->hasMany(NewsletterFieldValue::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(NewsletterSubscriberEvent::class)->latest();
    }

    public function consents(): HasMany
    {
        return $this->hasMany(NewsletterConsent::class);
    }
}
