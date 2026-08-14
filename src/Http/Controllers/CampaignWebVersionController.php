<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Dashed\DashedNewsletter\Campaigns\CampaignRenderer;
use Dashed\DashedNewsletter\Models\NewsletterCampaignRecipient;

class CampaignWebVersionController
{
    public function __invoke(Request $request, int $recipient): Response
    {
        // Ondertekend, want de webversie bevat de gepersonaliseerde tekst van
        // deze ene ontvanger. Zonder handtekening zou een oplopend nummer
        // genoeg zijn om de nieuwsbrief van een ander te lezen.
        abort_unless($request->hasValidSignature(), 403);

        $ontvanger = NewsletterCampaignRecipient::find($recipient);

        abort_unless($ontvanger && $ontvanger->campaign, 404);

        return response(
            app(CampaignRenderer::class)->render($ontvanger->campaign, $ontvanger)
        );
    }
}
