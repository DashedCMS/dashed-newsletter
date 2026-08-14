<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Filament\Livewire;

use Livewire\Component;
use Dashed\DashedNewsletter\Models\NewsletterCampaign;
use Dashed\DashedNewsletter\Campaigns\CampaignRenderer;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Dashed\DashedNewsletter\Models\NewsletterCampaignRecipient;

/**
 * De preview naast het campagneformulier.
 *
 * Rendert door dezelfde CampaignRenderer als de verzendweg. Een eigen weergave
 * hier zou een tweede renderpad zijn, en dan zie je vroeg of laat iets anders
 * dan je verstuurt.
 */
class CampaignPreview extends Component
{
    public int $campaignId;

    public ?int $newsletterListId = null;

    public ?string $subject = null;

    public ?string $preheader = null;

    /** @var array<int, array<string, mixed>> */
    public array $blocks = [];

    public ?int $previewRecipientId = null;

    public string $breedte = 'breed';

    public function html(): string
    {
        // Een campagne in het geheugen, niet uit de database: de redacteur is
        // aan het typen en heeft nog niet opgeslagen. Bewust niet opslaan, want
        // een preview hoort niets te veranderen.
        $campaign = NewsletterCampaign::find($this->campaignId) ?? new NewsletterCampaign();
        $campaign->newsletter_list_id = $this->newsletterListId ?? $campaign->newsletter_list_id;
        $campaign->subject = $this->subject;
        $campaign->preheader = $this->preheader;
        $campaign->blocks = $this->dehydrateRichContent($this->blocks);
        $campaign->setRelation('list', $campaign->list()->first());

        $renderer = app(CampaignRenderer::class);
        $sjabloon = $renderer->renderTemplate($campaign);

        $recipient = $this->previewRecipientId
            ? NewsletterCampaignRecipient::find($this->previewRecipientId)
            : null;

        if (! $recipient) {
            // Geen echt contact gekozen: een losse ontvanger die nergens wordt
            // opgeslagen (geen id, dus $recipient->exists is false), zodat
            // UnsubscribeLink::for() vanzelf naar de testroute wijst in plaats
            // van naar een ondertekende afmeldlink. De terugvalwaarden van de
            // velden zijn dan wel zichtbaar in plaats van kale plaatshouders.
            $recipient = new NewsletterCampaignRecipient([
                'newsletter_campaign_id' => $campaign->id,
                'email' => 'voorbeeld@example.com',
            ]);
            $recipient->setRelation('campaign', $campaign);
        }

        return $renderer->substitute($sjabloon, $recipient);
    }

    /**
     * Zet levende RichEditor-invoer om naar de HTML-string die ook
     * opgeslagen zou worden, vlak voordat CampaignRenderer die te zien
     * krijgt.
     *
     * Filament houdt de staat van een RichEditor tijdens het bewerken altijd
     * als TipTap-document aan (RichEditorStateCast::set() geeft ongeacht
     * isJson() een document terug); pas RichEditorStateCast::get() zet dat
     * bij het opslaan om naar HTML. $this->blocks komt hier rechtstreeks uit
     * de formulierstaat (zie de klassecommentaar), dus TextBlock's
     * 'body'-veld is hier nog een geneste boomstructuur en geen string. In
     * de database staat al gewoon HTML, dus de verzendweg (CampaignSender,
     * CampaignRenderer::render()) heeft hier geen last van; alleen de
     * preview leest de formulierstaat rechtstreeks en loopt hier tegenaan.
     * TextBlock::render() verwacht een string en crasht anders op elke
     * campagne met bestaande tekstblokken.
     *
     * Dit normaliseert alleen de invoer voor de renderer, niet de
     * weergave zelf: CampaignRenderer blijft de enige plek die bepaalt hoe
     * een blok er in de mail uitziet. RichContentRenderer is Filaments eigen
     * omzetting van een TipTap-document naar HTML (dezelfde route als
     * RichEditorStateCast::get() bij isJson() false), dus dit is geen tweede
     * renderpad, alleen een vertaling van formaat.
     *
     * @param array<int, array<string, mixed>> $blocks
     * @return array<int, array<string, mixed>>
     */
    private function dehydrateRichContent(array $blocks): array
    {
        foreach ($blocks as &$block) {
            if (! is_array($block['data'] ?? null)) {
                continue;
            }

            foreach ($block['data'] as $veld => $waarde) {
                // Een TipTap-document herken je aan de wortelknoop: een
                // array met 'type' => 'doc'. Andere veldwaarden (platte
                // tekst, een array van links) hebben die vorm niet.
                if (is_array($waarde) && ($waarde['type'] ?? null) === 'doc') {
                    $block['data'][$veld] = RichContentRenderer::make($waarde)->toHtml();
                }
            }
        }
        unset($block);

        return $blocks;
    }

    public function render()
    {
        return view('dashed-newsletter::filament.campaign-preview', [
            'html' => $this->html(),
        ]);
    }
}
