<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Campaigns;

/**
 * Zet de plaatshouder voor het meetplaatje in de HTML. De echte, ondertekende
 * URL komt er per ontvanger in via CampaignRenderer::vervang().
 *
 * display:block en niet het gebruikelijke onzichtbaar maken met CSS: een
 * mailprogramma dat display:none respecteert laadt het plaatje soms niet, en
 * dan meet je niets. Een plaatje van een bij een pixel valt zo of zo niet op.
 */
class TrackingPixel
{
    private const PLAATSHOUDER = ':open_pixel_url:';

    private const MARKERING = '<img src=":open_pixel_url:" width="1" height="1" alt="" style="display:block;width:1px;height:1px;border:0">';

    public function append(string $html): string
    {
        // Nooit twee: renderTemplate() kan in theorie twee keer over dezelfde
        // HTML lopen, en twee pixels betekent elke opening dubbel geteld.
        if (str_contains($html, self::PLAATSHOUDER)) {
            return $html;
        }

        $positie = strripos($html, '</body>');

        if ($positie === false) {
            return $html . self::MARKERING;
        }

        return substr($html, 0, $positie) . self::MARKERING . substr($html, $positie);
    }
}
