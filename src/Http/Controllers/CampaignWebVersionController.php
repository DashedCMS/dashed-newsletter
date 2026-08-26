<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Dashed\DashedNewsletter\Campaigns\SignedLink;
use Dashed\DashedNewsletter\Campaigns\CampaignRenderer;
use Dashed\DashedNewsletter\Models\NewsletterCampaignRecipient;

class CampaignWebVersionController
{
    public function __invoke(Request $request, int $recipient): Response
    {
        // Ondertekend, want de webversie bevat de gepersonaliseerde tekst van
        // deze ene ontvanger. Zonder handtekening zou een oplopend nummer
        // genoeg zijn om de nieuwsbrief van een ander te lezen.
        abort_unless(SignedLink::isValid($request), 403);

        $ontvanger = NewsletterCampaignRecipient::find($recipient);

        abort_unless($ontvanger && $ontvanger->campaign, 404);

        $campaign = $ontvanger->campaign;
        $renderer = app(CampaignRenderer::class);

        // Zelfde vorm als CampaignSender::send(): rendered_html is het
        // sjabloon dat StartCampaignJob één keer voor de hele verzendronde
        // vastlegde. Zonder dit hergebruik bouwt render() de campagne opnieuw
        // op, en dan toont de webversie op woensdag andere producten dan de
        // mail die maandag verstuurd is bij een campagne met een automatische
        // productselectie. De terugval op renderTemplate() is er voor een
        // campagne die nog geen rendered_html heeft (nooit verzonden, of een
        // proefmail), zodat de webversielink daar niet leeg op uitkomt.
        return response(
            $renderer->substitute(
                (string) ($campaign->rendered_html ?: $renderer->renderTemplate($campaign)),
                $ontvanger
            )
        );
    }
}
