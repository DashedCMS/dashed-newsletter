<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsletterSegment extends Model
{
    protected $table = 'dashed__newsletter_segments';

    protected $guarded = [];

    protected $casts = [
        'rules' => 'array',
    ];

    public function list(): BelongsTo
    {
        return $this->belongsTo(NewsletterList::class, 'newsletter_list_id');
    }
}
