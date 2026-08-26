<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedNewsletter\Campaigns\CampaignGuard;
use Dashed\DashedNewsletter\Models\NewsletterCampaign;
use Dashed\DashedNewsletter\Campaigns\CampaignRenderer;
use Dashed\DashedNewsletter\Campaigns\CampaignRecipients;
use Dashed\DashedNewsletter\Models\NewsletterCampaignRecipient;

/**
 * Zet een campagne in gang: controleren, ontvangers vastleggen, porties
 * inplannen. Dit is de enige weg naar binnen, of je nu op verzenden drukt of
 * een geplande campagne aan de beurt is.
 */
class StartCampaignJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public int $campaignId)
    {
    }

    public function handle(): void
    {
        $campaign = NewsletterCampaign::find($this->campaignId);

        if (! $campaign) {
            return;
        }

        CampaignGuard::assertSendable($campaign);

        // Voorwaardelijke update in plaats van een gewone save: twee jobs voor
        // dezelfde campagne (een dubbele klik op de verzendknop, of de knop die
        // de planner net voor was) lezen via de guard hierboven allebei
        // dezelfde 'concept'/'scheduled'-status. Een if-check op
        // $campaign->status in het geheugen zou ze dan allebei laten
        // doorgaan. Het aantal geraakte rijen van deze UPDATE is de enige
        // betrouwbare uitspraak over wie deze campagne mag starten: van twee
        // gelijktijdige jobs raakt er precies één een rij, de ander nul.
        // Zelfde patroon als CampaignSender::send() per ontvanger gebruikt,
        // hier één keer op campagneniveau.
        //
        // Deze claim hoort in de job en nergens anders, ook niet in de
        // verzendknop: CampaignGuard::problem() leest de status van de
        // campagne, dus wie die status vóór de guard-check zet, sluit
        // zichzelf buiten - de guard zou dan altijd "al aan het verzenden"
        // teruggeven, ook voor de aanroep die de vlag net zelf zette. De
        // guard moet de oude status zien; de claim mag pas daarna.
        //
        // De claim werkt bewust op uitsluiting (whereNotIn) en niet op een
        // eigen, positieve lijst van toegestane statussen. CampaignGuard::
        // problem() weigert precies 'sent' en 'sending', en niets anders:
        // alles wat de guard hierboven al doorliet moet dus ook hier
        // claimbaar zijn. Een eigen lijst hier (zoals eerder alleen 'concept'
        // en 'scheduled') is een tweede plek die "mag deze campagne starten"
        // moet betekenen, en die twee lopen dan een keer uiteen: precies wat
        // er eerder gebeurde toen 'cancelled' en 'failed' hier ontbraken
        // terwijl de guard ze allang doorliet. Blijf dit spiegelbeeld van de
        // guard, dan is er maar één plek die bepaalt wat "verzendbaar" is.
        $geclaimd = NewsletterCampaign::where('id', $campaign->id)
            ->whereNotIn('status', [NewsletterCampaign::STATUS_SENT, NewsletterCampaign::STATUS_SENDING])
            ->update([
                'status' => NewsletterCampaign::STATUS_SENDING,
                'started_at' => now(),
                // Een herstart vanuit 'failed' laat anders de oude reden
                // staan terwijl de campagne inmiddels gewoon (opnieuw)
                // verzonden wordt: het scherm toonde dan "Verzonden" met de
                // reden van de vorige mislukking er nog pal naast. Bij elke
                // herstart geldt: was er een reden, die hoort bij de vorige
                // poging en niet meer bij deze.
                'failure_reason' => null,
            ]);

        if ($geclaimd === 0) {
            // Een andere job heeft deze campagne net al geclaimd; niets te
            // doen hier, en zeker geen tweede keer ontvangers opbouwen.
            return;
        }

        $campaign->refresh();

        // Eén keer renderen voor deze hele ronde. De plaatshouders blijven
        // staan; CampaignSender vult ze per ontvanger in. Zou hier per
        // ontvanger gerenderd worden, dan bevraagt een productblok de webshop
        // net zo vaak als er ontvangers zijn.
        $campaign->update([
            'rendered_html' => app(CampaignRenderer::class)->renderForSending($campaign),
        ]);

        CampaignRecipients::build($campaign);

        // Het tempo bepaalt zowel hoe groot een portie is als hoe ver ze uit
        // elkaar liggen. Een portie staat voor precies zoveel werk als er in
        // die tijd mag: zou de portie groter zijn dan het tempo per minuut,
        // dan gaat de eerste er alsnog in een klap uit en is de afspraak een
        // papieren afspraak.
        //
        // Spreiden en geen pauze binnen de portie: een worker die staat te
        // slapen is een worker die niets anders kan doen, en bij duizenden
        // ontvangers is dat urenlang. De wachtrij kan wachten, de worker niet.
        $tempo = $campaign->list?->effectiveSendRatePerMinute() ?? 0;
        $chunkSize = (int) config('dashed-newsletter.chunk_size', 200);

        if ($tempo > 0) {
            $chunkSize = max(1, min($chunkSize, $tempo));
        }

        $secondenPerPortie = $tempo > 0
            ? (int) round(($chunkSize * 60) / $tempo)
            : 0;

        NewsletterCampaignRecipient::where('newsletter_campaign_id', $campaign->id)
            ->where('status', NewsletterCampaignRecipient::STATUS_PENDING)
            ->pluck('id')
            ->chunk($chunkSize)
            ->values()
            ->each(function ($ids, int $nummer) use ($campaign, $secondenPerPortie): void {
                $job = SendCampaignChunkJob::dispatch($campaign->id, $ids->all());

                if ($secondenPerPortie > 0 && $nummer > 0) {
                    $job->delay(now()->addSeconds($nummer * $secondenPerPortie));
                }
            });

        // Met een synchrone wachtrij (zoals in tests) is elke portie hierboven
        // al meteen afgehandeld tegen de tijd dat we hier komen, en kan de
        // laatste portie de campagne al op 'sent' hebben gezet. Met een echte
        // wachtrij zijn de porties nog niet gestart. Beide gevallen lopen via
        // dezelfde slotcontrole: niets openstaand, dan is de campagne klaar.
        // 'sending' telt hier mee als nog niet afgehandeld, net als in
        // SendCampaignChunkJob: een regel die net geclaimd is maar nog niet
        // verstuurd, mag de campagne niet als voltooid laten gelden.
        $open = NewsletterCampaignRecipient::where('newsletter_campaign_id', $campaign->id)
            ->whereIn('status', [
                NewsletterCampaignRecipient::STATUS_PENDING,
                NewsletterCampaignRecipient::STATUS_SENDING,
            ])
            ->exists();

        if (! $open && $campaign->fresh()->status === NewsletterCampaign::STATUS_SENDING) {
            $campaign->update([
                'status' => NewsletterCampaign::STATUS_SENT,
                'completed_at' => now(),
            ]);
        }
    }
}
