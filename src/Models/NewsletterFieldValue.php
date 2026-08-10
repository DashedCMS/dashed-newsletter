<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsletterFieldValue extends Model
{
    protected $table = 'dashed__newsletter_field_values';

    protected $guarded = [];

    protected $casts = [
        'value_date' => 'date',
    ];

    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(NewsletterSubscriber::class, 'newsletter_subscriber_id');
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(NewsletterField::class, 'newsletter_field_id');
    }

    /**
     * Schrijft een waarde weg en vult daarbij de getypeerde kolom die bij het
     * veldtype hoort. Zonder die kolommen vergelijkt een segment als tekst, en
     * dan valt 90 euro binnen "meer dan 250".
     */
    public static function writeValue(
        NewsletterSubscriber $subscriber,
        NewsletterField $field,
        mixed $value
    ): self {
        $text = ($value === null || $value === '') ? null : (string) $value;

        return static::updateOrCreate(
            [
                'newsletter_subscriber_id' => $subscriber->id,
                'newsletter_field_id' => $field->id,
            ],
            [
                'value' => $text,
                'value_number' => static::toNumber($field, $text),
                'value_date' => static::toDate($field, $text),
            ]
        );
    }

    private static function toNumber(NewsletterField $field, ?string $text): ?float
    {
        if ($text === null || $field->type !== NewsletterField::TYPE_NUMBER) {
            return null;
        }

        return is_numeric($text) ? (float) $text : null;
    }

    private static function toDate(NewsletterField $field, ?string $text): ?string
    {
        if ($text === null || $field->type !== NewsletterField::TYPE_DATE) {
            return null;
        }

        try {
            return CarbonImmutable::parse($text)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
