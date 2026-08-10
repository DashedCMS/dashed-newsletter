<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Segments;

use Dashed\DashedNewsletter\Segments\Contracts\SegmentCondition;
use Dashed\DashedNewsletter\Segments\Exceptions\UnknownSegmentConditionException;

class SegmentConditionRegistry
{
    /** @var array<string, SegmentCondition> */
    private array $conditions = [];

    public function register(SegmentCondition $condition): void
    {
        $this->conditions[$condition->key()] = $condition;
    }

    public function has(string $key): bool
    {
        return isset($this->conditions[$key]);
    }

    public function get(string $key): SegmentCondition
    {
        if (! $this->has($key)) {
            throw UnknownSegmentConditionException::forKey($key);
        }

        return $this->conditions[$key];
    }

    /** @return array<string, SegmentCondition> */
    public function all(): array
    {
        return $this->conditions;
    }
}
