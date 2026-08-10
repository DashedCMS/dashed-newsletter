<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsletterField extends Model
{
    public const TYPE_TEXT = 'text';
    public const TYPE_NUMBER = 'number';
    public const TYPE_DATE = 'date';
    public const TYPE_SELECT = 'select';
    public const TYPE_CHECKBOX = 'checkbox';

    protected $table = 'dashed__newsletter_fields';

    protected $guarded = [];

    protected $casts = [
        'options' => 'array',
        'required' => 'boolean',
        'show_in_signup_form' => 'boolean',
    ];

    public function list(): BelongsTo
    {
        return $this->belongsTo(NewsletterList::class, 'newsletter_list_id');
    }

    /**
     * De placeholder waarmee dit veld in een campagne gebruikt wordt.
     */
    public function placeholder(): string
    {
        return ':' . $this->key . ':';
    }
}
