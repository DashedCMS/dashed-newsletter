<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Campaigns;

use Dashed\DashedNewsletter\Models\NewsletterCampaign;
use Dashed\DashedNewsletter\Models\NewsletterCampaignRecipient;

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

        $headerBlocks = $list?->header_blocks ?? [];
        $footerBlocks = $list?->footer_blocks ?? [];

        $blocks = array_merge(
            $this->renderBlocks($headerBlocks, $context),
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
            // Header, campagne-inhoud en footer tellen allemaal mee: sinds de
            // header ook bewerkbaar is (NewsletterListResource) kan een
            // afmeldblok net zo goed daar staan. Kijk dan alleen naar
            // $footerBlocks, dan mist dat geval en plakt de standaardregel
            // hieronder er een tweede afmeldlink bij.
            'hasUnsubscribeBlock' => $this->heeftAfmeldblok($headerBlocks)
                || $this->heeftAfmeldblok($campaign->blocks ?? [])
                || $this->heeftAfmeldblok($footerBlocks),
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
     * @param array<int, array<string, mixed>> $blocks
     */
    private function heeftAfmeldblok(array $blocks): bool
    {
        foreach ($blocks as $block) {
            if (($block['type'] ?? null) === 'unsubscribe') {
                return true;
            }
        }

        return false;
    }

    public function render(NewsletterCampaign $campaign, NewsletterCampaignRecipient $recipient): string
    {
        return $this->substitute($this->renderTemplate($campaign), $recipient);
    }

    /**
     * Vervangt de plaatshouders van één ontvanger in een al gerenderd sjabloon.
     *
     * Dit is bewust gescheiden van renderTemplate(): de verzendweg rendert één
     * keer per ronde en roept dit per ontvanger aan. Een productblok bevraagt
     * de webshop dus één keer en niet één keer per ontvanger.
     */
    public function substitute(string $html, NewsletterCampaignRecipient $recipient): string
    {
        $waarden = CampaignPersonalisation::valuesFor($recipient);
        $waarden['unsubscribe_url'] = UnsubscribeLink::for($recipient);

        return preg_replace_callback(
            '/:(\w+):/',
            fn (array $m): string => array_key_exists($m[1], $waarden) ? $waarden[$m[1]] : $m[0],
            $html
        );
    }
}
