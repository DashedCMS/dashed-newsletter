<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Mail\Mailables\Envelope;
use Dashed\DashedNewsletter\Models\NewsletterCampaign;
use Dashed\DashedNewsletter\Campaigns\UnsubscribeLink;
use Dashed\DashedNewsletter\Models\NewsletterCampaignRecipient;

class NewsletterCampaignMail extends Mailable
{
    public function __construct(
        public NewsletterCampaign $campaign,
        public NewsletterCampaignRecipient $recipient,
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
            subject: (string) $this->campaign->subject,
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
        return new Content(
            view: 'dashed-newsletter::emails.campaign',
            with: [
                'campaign' => $this->campaign,
                'unsubscribeUrl' => UnsubscribeLink::for($this->recipient),
            ],
        );
    }
}
