<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Campaigns;

use Illuminate\Support\Facades\URL;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedNewsletter\Models\NewsletterCampaign;
use Dashed\DashedNewsletter\Models\NewsletterCampaignLink;
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
            // Voor blokken die zelf iets opzoeken (artikelen, producten,
            // kortingscodes): zonder deze sleutel valt zo'n blok terug op
            // Sites::getActive(), en die geeft in een queue-job geen actieve
            // site terug (geen HTTP-request om er een af te leiden), maar de
            // eerst geconfigureerde site. Op een installatie met meer dan één
            // site komt dan de content van de verkeerde site in de mail.
            // effectiveSiteId() is dezelfde afleiding die CampaignRecipients
            // en CampaignSender al gebruiken voor de blokkadelijst.
            'siteId' => $campaign->effectiveSiteId(),
            // Voor blokken die links opleveren (producten): een relatief pad
            // is in een mailprogramma een dode link, dus die maken blokken
            // hiermee absoluut. Dezelfde waarde als de shell-view gebruikt.
            'siteUrl' => Customsetting::get('site_url', $list?->site_id) ?: config('app.url'),
        ];

        $headerBlocks = $list?->header_blocks ?? [];
        $footerBlocks = $list?->footer_blocks ?? [];

        // Eén keer opgehaald en meegegeven aan renderBlocks() én
        // heeftAfmeldblok(): dezelfde registry bepaalt in beide gevallen of
        // een blok daadwerkelijk iets oplevert. Zie het commentaar bij
        // heeftAfmeldblok() voor waarom dat moet samenvallen.
        $registry = cms()->emailBlocks();

        $blocks = array_merge(
            $this->renderBlocks($headerBlocks, $context, $registry),
            $this->renderCampaignBody($campaign, $context, $registry),
            $this->renderBlocks($footerBlocks, $context, $registry),
        );

        // brandingColors()['logo'] is meestal een media-id (mediaHelper()->
        // field in NewsletterListResource), maar hetzelfde veld kan ook via
        // Customsetting::get('mail_logo'/'site_logo') een letterlijke url
        // teruggeven (de e-mailinstellingen van dashed-core gebruiken daar
        // hetzelfde soort veld). getSingleMedia() geeft in dat laatste geval
        // de string ongewijzigd terug (geen media-id om op te zoeken), en
        // anders een object met een url-eigenschap, of '' als het media-item
        // niet meer bestaat. Leeg laten valt terug op de e-mailinstellingen
        // van de site (zie brandingColors()), en is daar ook niets, dan is
        // $siteLogo gewoon null: het beloofde "niets tonen" heeft geen
        // aparte state nodig.
        $media = $kleuren['logo'] ? mediaHelper()->getSingleMedia($kleuren['logo']) : null;
        $siteLogo = match (true) {
            is_object($media) => $media->url ?? null,
            is_string($media) && $media !== '' => $media,
            default => null,
        };
        $siteUrl = $context['siteUrl'];

        return view('dashed-newsletter::emails.shell', [
            'subject' => (string) $campaign->subject,
            'preheader' => $campaign->preheader,
            'blocks' => $blocks,
            'backgroundColor' => $kleuren['background'],
            'primaryColor' => $kleuren['primary'],
            'listName' => $list?->name,
            'siteLogo' => $siteLogo,
            'siteUrl' => $siteUrl,
            // Header, campagne-inhoud en footer tellen allemaal mee: sinds de
            // header ook bewerkbaar is (NewsletterListResource) kan een
            // afmeldblok net zo goed daar staan. Kijk dan alleen naar
            // $footerBlocks, dan mist dat geval en plakt de standaardregel
            // hieronder er een tweede afmeldlink bij.
            'hasUnsubscribeBlock' => $this->heeftAfmeldblok($headerBlocks, $registry)
                || $this->heeftAfmeldblok($campaign->blocks ?? [], $registry)
                || $this->heeftAfmeldblok($footerBlocks, $registry),
        ])->render();
    }

    /**
     * De HTML voor het verzendpad: hetzelfde sjabloon als renderTemplate(),
     * plus de tracking die de lijst toestaat.
     *
     * Bewust een aparte methode en geen vlag op renderTemplate(). De preview,
     * het scherm Voorbeeld en de webversie gaan allemaal door renderTemplate()
     * heen, en die mogen nooit links omschrijven of een pixel plaatsen: dan
     * telt een redacteur die zijn eigen concept bekijkt mee als opening, en
     * staan er linkregels van een campagne die nooit verstuurd is. Met een
     * vlag is dat een kwestie van een aanroeper die hem vergeet; met twee
     * methodes kan het niet per ongeluk.
     */
    public function renderForSending(NewsletterCampaign $campaign): string
    {
        $html = $this->renderTemplate($campaign);
        $list = $campaign->list;

        if ($list?->track_clicks) {
            $html = app(LinkRewriter::class)->rewrite($campaign, $html);
        }

        if ($list?->track_opens) {
            $html = app(TrackingPixel::class)->append($html);
        }

        return $html;
    }

    /**
     * @param array<int, array<string, mixed>> $blocks
     * @param array<string, mixed> $context
     * @param array<string, class-string> $registry
     * @return array<int, string>
     */
    private function renderBlocks(array $blocks, array $context, array $registry): array
    {
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
     * @param array<string, class-string> $registry
     * @return array<int, string>
     */
    private function renderCampaignBody(NewsletterCampaign $campaign, array $context, array $registry): array
    {
        $blocks = $campaign->blocks ?? [];

        if ($blocks !== []) {
            return $this->renderBlocks($blocks, $context, $registry);
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
     * Of dit blokkenrijtje daadwerkelijk een afmeldlink oplevert.
     *
     * Bewust niet alleen op de tekst 'unsubscribe' in de blokdata afgaan: dat
     * is een vlag, geen garantie. Staat 'unsubscribe' wel in de data maar
     * niet (meer) in $registry, dan rendert renderBlocks() dat blok niet
     * (zie de guard daar), en zou de vlag alsnog de standaardregel in
     * shell.blade.php onderdrukken: een nieuwsbrief zonder afmeldlink,
     * precies wat UnsubscribeBlock's eigen klassecommentaar uitsluit. Door
     * hier dezelfde $registry-check te doen als renderBlocks() zelf gebruikt,
     * volgt de vlag wat er werkelijk gerenderd wordt.
     *
     * @param array<int, array<string, mixed>> $blocks
     * @param array<string, class-string> $registry
     */
    private function heeftAfmeldblok(array $blocks, array $registry): bool
    {
        foreach ($blocks as $block) {
            $type = $block['type'] ?? null;

            if ($type === 'unsubscribe' && isset($registry[$type])) {
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
        return $this->vervang($html, $recipient, ontsnappen: true);
    }

    /**
     * Zelfde vervanging, maar zonder te ontsnappen. Voor plekken die geen HTML
     * zijn, en dat is er precies een: het onderwerp van de mail. Een
     * mailheader is platte tekst, dus daar zou htmlspecialchars() een contact
     * dat "Jan & Erna" heet als "Jan &amp; Erna" in de inbox zetten, en een
     * naam als d'Angelo als d&#039;Angelo.
     *
     * Dit is veilig omdat een header geen HTML rendert. Gebruik dit nergens
     * anders voor: alles wat wel in de HTML van de mail belandt hoort door
     * substitute() te gaan.
     */
    public function substitutePlainText(string $tekst, NewsletterCampaignRecipient $recipient): string
    {
        return $this->vervang($tekst, $recipient, ontsnappen: false);
    }

    private function vervang(string $html, NewsletterCampaignRecipient $recipient, bool $ontsnappen): string
    {
        $waarden = CampaignPersonalisation::valuesFor($recipient);

        // Ontsnappen, want deze waarden komen niet van een beheerder. Een
        // bezoeker vult zijn eigen contactvelden in via het aanmeldformulier
        // (NewsletterListAPI::dispatch() -> subscribe() ->
        // NewsletterFieldValue::writeValue(), zonder filtering opgeslagen), en
        // die tekst belandt hier letterlijk in de HTML van de mail en in de
        // srcdoc van de previewiframe. Zonder deze stap is een voornaam met een
        // scripttag opgeslagen XSS op het domein van de webshop, en in de
        // preview (srcdoc erft de origin van het beheerpaneel) een manier om in
        // de sessie van een ingelogde beheerder te draaien.
        //
        // CampaignPersonalisation::valuesFor() mixt de echte contactwaarde en de
        // terugvalwaarde (NewsletterField::default_value, door een beheerder
        // gezet) door elkaar tot dezelfde array, dus hier is geen onderscheid
        // meer te maken tussen de twee. Ook de terugvalwaarde gaat daarom door
        // htmlspecialchars(): dat is de veilige kant om op te vergissen. Zet een
        // beheerder per ongeluk "Jansen & Zn <BV>" als terugvalwaarde, dan zie
        // je gewoon die tekst; het omgekeerde (bezoekersinvoer die per ongeluk
        // ongefilterd blijft) is opgeslagen XSS.
        if ($ontsnappen) {
            foreach ($waarden as $sleutel => $waarde) {
                $waarden[$sleutel] = htmlspecialchars($waarde, ENT_QUOTES, 'UTF-8');
            }
        }

        // Na het ontsnappen toegevoegd: dit zijn URL's die deze klasse zelf
        // opbouwt, geen contactinvoer. Ze horen in een href te staan, en
        // htmlspecialchars() zou het &-teken tussen querystring-parameters
        // (signedRoute voegt ?signature=... toe) veranderen in &amp;, wat in
        // een href juist correct is maar de handtekening van de URL zelf niet
        // aanpast. UrlSignatureController leest de URL zoals de browser hem
        // ontsnapt terugstuurt, dus dit blijft werken; alleen was ontsnappen
        // hier zinloos geweest omdat de waarde niet van een bezoeker komt.
        $waarden['unsubscribe_url'] = UnsubscribeLink::for($recipient);
        $waarden['web_version_url'] = $recipient->id
            ? SignedLink::to('dashed-newsletter.campaign.web-version', ['recipient' => $recipient->id])
            : '';

        // Leeg bij een proefmail, net als de webversie hierboven: er is geen
        // opgeslagen ontvangerregel om naar te ondertekenen, en een beheerder
        // die zichzelf een proef stuurt hoort ook niet als opening in de
        // cijfers te belanden.
        $waarden['open_pixel_url'] = $recipient->id
            ? SignedLink::to('dashed-newsletter.campaign.open', ['recipient' => $recipient->id])
            : '';

        return preg_replace_callback(
            '/:(\w+):/',
            function (array $m) use ($waarden, $recipient): string {
                if (array_key_exists($m[1], $waarden)) {
                    return $waarden[$m[1]];
                }

                // De klikplaatshouders staan niet in $waarden: een campagne
                // kan er tientallen hebben, en die allemaal vooraf opbouwen
                // zou voor elke ontvanger een reeks ondertekende URL's maken
                // waarvan de meeste niet in de mail voorkomen.
                if (preg_match('/^click_(\d+)$/', $m[1], $klik)) {
                    return $this->klikUrl((int) $klik[1], $recipient);
                }

                return $m[0];
            },
            $html
        );
    }

    /**
     * De ondertekende klik-URL van een link voor deze ontvanger.
     *
     * Twee terugvallen, allebei op de echte URL en niet op een lege string.
     * Een proefmail heeft geen opgeslagen ontvangerregel om naar te
     * ondertekenen (zie UnsubscribeLink::for(), zelfde geval), en dan hoort de
     * knop gewoon te werken zonder gemeten te worden. Bestaat de linkregel
     * niet meer, dan laten we de plaatshouder staan: dat valt op in een
     * preview, terwijl een lege href pas bij een ontvanger opvalt.
     */
    private function klikUrl(int $linkId, NewsletterCampaignRecipient $recipient): string
    {
        $link = NewsletterCampaignLink::find($linkId);

        if (! $link) {
            return ':click_' . $linkId . ':';
        }

        if (! $recipient->exists) {
            return $link->url;
        }

        return SignedLink::to('dashed-newsletter.campaign.click', [
            'link' => $link->id,
            'recipient' => $recipient->id,
        ]);
    }
}
