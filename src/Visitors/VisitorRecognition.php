<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Visitors;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Cookie\CookieValuePrefix;
use Symfony\Component\HttpFoundation\Cookie;
use Dashed\DashedNewsletter\Segments\SegmentQuery;
use Dashed\DashedNewsletter\Models\NewsletterSegment;
use Dashed\DashedNewsletter\Models\NewsletterSubscriber;
use Dashed\DashedNewsletter\Models\NewsletterSuppression;

/**
 * Een bezoeker herkennen die via een link in de nieuwsbrief binnenkwam, zodat
 * een popup op een segment kan richten.
 *
 * Alleen als de lijst het toestaat (`recognize_visitors`, standaard uit):
 * dit is het volgen van een bezoeker, en de privacyverklaring van de site
 * moet het noemen voordat de schakelaar aan gaat. De cookie bevat alleen het
 * abonnee-id, versleuteld, en leeft dertig dagen. Een afgemelde, opgeschoonde
 * of geblokkeerde abonnee wordt nooit herkend, ook niet met een geldige
 * cookie, en afmelden wist de cookie.
 *
 * De klikroute staat buiten de web-groep (geen sessie, geen CSRF), dus daar
 * versleutelt EncryptCookies niets. Deze klasse versleutelt de waarde daarom
 * zelf, op precies de manier van EncryptCookies, zodat de web-groep hem op
 * elke gewone pagina gewoon ontsleutelt en `$request->cookie()` het id geeft.
 */
class VisitorRecognition
{
    public const COOKIE = 'dashed_newsletter_visitor';

    public const MINUTES = 60 * 24 * 30;

    public static function cookieFor(NewsletterSubscriber $subscriber): Cookie
    {
        $value = Crypt::encryptString(CookieValuePrefix::create(self::COOKIE, Crypt::getKey()) . $subscriber->getKey());

        return cookie(self::COOKIE, $value, self::MINUTES, '/', null, null, true, false, 'lax');
    }

    public static function forgetCookie(): Cookie
    {
        return cookie()->forget(self::COOKIE);
    }

    /**
     * Mag deze abonnee herkend worden: actief, op een lijst die het toestaat,
     * en niet geblokkeerd.
     */
    public static function mayRecognize(?NewsletterSubscriber $subscriber): bool
    {
        if (! $subscriber || $subscriber->status !== NewsletterSubscriber::STATUS_ACTIVE) {
            return false;
        }

        $list = $subscriber->list;

        if (! $list || ! $list->recognize_visitors) {
            return false;
        }

        return ! NewsletterSuppression::blocks((string) $list->site_id, (string) $subscriber->email);
    }

    /**
     * De herkende abonnee van dit verzoek, per verzoek onthouden.
     */
    public static function subscriber(?Request $request = null): ?NewsletterSubscriber
    {
        $request ??= request();
        $memoKey = 'dashed.newsletter.visitor';

        if ($request->attributes->has($memoKey)) {
            return $request->attributes->get($memoKey);
        }

        $id = $request->cookie(self::COOKIE);
        $subscriber = is_string($id) && ctype_digit($id) ? NewsletterSubscriber::with('list')->find((int) $id) : null;
        $subscriber = static::mayRecognize($subscriber) ? $subscriber : null;

        $request->attributes->set($memoKey, $subscriber);

        return $subscriber;
    }

    /**
     * Valt de bezoeker in dit segment: als herkende abonnee (cookie), of met
     * een van de gegeven e-mailadressen (ingelogde klant, adres uit een
     * popup) als contact op de lijst van het segment. Een segment zonder
     * voorwaarden of dat niet bestaat levert nee op, nooit "iedereen".
     *
     * @param  array<int, string|null>  $emails
     */
    public static function inSegment(int $segmentId, array $emails = []): bool
    {
        $segment = NewsletterSegment::find($segmentId);

        if (! $segment) {
            return false;
        }

        $subscriber = static::subscriber();
        $emails = array_values(array_unique(array_filter(array_map(
            fn ($email) => is_string($email) ? strtolower(trim($email)) : null,
            $emails,
        ))));

        if (! $subscriber && $emails === []) {
            return false;
        }

        return (bool) rescue(function () use ($segment, $subscriber, $emails): bool {
            return SegmentQuery::for($segment)
                ->where('status', NewsletterSubscriber::STATUS_ACTIVE)
                ->where(function ($query) use ($subscriber, $emails): void {
                    if ($subscriber) {
                        $query->orWhere('id', $subscriber->getKey());
                    }

                    if ($emails !== []) {
                        $query->orWhereIn('email', $emails);
                    }
                })
                ->exists();
        }, false, false);
    }
}
