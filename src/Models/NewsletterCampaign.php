<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Models;

use Illuminate\Database\Eloquent\Model;
use Dashed\DashedCore\Models\Customsetting;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsletterCampaign extends Model
{
    public const STATUS_CONCEPT = 'concept';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_SENDING = 'sending';
    public const STATUS_SENT = 'sent';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_FAILED = 'failed';

    protected $table = 'dashed__newsletter_campaigns';

    protected $guarded = [];

    protected $attributes = [
        'status' => self::STATUS_CONCEPT,
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'blocks' => 'array',
    ];

    public function list(): BelongsTo
    {
        return $this->belongsTo(NewsletterList::class, 'newsletter_list_id');
    }

    public function segment(): BelongsTo
    {
        return $this->belongsTo(NewsletterSegment::class, 'newsletter_segment_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(NewsletterCampaignRecipient::class, 'newsletter_campaign_id');
    }

    public function links(): HasMany
    {
        return $this->hasMany(NewsletterCampaignLink::class, 'newsletter_campaign_id');
    }

    public function clicks(): HasMany
    {
        return $this->hasMany(NewsletterCampaignClick::class, 'newsletter_campaign_id');
    }

    /**
     * Dezelfde keten als bij een lijst, met de campagne ervoor: eigen adres,
     * anders dat van de lijst, anders dat van de site. Zo stel je het op één
     * plek in en volgt de rest.
     */
    public function effectiveFromEmail(): ?string
    {
        return $this->from_email
            ?: $this->list?->from_email
            ?: Customsetting::get('site_from_email', $this->site_id)
            ?: config('mail.from.address');
    }

    public function effectiveFromName(): ?string
    {
        return $this->from_name
            ?: $this->list?->from_name
            ?: Customsetting::get('site_name', $this->site_id)
            ?: config('mail.from.name');
    }

    /**
     * De site waarop deze campagne blokkadecontrole (NewsletterSuppression)
     * en zichtbaarheid in het beheer draait. Via het scherm staat site_id
     * altijd gelijk aan dat van de gekozen lijst (zie NewsletterCampaignResource
     * en CreateNewsletterCampaign), maar site_id is nullable en het model
     * heeft $guarded = [], dus een campagne die buiten het scherm om
     * aangemaakt wordt (een test, een toekomstige API) kan hem leeg laten.
     * Zonder deze afleiding sloegen CampaignRecipients en CampaignSender de
     * blokkadelijst dan stilzwijgend over: beide keken alleen naar
     * $campaign->site_id en deden niets als die leeg was. Val daarom terug
     * op de lijst waar de campagne aan hangt: die site hoort per definitie
     * hetzelfde te zijn.
     */
    public function effectiveSiteId(): ?string
    {
        return $this->site_id ?: $this->list?->site_id;
    }

    /**
     * Een kopie van deze campagne als nieuw concept.
     *
     * De schone lei staat hier en niet in de knop, want dit is te belangrijk
     * om aan een scherm over te laten. Twee dingen gaan er anders mis. Een
     * kopie die 'status' meeneemt houdt zichzelf voor verzonden en is niet
     * meer te bewerken (zie getEditAuthorizationResponse()). En een kopie die
     * ontvangerregels meeneemt slaat bij het verzenden precies die mensen
     * over: CampaignRecipients::build() raakt alleen 'pending' en 'skipped'
     * aan, dus bestaande 'sent'-regels blijven staan en krijgen niets.
     *
     * Bewust een lijst van wat er NIET meegaat, en niet van wat er wel
     * meegaat: komt er later een kolom bij met inhoud, dan hoort die vanzelf
     * mee te reizen. Komt er een kolom bij die verzendtoestand is, dan moet
     * die hier expliciet bij, en dat is precies het moment waarop iemand
     * erover nadenkt.
     */
    public function duplicate(): self
    {
        $kopie = $this->replicate([
            'status',
            'recipients_count',
            'sent_count',
            'failed_count',
            'rendered_html',
            'started_at',
            'completed_at',
            'scheduled_at',
            'failure_reason',
        ]);

        $kopie->name = 'Kopie van ' . $this->name;
        $kopie->status = self::STATUS_CONCEPT;
        $kopie->recipients_count = 0;
        $kopie->sent_count = 0;
        $kopie->failed_count = 0;
        $kopie->save();

        return $kopie;
    }
}
