<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Mail\Mailables\Envelope;
use Dashed\DashedNewsletter\Campaigns\UnsubscribeLink;
use Dashed\DashedNewsletter\Campaigns\CampaignRenderer;
use Dashed\DashedNewsletter\Models\NewsletterCampaign;
use Dashed\DashedNewsletter\Models\NewsletterCampaignRecipient;

class NewsletterCampaignMail extends Mailable
{
    public function __construct(
        public NewsletterCampaign $campaign,
        public NewsletterCampaignRecipient $recipient,
        // Genoemd $renderedHtml en niet $html: Illuminate\Mail\Mailable
        // declareert zelf al een protected $html-property (gevuld door
        // Content::htmlString via de basisklasse), en een eigen public $html
        // hier botst daarmee met een fatale "must not be defined"-fout.
        public ?string $renderedHtml = null,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(
                (string) $this->campaign->effectiveFromEmail(),
                $this->campaign->effectiveFromName() ?: null
            ),
            replyTo: $this->campaign->reply_to_email
                ? [new Address($this->campaign->reply_to_email)]
                : [],
            // Ook het onderwerp door de vervanging halen. Een redacteur die
            // ":voornaam:" in de inhoud zet, doet dat net zo goed in het
            // onderwerp, en dat is de eerste regel die een ontvanger ziet.
            // Zonder dit staat er letterlijk "Hallo :voornaam:," in de inbox.
            subject: app(CampaignRenderer::class)->substitutePlainText(
                (string) $this->campaign->subject,
                $this->recipient
            ),
        );
    }

    /**
     * De List-Unsubscribe-koppen laten een mailbox een afmeldknop tonen naast
     * de mail. Gmail en Yahoo eisen ze sinds 2024 van bulkverzenders, en de
     * One-Click-variant vereist dat de URL ook op POST reageert.
     */
    public function headers(): Headers
    {
        $url = UnsubscribeLink::for($this->recipient);

        return new Headers(text: [
            'List-Unsubscribe' => '<' . $url . '>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ]);
    }

    public function content(): Content
    {
        // De HTML is al gerenderd en gepersonaliseerd door CampaignRenderer.
        // Een eigen blade hier zou een tweede renderpad zijn, en dan gaan de
        // preview en de verzending vroeg of laat uit elkaar lopen.
        return new Content(
            htmlString: $this->renderedHtml ?? app(CampaignRenderer::class)->render($this->campaign, $this->recipient),
        );
    }
}
