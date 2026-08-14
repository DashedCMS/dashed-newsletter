<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Filament\Livewire;

use Livewire\Component;
use Dashed\DashedNewsletter\Models\NewsletterCampaign;
use Dashed\DashedNewsletter\Campaigns\CampaignRenderer;
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
        $campaign->blocks = $this->blocks;
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

    public function render()
    {
        return view('dashed-newsletter::filament.campaign-preview', [
            'html' => $this->html(),
        ]);
    }
}
