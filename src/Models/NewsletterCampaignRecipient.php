<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    /**
     * Een regel die 'sending' stond op het moment dat de campagne werd
     * afgebroken (CampaignCanceller): een worker had hem net geclaimd, en we
     * weten niet of de mail de deur uit is vóór de worker omviel of vóór het
     * afbreken. Bewust een andere eindtoestand dan STATUS_SKIPPED: een
     * geskipte regel is zeker nooit verstuurd en mag bij een herstart gewoon
     * opnieuw beoordeeld worden (CampaignRecipients::build() doet dat ook
     * alleen voor 'pending' en 'skipped'). Deze regel weten we dat niet van,
     * en bij die onzekerheid kiezen we voor nooit meer versturen in plaats
     * van het risico op een tweede mail. Daarom een status die build() nooit
     * aanraakt, net als 'sent' en 'failed'.
     */
    public const STATUS_INTERRUPTED = 'interrupted';

    public const SKIP_UNSUBSCRIBED = 'unsubscribed';
    public const SKIP_SUPPRESSED = 'suppressed';
    public const SKIP_INVALID_EMAIL = 'invalid_email';
    public const SKIP_CANCELLED = 'cancelled';

    /**
     * De redenen die een ontvanger op de afmeldpagina kan kiezen. Kort
     * gehouden: een lange lijst levert minder bruikbare antwoorden op dan een
     * handvol dat de meeste gevallen dekt, met daarnaast een vrij veld.
     */
    public const REASON_TOO_OFTEN = 'te_vaak';
    public const REASON_NOT_RELEVANT = 'niet_relevant';
    public const REASON_NEVER_SIGNED_UP = 'nooit_aangemeld';
    public const REASON_TOO_MANY_MAILS = 'te_veel_mail';
    public const REASON_OTHER = 'anders';

    /** @return array<string, string> */
    public static function unsubscribeReasons(): array
    {
        return [
            self::REASON_TOO_OFTEN => 'Ik krijg te vaak mail',
            self::REASON_NOT_RELEVANT => 'De inhoud is niet relevant voor mij',
            self::REASON_NEVER_SIGNED_UP => 'Ik heb me hier nooit voor aangemeld',
            self::REASON_TOO_MANY_MAILS => 'Ik wil sowieso minder mail',
            self::REASON_OTHER => 'Anders',
        ];
    }

    protected $table = 'dashed__newsletter_campaign_recipients';

    protected $guarded = [];

    /**
     * Eloquent leest de standaardwaarden van de database niet in, dus zonder
     * dit staan de tellers op null op een vers aangemaakte regel. Nul is wat
     * ze zijn: er is nog niet geopend en niet geklikt.
     */
    protected $attributes = [
        'open_count' => 0,
        'click_count' => 0,
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'bounced_at' => 'datetime',
        'complained_at' => 'datetime',
        'opened_at' => 'datetime',
        'clicked_at' => 'datetime',
        'unsubscribed_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(NewsletterCampaign::class, 'newsletter_campaign_id');
    }

    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(NewsletterSubscriber::class, 'newsletter_subscriber_id');
    }

    public function clicks(): HasMany
    {
        return $this->hasMany(NewsletterCampaignClick::class, 'newsletter_campaign_recipient_id');
    }
}
