<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Ai;

use Dashed\DashedAi\Facades\Ai;
use Dashed\DashedNewsletter\Ai\Exceptions\AiGenerationFailedException;

/**
 * Fase 1: het model zoekt zelf en komt met een voorstel.
 *
 * Zelfde lusvorm als ChatAgentRunner in dashed-livechat: aanroepen, kijken of
 * stop_reason 'tool_use' is, elk tool_use-blok uitvoeren, de resultaten als
 * een user-beurt terugsturen. Bewust geen gedeelde basisklasse met die runner:
 * die werkt op een gesprek met een bezoeker en een agentmodel, deze op een
 * eenmalige opdracht. Ze delen een vorm, geen verantwoordelijkheid.
 */
final class CampaignPlanner
{
    public function __construct(private SearchToolRegistry $tools)
    {
    }

    public function plan(CampaignBriefing $briefing, ?string $siteId): CampaignPlan
    {
        if (! Ai::hasProvider()) {
            throw new AiGenerationFailedException('Er is geen AI-koppeling ingesteld.');
        }

        $schema = $this->tools->anthropicSchema();
        $maxRondes = (int) config('dashed-newsletter.ai.max_search_rounds', 8);
        $messages = [['role' => 'user', 'content' => $briefing->toPrompt()]];

        for ($ronde = 0; $ronde <= $maxRondes; $ronde++) {
            $options = [
                'system' => $this->systemPrompt(),
                'max_tokens' => 4096,
            ];

            // De laatste beurt krijgt geen gereedschap mee. Zonder die
            // uitzondering kan het model blijven zoeken tot de lus op is en
            // komt er nooit een voorstel uit; nu is de laatste beurt een
            // vraag om het plan en niets anders.
            if ($ronde < $maxRondes && $schema !== []) {
                $options['tools'] = $schema;
            }

            $response = Ai::messages($messages, $options);

            if (! is_array($response)) {
                throw new AiGenerationFailedException('De AI gaf geen antwoord.');
            }

            $content = is_array($response['content'] ?? null) ? $response['content'] : [];
            $messages[] = ['role' => 'assistant', 'content' => $content];

            if (($response['stop_reason'] ?? null) !== 'tool_use') {
                return $this->planUit($content);
            }

            $messages[] = ['role' => 'user', 'content' => $this->zoek($content, $siteId)];
        }

        throw new AiGenerationFailedException('De AI bleef zoeken zonder met een voorstel te komen.');
    }

    private function planUit(array $content): CampaignPlan
    {
        $raw = $this->decode($content);

        if ($raw === null) {
            throw new AiGenerationFailedException('De AI gaf geen bruikbaar voorstel terug.');
        }

        $plan = CampaignPlan::fromArray($raw);

        if ($plan->isEmpty()) {
            throw new AiGenerationFailedException('De AI kwam niet met een voorstel. Pas de briefing aan en probeer opnieuw.');
        }

        return $plan;
    }

    /**
     * Voert elk tool_use-blok uit en bouwt er een user-beurt van. Onbekend
     * gereedschap levert een foutregel op en geen uitzondering: het model mag
     * zichzelf herstellen, en een verzonnen naam hoort de hele generatie niet
     * om te gooien.
     *
     * @return array<int, array<string, mixed>>
     */
    private function zoek(array $content, ?string $siteId): array
    {
        $resultaten = [];

        foreach ($content as $block) {
            if (! is_array($block) || ($block['type'] ?? null) !== 'tool_use') {
                continue;
            }

            $tool = $this->tools->get((string) ($block['name'] ?? ''));
            $invoer = is_array($block['input'] ?? null) ? $block['input'] : [];

            $resultaat = $tool
                ? $tool->handle($invoer, $siteId)
                : ['error' => 'onbekende zoekfunctie'];

            $resultaten[] = [
                'type' => 'tool_result',
                'tool_use_id' => (string) ($block['id'] ?? ''),
                'content' => json_encode($resultaat, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ];
        }

        return $resultaten;
    }

    /**
     * Haalt de JSON uit de tekstblokken. Een eigen kopie en geen hergebruik
     * van AiProvider::parseJsonResponse(): die is protected en zit vast aan
     * een provider-instantie.
     */
    private function decode(array $content): ?array
    {
        $tekst = trim(collect($content)
            ->filter(fn (mixed $block): bool => is_array($block) && ($block['type'] ?? null) === 'text')
            ->pluck('text')
            ->implode("\n"));

        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/i', $tekst, $match)) {
            $tekst = trim($match[1]);
        }

        $decoded = json_decode($tekst, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
            Je stelt een nieuwsbrief samen voor een Nederlandse webshop. In deze stap
            schrijf je nog niets: je onderzoekt en je komt met een voorstel.

            Gebruik de zoekfuncties om te kijken wat er te promoten valt. Zoek gericht,
            niet uitputtend: een handvol zoekopdrachten hoort genoeg te zijn. Kies alleen
            uit wat de zoekfuncties je teruggeven. Verzin nooit een product of artikel,
            en verzin nooit een id.

            Als je genoeg weet, antwoord je met UITSLUITEND geldig JSON, zonder
            markdown-codeblok en zonder tekst eromheen, in exact deze vorm:

            {
              "products": [{"id": 12, "name": "Naam", "reason": "Waarom deze"}],
              "articles": [{"id": 3, "name": "Titel", "reason": "Waarom dit"}],
              "outline": ["Korte omschrijving van het eerste onderdeel", "Het tweede"],
              "subject_direction": "Een richting voor de onderwerpregel, geen definitieve regel"
            }

            Levert een zoekfunctie niets op, laat de lijst dan leeg en stel een
            nieuwsbrief voor zonder dat onderdeel. Een lege lijst is een prima antwoord;
            een verzonnen id is dat niet.
            PROMPT;
    }
}
