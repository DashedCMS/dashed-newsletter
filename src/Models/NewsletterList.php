<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NewsletterList extends Model
{
    protected $table = 'dashed__newsletter_lists';

    protected $guarded = [];

    protected $casts = [
        'settings' => 'array',
        'notify_on_subscribe' => 'boolean',
        'notify_on_unsubscribe' => 'boolean',
    ];

    public function scopeForSite(Builder $query, ?string $siteId): Builder
    {
        return $query->where('site_id', $siteId);
    }

    public function subscribers(): HasMany
    {
        return $this->hasMany(NewsletterSubscriber::class);
    }

    public function fields(): HasMany
    {
        return $this->hasMany(NewsletterField::class)->orderBy('sort');
    }

    public function segments(): HasMany
    {
        return $this->hasMany(NewsletterSegment::class);
    }
}
