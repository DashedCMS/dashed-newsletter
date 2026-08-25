<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Ai;

/**
 * Het voorstel uit fase 1. Reist als array door de formulierstaat naar het
 * goedkeuringsscherm en terug, dus fromArray() moet net zo goed overweg
 * kunnen met wat een model verzint als met zijn eigen toArray().
 */
final class CampaignPlan
{
    /**
     * @param array<int, array{id: int, name: string, reason: string}> $products
     * @param array<int, array{id: int, name: string, reason: string}> $articles
     * @param array<int, string> $outline
     */
    public function __construct(
        public readonly array $products,
        public readonly array $articles,
        public readonly array $outline,
        public readonly string $subjectDirection,
    ) {
    }

    /** @param array<string, mixed>|null $raw */
    public static function fromArray(?array $raw): self
    {
        $raw ??= [];

        return new self(
            products: self::items($raw['products'] ?? []),
            articles: self::items($raw['articles'] ?? []),
            outline: self::outline($raw['outline'] ?? []),
            subjectDirection: trim((string) ($raw['subject_direction'] ?? '')),
        );
    }

    public function toArray(): array
    {
        return [
            'products' => $this->products,
            'articles' => $this->articles,
            'outline' => $this->outline,
            'subject_direction' => $this->subjectDirection,
        ];
    }

    /**
     * Leeg betekent: hier valt geen nieuwsbrief van te maken. Een plan met
     * alleen een opbouw is dus niet leeg, want een site zonder webshop hoort
     * gewoon een nieuwsbrief te kunnen laten schrijven.
     */
    public function isEmpty(): bool
    {
        return $this->products === [] && $this->articles === [] && $this->outline === [];
    }

    /** @return array<int, int> */
    public function productIds(): array
    {
        return array_column($this->products, 'id');
    }

    /** @return array<int, int> */
    public function articleIds(): array
    {
        return array_column($this->articles, 'id');
    }

    /**
     * Het plan zoals de redacteur het goedkeurde. Wat hij eruit haalde is
     * daarna nergens meer te vinden, en dat is de bedoeling: fase 2 kan er
     * niet bij.
     *
     * @param array<int, int|string> $productIds
     * @param array<int, int|string> $articleIds
     */
    public function only(array $productIds, array $articleIds): self
    {
        $producten = array_map('intval', $productIds);
        $artikelen = array_map('intval', $articleIds);

        return new self(
            products: array_values(array_filter(
                $this->products,
                fn (array $regel): bool => in_array($regel['id'], $producten, true),
            )),
            articles: array_values(array_filter(
                $this->articles,
                fn (array $regel): bool => in_array($regel['id'], $artikelen, true),
            )),
            outline: $this->outline,
            subjectDirection: $this->subjectDirection,
        );
    }

    public function toPrompt(): string
    {
        $tekst = '';

        if ($this->products !== []) {
            $tekst .= "Goedgekeurde producten (gebruik uitsluitend deze id's):\n";
            foreach ($this->products as $regel) {
                $tekst .= '- id ' . $regel['id'] . ': ' . $regel['name']
                    . ($regel['reason'] !== '' ? ' (' . $regel['reason'] . ')' : '') . "\n";
            }
        }

        if ($this->articles !== []) {
            $tekst .= "\nGoedgekeurde artikelen (gebruik uitsluitend deze id's):\n";
            foreach ($this->articles as $regel) {
                $tekst .= '- id ' . $regel['id'] . ': ' . $regel['name']
                    . ($regel['reason'] !== '' ? ' (' . $regel['reason'] . ')' : '') . "\n";
            }
        }

        if ($this->outline !== []) {
            $tekst .= "\nGoedgekeurde opbouw:\n";
            foreach ($this->outline as $stap) {
                $tekst .= '- ' . $stap . "\n";
            }
        }

        if ($this->subjectDirection !== '') {
            $tekst .= "\nRichting voor het onderwerp: " . $this->subjectDirection . "\n";
        }

        return trim($tekst);
    }

    /** @return array<int, array{id: int, name: string, reason: string}> */
    private static function items(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $regels = [];

        foreach ($raw as $regel) {
            // Een regel zonder id is onbruikbaar: fase 2 kan er geen blok mee
            // vullen en de redacteur kan hem niet aanvinken.
            if (! is_array($regel) || ! isset($regel['id']) || ! is_numeric($regel['id'])) {
                continue;
            }

            $regels[] = [
                'id' => (int) $regel['id'],
                'name' => trim((string) ($regel['name'] ?? '')),
                'reason' => trim((string) ($regel['reason'] ?? '')),
            ];
        }

        return $regels;
    }

    /** @return array<int, string> */
    private static function outline(mixed $raw): array
    {
        if (is_string($raw)) {
            return trim($raw) === '' ? [] : [trim($raw)];
        }

        if (! is_array($raw)) {
            return [];
        }

        return array_values(array_filter(
            array_map(fn (mixed $stap): string => trim((string) (is_scalar($stap) ? $stap : '')), $raw),
            fn (string $stap): bool => $stap !== '',
        ));
    }
}
