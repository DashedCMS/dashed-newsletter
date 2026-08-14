<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Campaigns;

use Dashed\DashedNewsletter\Models\NewsletterCampaign;

/**
 * Zet een campagne om in de HTML die verstuurd wordt.
 *
 * renderTemplate() laat de plaatshouders (:voornaam:, :unsubscribe_url:) staan.
 * Dat is met opzet: de verzendweg rendert één keer per ronde en vervangt daarna
 * alleen nog per ontvanger. Zie CampaignPersonalisation.
 */
class CampaignRenderer
{
    public function renderTemplate(NewsletterCampaign $campaign): string
    {
        $list = $campaign->list;
        $kleuren = $list?->brandingColors() ?? [
            'primary' => '#A0131C',
            'text' => '#ffffff',
            'background' => '#f3f4f6',
            'logo' => null,
        ];

        $context = [
            'primaryColor' => $kleuren['primary'],
            'textColor' => $kleuren['text'],
            'backgroundColor' => $kleuren['background'],
            'siteName' => $list?->name,
        ];

        $footerBlocks = $list?->footer_blocks ?? [];

        $blocks = array_merge(
            $this->renderBlocks($list?->header_blocks ?? [], $context),
            $this->renderCampaignBody($campaign, $context),
            $this->renderBlocks($footerBlocks, $context),
        );

        return view('dashed-newsletter::emails.shell', [
            'subject' => (string) $campaign->subject,
            'preheader' => $campaign->preheader,
            'blocks' => $blocks,
            'backgroundColor' => $kleuren['background'],
            'primaryColor' => $kleuren['primary'],
            'listName' => $list?->name,
            'hasUnsubscribeBlock' => $this->heeftAfmeldblok($footerBlocks),
        ])->render();
    }

    /**
     * @param array<int, array<string, mixed>> $blocks
     * @param array<string, mixed> $context
     * @return array<int, string>
     */
    private function renderBlocks(array $blocks, array $context): array
    {
        $registry = cms()->emailBlocks();
        $gerenderd = [];

        foreach ($blocks as $block) {
            $type = $block['type'] ?? null;

            // Een blok waarvan het pakket niet meer geïnstalleerd is, wordt
            // overgeslagen in plaats van de hele nieuwsbrief te laten klappen.
            if (! $type || ! isset($registry[$type])) {
                continue;
            }

            $gerenderd[] = $registry[$type]::render($block['data'] ?? [], $context);
        }

        return $gerenderd;
    }

    /**
     * @param array<string, mixed> $context
     * @return array<int, string>
     */
    private function renderCampaignBody(NewsletterCampaign $campaign, array $context): array
    {
        $blocks = $campaign->blocks ?? [];

        if ($blocks !== []) {
            return $this->renderBlocks($blocks, $context);
        }

        // Campagnes van vóór dit project hebben alleen rich-editor-inhoud.
        // Die moeten gewoon door kunnen verzenden, ook als ze al ingepland
        // stonden toen deze wijziging werd uitgerold.
        if (blank($campaign->content)) {
            return [];
        }

        return ['<tr><td style="padding:24px;">' . $campaign->content . '</td></tr>'];
    }

    /**
     * @param array<int, array<string, mixed>> $footerBlocks
     */
    private function heeftAfmeldblok(array $footerBlocks): bool
    {
        foreach ($footerBlocks as $block) {
            if (($block['type'] ?? null) === 'unsubscribe') {
                return true;
            }
        }

        return false;
    }
}
