<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Adressen die van deze site nooit meer een nieuwsbrief krijgen.
 *
 * Blokkeren is iets anders dan uitschrijven. Uitschrijven is een keuze van de
 * ontvanger en staat op het contact, zichtbaar in zijn tijdlijn. Blokkeren is
 * een technisch feit over alle lijsten van een site heen, ook lijsten waar dat
 * adres nu nog niet op staat. Vandaar een eigen tabel op adres en niet een
 * vlaggetje op het contact.
 */
class NewsletterSuppression extends Model
{
    public const REASON_BOUNCE = 'bounce';
    public const REASON_COMPLAINT = 'complaint';
    public const REASON_MANUAL = 'manual';

    protected $table = 'dashed__newsletter_suppressions';

    protected $guarded = [];

    public static function normalize(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    public static function blocks(string $siteId, string $email): bool
    {
        return static::where('site_id', $siteId)
            ->where('email', static::normalize($email))
            ->exists();
    }

    /**
     * De eerste reden blijft staan bij een tweede melding: dat is wat er als
     * eerste misging, en dat is wat je wilt kunnen laten zien.
     */
    public static function block(string $siteId, string $email, string $reason, ?string $source = null): self
    {
        return static::firstOrCreate(
            ['site_id' => $siteId, 'email' => static::normalize($email)],
            ['reason' => $reason, 'source' => $source]
        );
    }
}
