<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Ai;

/**
 * Wat fase 2 oplevert. Gaat rechtstreeks de formulierstaat in en wordt niet
 * opgeslagen: wie het scherm sluit zonder opslaan, verandert niets.
 */
final class CampaignDraft
{
    /** @param array<int, array{type: string, data: array}> $blocks */
    public function __construct(
        public readonly string $name,
        public readonly string $subject,
        public readonly string $preheader,
        public readonly array $blocks,
    ) {
    }
}
