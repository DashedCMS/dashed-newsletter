<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Segments\Contracts;

use Illuminate\Database\Eloquent\Builder;

interface SegmentCondition
{
    /**
     * Unieke sleutel, met een puntnotatie zodat te zien is welk package hem levert.
     */
    public function key(): string;

    public function label(): string;

    /**
     * Groep waaronder de conditie in het segmentscherm valt.
     */
    public function group(): string;

    /**
     * Filament-velden waarmee deze conditie geconfigureerd wordt.
     *
     * @return array<int, mixed>
     */
    public function schema(): array;

    /**
     * Past de conditie toe op een query van NewsletterSubscriber.
     *
     * @param array<string, mixed> $config
     * @param string $boolean 'and' of 'or'
     */
    public function apply(Builder $query, array $config, string $boolean): void;
}
