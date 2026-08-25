<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Ai;

/**
 * De antwoorden op het briefingformulier. Een object en geen losse array,
 * zodat fase 1 en fase 2 allebei van dezelfde velden uitgaan: fase 2 moet
 * weten wat de bedoeling was, anders schrijft hij netjes over het verkeerde.
 */
final class CampaignBriefing
{
    public const LENGTHS = [
        'kort' => 'Kort',
        'gemiddeld' => 'Gemiddeld',
        'uitgebreid' => 'Uitgebreid',
    ];

    public function __construct(
        public readonly string $audience,
        public readonly string $occasion,
        public readonly string $promote,
        public readonly string $length,
        public readonly string $instruction,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromFormData(array $data): self
    {
        $length = (string) ($data['length'] ?? 'gemiddeld');

        return new self(
            audience: trim((string) ($data['audience'] ?? '')),
            occasion: trim((string) ($data['occasion'] ?? '')),
            promote: trim((string) ($data['promote'] ?? '')),
            // Een lengte die niet bestaat komt anders letterlijk in het prompt
            // terecht, en dat is een open deur voor wat er in dat veld getypt
            // wordt.
            length: array_key_exists($length, self::LENGTHS) ? $length : 'gemiddeld',
            instruction: trim((string) ($data['instruction'] ?? '')),
        );
    }

    public function toPrompt(): string
    {
        $regels = array_filter([
            'Voor wie' => $this->audience,
            'Aanleiding' => $this->occasion,
            'Wat promoten' => $this->promote,
            'Gewenste lengte' => self::LENGTHS[$this->length],
            'Eigen aanwijzing van de redacteur' => $this->instruction,
        ], fn (string $waarde): bool => $waarde !== '');

        $tekst = '';
        foreach ($regels as $label => $waarde) {
            $tekst .= $label . ': ' . $waarde . "\n";
        }

        return trim($tekst);
    }
}
