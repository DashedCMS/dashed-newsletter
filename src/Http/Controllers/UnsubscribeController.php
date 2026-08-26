<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Dashed\DashedNewsletter\Facades\Newsletter;
use Dashed\DashedNewsletter\Campaigns\SignedLink;
use Dashed\DashedNewsletter\Models\NewsletterSubscriber;
use Dashed\DashedNewsletter\Models\NewsletterCampaignRecipient;

/**
 * Afmelden, opnieuw aanmelden, en het scherm ertussen.
 *
 * GET en POST op dezelfde URL doen bewust iets verschillends, en dat is geen
 * slordigheid maar een eis.
 *
 * De mail stuurt List-Unsubscribe-Post mee, dus Gmail en Yahoo tonen hun eigen
 * afmeldknop en POSTen daarheen. RFC 8058 eist dat zo'n POST direct
 * uitschrijft, zonder tussenscherm. Een bevestigingspagina daar zou de norm
 * schenden, en mailboxaanbieders rekenen dat af op de bezorgbaarheid van
 * alles wat je daarna nog verstuurt.
 *
 * De link in de mail zelf is een GET, en die hoort juist wel een scherm te
 * tonen: afmelden is een handeling, en een mailprogramma dat links vooruit
 * ophaalt zou anders mensen uitschrijven die niets hebben aangeklikt. Dat
 * laatste is geen theorie; scanners van bedrijfsmail doen dat.
 */
class UnsubscribeController extends Controller
{
    /** De link uit de mail: tonen wat er staat te gebeuren, nog niets doen. */
    public function show(Request $request, int $recipient)
    {
        $row = $this->regel($request, $recipient);

        return response()->view('dashed-newsletter::unsubscribe-confirm', [
            'email' => $row->email,
            'listName' => $row->subscriber->list?->name,
            'reasons' => NewsletterCampaignRecipient::unsubscribeReasons(),
            'confirmUrl' => SignedLink::to('dashed.frontend.newsletter.unsubscribe-confirm', ['recipient' => $row->id]),
        ]);
    }

    /**
     * De one-click-afmelding van een mailbox (RFC 8058). Direct uitschrijven,
     * geen scherm, geen reden: er is niemand om er een te vragen.
     */
    public function oneClick(Request $request, int $recipient)
    {
        $row = $this->regel($request, $recipient);

        $this->meldAf($request, $row, null, null);

        return $this->klaar($row);
    }

    /** De knop op onze eigen pagina, met een optionele reden. */
    public function confirm(Request $request, int $recipient)
    {
        $row = $this->regel($request, $recipient);

        // De reden komt van een bezoeker, dus alleen wat op de lijst staat
        // telt. Een verzonnen waarde hoort niet in de rapportage te belanden.
        $reden = (string) $request->input('reason', '');
        $reden = array_key_exists($reden, NewsletterCampaignRecipient::unsubscribeReasons()) ? $reden : null;

        // Afkappen en niet weigeren: een te lange toelichting is geen reden om
        // iemand het afmelden te ontzeggen.
        $toelichting = trim((string) $request->input('comment', ''));
        $toelichting = $toelichting === '' ? null : mb_substr($toelichting, 0, 1000);

        $this->meldAf($request, $row, $reden, $toelichting);

        return $this->klaar($row);
    }

    /** Toch weer aanmelden, vanaf de pagina na het afmelden. */
    public function resubscribe(Request $request, int $recipient)
    {
        $row = $this->regel($request, $recipient);

        Newsletter::changeStatus(
            subscriber: $row->subscriber,
            status: NewsletterSubscriber::STATUS_ACTIVE,
            consentText: 'Opnieuw aangemeld via de afmeldpagina van een nieuwsbrief.',
            source: 'afmeldpagina',
            ip: $request->ip(),
        );

        // De afmelding van deze campagne terugdraaien, inclusief de reden: die
        // hoort niet in de cijfers te blijven staan van iemand die er nog op
        // staat. De tijdlijn van het contact houdt beide gebeurtenissen wel.
        $row->forceFill([
            'unsubscribed_at' => null,
            'unsubscribe_reason' => null,
            'unsubscribe_comment' => null,
        ])->save();

        return response()->view('dashed-newsletter::resubscribed', [
            'email' => $row->email,
            'listName' => $row->subscriber->list?->name,
        ]);
    }

    private function regel(Request $request, int $recipient): NewsletterCampaignRecipient
    {
        if (! SignedLink::isValid($request)) {
            abort(403);
        }

        $row = NewsletterCampaignRecipient::with('subscriber.list')->find($recipient);

        if (! $row || ! $row->subscriber) {
            abort(404);
        }

        return $row;
    }

    private function meldAf(Request $request, NewsletterCampaignRecipient $row, ?string $reden, ?string $toelichting): void
    {
        // Via changeStatus() en niet met de hand: die schrijft de gebeurtenis
        // in de tijdlijn, zet unsubscribed_at op het contact, en stuurt de
        // melding naar de app. Twee keer afmelden levert daar vanzelf niets
        // extra's op.
        Newsletter::changeStatus(
            subscriber: $row->subscriber,
            status: NewsletterSubscriber::STATUS_UNSUBSCRIBED,
            source: 'afmeldlink',
            ip: $request->ip(),
        );

        // En op de ontvangerregel, want changeStatus() legt het vast op het
        // contact zonder de campagne erbij. Zonder dit is niet te zien welke
        // mail de aanleiding was, en dus ook niet hoeveel afmeldingen een
        // campagne kostte.
        $row->forceFill([
            'unsubscribed_at' => $row->unsubscribed_at ?? now(),
            'unsubscribe_reason' => $reden,
            'unsubscribe_comment' => $toelichting,
        ])->save();
    }

    private function klaar(NewsletterCampaignRecipient $row)
    {
        return response()->view('dashed-newsletter::unsubscribed', [
            'email' => $row->email,
            'listName' => $row->subscriber->list?->name,
            'resubscribeUrl' => SignedLink::to('dashed.frontend.newsletter.resubscribe', ['recipient' => $row->id]),
        ]);
    }
}
