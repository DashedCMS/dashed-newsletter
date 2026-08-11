<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Dashed\DashedCore\Models\Customsetting;
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

    /**
     * Het adres waarmee deze lijst verstuurt. Een lijst hoeft er geen eigen te
     * hebben: is hij leeg, dan geldt het adres uit de algemene instellingen van
     * de site, en anders dat uit de mailconfiguratie. Zo hoeft een redacteur bij
     * een nieuwe lijst niets in te vullen wat elders al klopt.
     */
    public function effectiveFromEmail(): ?string
    {
        return $this->from_email
            ?: Customsetting::get('site_from_email', $this->site_id)
            ?: config('mail.from.address');
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
