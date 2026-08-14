<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Campaigns;

use Illuminate\Support\Facades\URL;
use Dashed\DashedCore\Models\Customsetting;
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
            // Voor blokken die zelf iets opzoeken (artikelen, producten,
            // kortingscodes): zonder deze sleutel valt zo'n blok terug op
            // Sites::getActive(), en die geeft in een queue-job geen actieve
            // site terug (geen HTTP-request om er een af te leiden), maar de
            // eerst geconfigureerde site. Op een installatie met meer dan één
            // site komt dan de content van de verkeerde site in de mail.
            // effectiveSiteId() is dezelfde afleiding die CampaignRecipients
            // en CampaignSender al gebruiken voor de blokkadelijst.
            'siteId' => $campaign->effectiveSiteId(),
        ];

        $headerBlocks = $list?->header_blocks ?? [];
        $footerBlocks = $list?->footer_blocks ?? [];

        $blocks = array_merge(
            $this->renderBlocks($headerBlocks, $context),
            $this->renderCampaignBody($campaign, $context),
            $this->renderBlocks($footerBlocks, $context),
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
        $siteUrl = Customsetting::get('site_url', $list?->site_id) ?: config('app.url');

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
        foreach ($waarden as $sleutel => $waarde) {
            $waarden[$sleutel] = htmlspecialchars($waarde, ENT_QUOTES, 'UTF-8');
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
            ? URL::signedRoute('dashed-newsletter.campaign.web-version', ['recipient' => $recipient->id])
            : '';

        return preg_replace_callback(
            '/:(\w+):/',
            fn (array $m): string => array_key_exists($m[1], $waarden) ? $waarden[$m[1]] : $m[0],
            $html
        );
    }
}
