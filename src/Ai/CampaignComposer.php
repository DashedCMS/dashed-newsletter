<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Ai;

use Dashed\DashedAi\Facades\Ai;
use Dashed\DashedCore\Classes\ContentStudio\ContentStudioGenerator;
use Dashed\DashedNewsletter\Ai\Exceptions\AiGenerationFailedException;

/**
 * Fase 2: het goedgekeurde plan wordt een nieuwsbrief.
 *
 * Wat eruit komt gaat langs twee filters, en allebei zijn ze code en geen
 * instructie:
 *
 * 1. de catalogus, via ContentStudioGenerator::normalize(): onbekende
 *    bloktypen en veldnamen vallen weg. Omdat de catalogus is gebouwd uit
 *    NewsletterCampaignResource::newsletterBlocks() kan er ook nooit een
 *    transactioneel blok in belanden;
 * 2. het goedgekeurde plan: elke verwijzing naar een product of artikel dat de
 *    redacteur eruit haalde valt weg. Dit is de belofte van de
 *    goedkeuringsstap, en die wordt afgedwongen, niet gevraagd.
 */
final class CampaignComposer
{
    /**
     * De velden waarin een blok naar gekozen items verwijst, met de kant van
     * het plan waar ze tegen gehouden worden.
     */
    private const ID_VELDEN = [
        'product_ids' => 'productIds',
        'article_ids' => 'articleIds',
    ];

    /** @param array<int, array> $catalog */
    public function compose(
        CampaignPlan $plan,
        CampaignBriefing $briefing,
        array $catalog,
        string $adjustment = '',
    ): CampaignDraft {
        $antwoord = Ai::json($this->prompt($plan, $briefing, $catalog, $adjustment));

        if (! is_array($antwoord)) {
            throw new AiGenerationFailedException('De AI gaf geen bruikbare nieuwsbrief terug.');
        }

        $blocks = (new ContentStudioGenerator())->normalize($antwoord['blocks'] ?? null, $catalog);
        $blocks = $this->beperkTotPlan($blocks, $plan);

        if ($blocks === []) {
            throw new AiGenerationFailedException('De AI gaf geen bruikbare blokken terug. Pas de bijsturing aan en probeer opnieuw.');
        }

        return new CampaignDraft(
            // Een campagne zonder naam is in het overzicht niet terug te
            // vinden, dus liever een saaie terugval dan een lege regel.
            name: trim((string) ($antwoord['name'] ?? '')) ?: 'Nieuwsbrief',
            // Terugval op de richting die fase 1 al bedacht en die de redacteur
            // heeft gezien. Beter dat dan leeg: een leeg onderwerp overschrijft
            // in het scherm wat er stond, en dan kost de AI je werk in plaats
            // van dat hij het oplevert. Blijft ook dat leeg, dan is leeg het
            // eerlijke antwoord en laat het scherm het bestaande met rust.
            subject: trim((string) ($antwoord['subject'] ?? '')) ?: $plan->subjectDirection,
            preheader: trim((string) ($antwoord['preheader'] ?? '')),
            blocks: $blocks,
        );
    }

    /**
     * @param array<int, array{type: string, data: array}> $blocks
     * @return array<int, array{type: string, data: array}>
     */
    private function beperkTotPlan(array $blocks, CampaignPlan $plan): array
    {
        $schoon = [];

        foreach ($blocks as $block) {
            $data = is_array($block['data'] ?? null) ? $block['data'] : [];
            $leeggelopen = false;

            foreach (self::ID_VELDEN as $veld => $methode) {
                if (! array_key_exists($veld, $data)) {
                    continue;
                }

                // array_intersect houdt de volgorde van de eerste array aan,
                // dus de volgorde die het model koos blijft staan.
                $data[$veld] = array_values(array_intersect(
                    array_map('intval', (array) $data[$veld]),
                    $plan->{$methode}(),
                ));

                if ($data[$veld] === []) {
                    $leeggelopen = true;
                }
            }

            // Een productblok zonder producten rendert een lege string. Zo'n
            // blok in het formulier zetten geeft de redacteur iets te zien dat
            // in de mail niet bestaat.
            if ($leeggelopen) {
                continue;
            }

            $schoon[] = ['type' => $block['type'], 'data' => $data];
        }

        return $schoon;
    }

    /** @param array<int, array> $catalog */
    private function prompt(CampaignPlan $plan, CampaignBriefing $briefing, array $catalog, string $adjustment): string
    {
        $catalogusJson = json_encode($catalog, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $bijsturing = $adjustment !== ''
            ? "Bijsturing van de redacteur (deze gaat voor):\n" . $adjustment
            : 'De redacteur heeft geen bijsturing meegegeven.';

        return <<<PROMPT
            Je schrijft een nieuwsbrief voor een Nederlandse webshop. Het onderzoek is
            gedaan en de redacteur heeft het voorstel hieronder goedgekeurd. Schrijf nu
            de mail.

            De oorspronkelijke briefing:
            {$briefing->toPrompt()}

            {$plan->toPrompt()}

            {$bijsturing}

            Beschikbare blokken (JSON, met per blok de toegestane velden en types):
            {$catalogusJson}

            Regels:
            - Gebruik uitsluitend bloktypen en veldnamen uit de lijst.
            - Verwijs uitsluitend naar de goedgekeurde id's. Een ander id valt eruit.
            - Zet product_ids en article_ids als een lijst getallen.
            - Vul alleen velden die je zinvol kunt invullen.
            - Schrijf in het Nederlands, in de je-vorm, zonder uitroeptekens.
            - Zet geen afmeldlink, webversielink of sociale pictogrammen in de blokken:
              die zitten vast in de mail zelf.

            Het onderwerp en de preheader zijn geen bijzaak: daar wordt op besloten of
            de mail geopend wordt, nog voordat iemand een blok ziet.

            - Bedenk altijd een onderwerpregel. Concreet en specifiek, ongeveer dertig
              tot vijfenvijftig tekens, zodat hij op een telefoon niet afgekapt wordt.
              Geen clickbait, geen woord "nieuwsbrief", en niet in hoofdletters.
            - Bedenk altijd een preheader. Dat is de regel die een mailbox naast het
              onderwerp toont. Vul het onderwerp aan, herhaal het niet, en maak hem
              ongeveer veertig tot negentig tekens.
            - Kreeg je hierboven een richting voor het onderwerp mee, volg die dan,
              maar schrijf zelf de definitieve regel.

            Antwoord met JSON in exact deze vorm, zonder markdown-codeblok:
            {"name": "<korte naam voor in het overzicht>", "subject": "<onderwerpregel>", "preheader": "<preheader>", "blocks": [{"type": "<bloktype>", "data": { ...velden... }}]}
            PROMPT;
    }
}
