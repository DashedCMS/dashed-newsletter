<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Ai\Contracts;

/**
 * Een zoekfunctie die het model tijdens fase 1 mag aanroepen. Bewust klein:
 * hoe heet je, wat kun je, wat neem je aan, en geef het resultaat terug. Wie
 * later een zoekfunctie voor vacatures of evenementen wil, meldt die aan
 * zonder de planner aan te raken.
 *
 * Alleen lezen. Er is geen zoekfunctie waarmee het model iets kan wijzigen, en
 * dat hoort zo te blijven.
 */
interface SearchTool
{
    /** De naam waarmee het model hem aanroept, bijvoorbeeld searchProducts. */
    public function name(): string;

    /** Een of twee zinnen voor het model over wanneer hij hem gebruikt. */
    public function description(): string;

    /** JSON-schema van de invoer, in de vorm die Anthropic verwacht. */
    public function inputSchema(): array;

    /**
     * Voer de zoekopdracht uit. $siteId is de site van de campagne; de
     * zoekfunctie hoort daar zelf op te filteren, want dat is een garantie en
     * een instructie aan het model is een verzoek.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function handle(array $input, ?string $siteId): array;
}
