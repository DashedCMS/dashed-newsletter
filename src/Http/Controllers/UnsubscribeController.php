<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Dashed\DashedNewsletter\Facades\Newsletter;
use Dashed\DashedNewsletter\Models\NewsletterSubscriber;
use Dashed\DashedNewsletter\Models\NewsletterCampaignRecipient;

class UnsubscribeController extends Controller
{
    public function __invoke(Request $request, int $recipient)
    {
        if (! $request->hasValidSignature()) {
            abort(403);
        }

        $row = NewsletterCampaignRecipient::with('subscriber.list')->find($recipient);

        if (! $row || ! $row->subscriber) {
            abort(404);
        }

        // Via changeStatus() en niet met de hand: die schrijft de gebeurtenis in
        // de tijdlijn, zet unsubscribed_at, en stuurt de melding naar de app.
        // Twee keer afmelden levert daar vanzelf niets extra's op.
        Newsletter::changeStatus(
            subscriber: $row->subscriber,
            status: NewsletterSubscriber::STATUS_UNSUBSCRIBED,
            source: 'afmeldlink',
            ip: $request->ip(),
        );

        return response()->view('dashed-newsletter::unsubscribed', [
            'listName' => $row->subscriber->list?->name,
            'email' => $row->email,
        ]);
    }
}
