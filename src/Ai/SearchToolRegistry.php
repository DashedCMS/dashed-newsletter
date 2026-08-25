<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Ai;

use Dashed\DashedNewsletter\Ai\Contracts\SearchTool;

/**
 * Zelfde vorm als SegmentConditionRegistry: het nieuwsbriefpakket hoort niet
 * te weten dat er een webshop of een artikelenmodule bestaat. Die pakketten
 * melden zichzelf aan.
 */
class SearchToolRegistry
{
    /** @var array<string, SearchTool> */
    private array $tools = [];

    public function register(SearchTool $tool): void
    {
        $this->tools[$tool->name()] = $tool;
    }

    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    public function get(string $name): ?SearchTool
    {
        return $this->tools[$name] ?? null;
    }

    /** @return array<string, SearchTool> */
    public function all(): array
    {
        return $this->tools;
    }

    /**
     * Het gereedschapsschema voor Anthropic. Geen aparte laag nodig:
     * ClaudeProvider::messages() geeft dit ongewijzigd door als `tools`.
     *
     * @return array<int, array{name: string, description: string, input_schema: array}>
     */
    public function anthropicSchema(): array
    {
        return array_values(array_map(fn (SearchTool $tool): array => [
            'name' => $tool->name(),
            'description' => $tool->description(),
            'input_schema' => $tool->inputSchema(),
        ], $this->tools));
    }
}
