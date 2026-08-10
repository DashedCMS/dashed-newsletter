<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Segments;

use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Builder;
use Dashed\DashedNewsletter\Models\NewsletterSegment;
use Dashed\DashedNewsletter\Models\NewsletterSubscriber;
use Dashed\DashedNewsletter\Segments\Exceptions\EmptySegmentException;

class SegmentQuery
{
    /**
     * Vertaalt de regelboom van een segment naar een query op de contacten van
     * de lijst waar het segment bij hoort.
     */
    public static function for(NewsletterSegment $segment): Builder
    {
        $query = NewsletterSubscriber::query()
            ->where('newsletter_list_id', $segment->newsletter_list_id);

        $rules = $segment->rules ?: [];

        // Bewust géén stille terugval op "dan maar iedereen". Een segment zonder
        // voorwaarden is niet "de hele lijst", het is een segment dat nog niet
        // af is; het stil uitrekenen tot de hele lijst is precies het scenario
        // waar applyCondition() hieronder ook tegen beschermt.
        if (! static::containsCondition($rules)) {
            throw EmptySegmentException::forSegment((string) $segment->name);
        }

        static::applyGroup($query, $rules, 'and');

        return $query;
    }

    /**
     * Of er ergens in de boom een blad met een echte voorwaarde zit. Een groep
     * met alleen lege groepen eronder telt niet: die levert net zo goed geen
     * enkele beperking op.
     *
     * @param array<string, mixed> $node
     */
    private static function containsCondition(array $node): bool
    {
        if (isset($node['children'])) {
            foreach ($node['children'] as $child) {
                if (is_array($child) && static::containsCondition($child)) {
                    return true;
                }
            }

            return false;
        }

        return ($node['condition'] ?? '') !== '';
    }

    public static function count(NewsletterSegment $segment): int
    {
        return static::for($segment)->count();
    }

    /**
     * Telling voor in het scherm. De sleutel bevat een hash van de regels, zodat
     * een gewijzigde regelboom vanzelf een nieuwe telling krijgt en de
     * verversknop alleen nodig is als de contacten zijn veranderd, niet de
     * regels. Een tijdstip zou hier niet volstaan: de telling hangt af van de
     * regels, niet van het moment waarop ze zijn opgeslagen, en twee
     * wijzigingen binnen dezelfde seconde zouden anders dezelfde sleutel delen.
     */
    public static function cachedCount(NewsletterSegment $segment, bool $forget = false): int
    {
        $key = 'newsletter.segment.' . $segment->id . '.' . md5(json_encode($segment->rules ?? []));

        if ($forget) {
            Cache::forget($key);
        }

        return Cache::remember($key, now()->addMinutes(10), fn () => static::count($segment));
    }

    /**
     * @param array<string, mixed> $group
     */
    private static function applyGroup(Builder $query, array $group, string $boolean): void
    {
        $operator = ($group['operator'] ?? 'and') === 'or' ? 'or' : 'and';
        $children = $group['children'] ?? [];

        $query->where(function (Builder $nested) use ($children, $operator): void {
            foreach ($children as $index => $child) {
                // De eerste voorwaarde in een groep is altijd 'and', anders zou
                // een groep die met 'or' begint de rest van de query openbreken.
                $childBoolean = $index === 0 ? 'and' : $operator;

                if (isset($child['children'])) {
                    static::applyGroup($nested, $child, $childBoolean);

                    continue;
                }

                static::applyCondition($nested, $child, $childBoolean);
            }
        }, null, null, $boolean);
    }

    /**
     * @param array<string, mixed> $leaf
     */
    private static function applyCondition(Builder $query, array $leaf, string $boolean): void
    {
        $key = $leaf['condition'] ?? '';

        // Bewust géén stille overslag. Een segment dat "besteld voor meer dan 250"
        // bevat zou zonder die conditie de hele lijst worden, en dan gaat er straks
        // een mail naar duizenden mensen die hem niet hadden moeten krijgen.
        $condition = app(SegmentConditionRegistry::class)->get($key);

        $condition->apply($query, $leaf, $boolean);
    }
}
