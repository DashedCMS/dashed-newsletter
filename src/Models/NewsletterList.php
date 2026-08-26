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

    /**
     * Eloquent leest de standaardwaarden van de database niet in, dus zonder
     * dit staat track_opens op null op een vers aangemaakte lijst, en dat is
     * falsy. Meten hoort juist aan te staan tot iemand het uitzet, dus die
     * stand moet ook hier staan en niet alleen in de migratie.
     */
    protected $attributes = [
        'track_opens' => true,
        'track_clicks' => true,
    ];

    protected $casts = [
        'settings' => 'array',
        'notify_on_subscribe' => 'boolean',
        'notify_on_unsubscribe' => 'boolean',
        'header_blocks' => 'array',
        'footer_blocks' => 'array',
        'track_opens' => 'boolean',
        'track_clicks' => 'boolean',
        'send_rate_per_minute' => 'integer',
    ];

    /**
     * Het tempo waarmee deze lijst verstuurt, in mails per minuut.
     *
     * Leeg op de lijst betekent: volg de instelling van de site. Nul op de
     * lijst betekent uitdrukkelijk geen begrenzing, en dat is iets anders dan
     * leeg. Zonder dat onderscheid kan een beheerder de standaard niet
     * uitzetten voor een lijst waar hij hem niet wil.
     */
    public function effectiveSendRatePerMinute(): int
    {
        $eigen = $this->send_rate_per_minute;

        if ($eigen !== null) {
            return max(0, (int) $eigen);
        }

        return max(0, (int) config('dashed-newsletter.send_rate_per_minute', 0));
    }

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

    /**
     * De kleuren waarmee deze lijst verstuurt, per kleur terugvallend op de
     * e-mailinstellingen van de site. Per kleur en niet als geheel: wie alleen
     * de primaire kleur overschrijft, hoort de rest gewoon uit de instellingen
     * te krijgen.
     *
     * @return array{primary: string, text: string, background: string, logo: string|null}
     */
    public function brandingColors(): array
    {
        return [
            'primary' => $this->mail_primary_color
                ?: (Customsetting::get('mail_primary_color', $this->site_id) ?: '#A0131C'),
            'text' => $this->mail_text_color
                ?: Customsetting::get('mail_text_color', $this->site_id, '#ffffff'),
            'background' => $this->mail_background_color
                ?: Customsetting::get('mail_background_color', $this->site_id, '#f3f4f6'),
            'logo' => $this->mail_logo
                ?: (Customsetting::get('mail_logo', $this->site_id) ?: Customsetting::get('site_logo', $this->site_id)),
        ];
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
