<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Filament\Forms;

use Dashed\DashedNewsletter\Segments\SegmentConditionRegistry;
use Dashed\DashedNewsletter\Segments\Contracts\SegmentCondition;

class SegmentRuleBuilder
{
    /**
     * @return array<string, array<string, SegmentCondition>>
     */
    public static function conditionsByGroup(): array
    {
        $groups = [];

        foreach (app(SegmentConditionRegistry::class)->all() as $key => $condition) {
            $groups[$condition->group()][$key] = $condition;
        }

        return $groups;
    }

    /**
     * Of de regelboom ergens de opgegeven conditiesleutel gebruikt.
     *
     * @param  array<string, mixed>  $rules
     */
    public static function usesConditionKey(array $rules, string $key): bool
    {
        if (isset($rules['children'])) {
            foreach ($rules['children'] as $child) {
                if (is_array($child) && static::usesConditionKey($child, $key)) {
                    return true;
                }
            }

            return false;
        }

        return ($rules['condition'] ?? null) === $key;
    }

    /**
     * Sleutels in de regelboom waarvoor geen conditie meer geregistreerd is.
     * Het scherm toont deze zodat zichtbaar is wat er stuk is; overslaan zou
     * het segment stilzwijgend de hele lijst maken.
     *
     * @param  array<string, mixed>  $rules
     * @return array<int, string>
     */
    public static function missingConditionKeys(array $rules): array
    {
        $registry = app(SegmentConditionRegistry::class);
        $missing = [];

        $walk = function (array $node) use (&$walk, $registry, &$missing): void {
            if (isset($node['children'])) {
                foreach ($node['children'] as $child) {
                    $walk($child);
                }

                return;
            }

            $key = $node['condition'] ?? null;

            if ($key && ! $registry->has($key)) {
                $missing[] = $key;
            }
        };

        $walk($rules);

        return array_values(array_unique($missing));
    }
}
