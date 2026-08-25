<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\URL;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedNewsletter\Jobs\StartCampaignJob;
use Dashed\DashedNewsletter\Models\NewsletterCampaign;
use Dashed\DashedNewsletter\Campaigns\CampaignGuard;
use Dashed\DashedNewsletter\Campaigns\CampaignRenderer;
use Dashed\DashedNewsletter\Campaigns\CampaignCanceller;

class NewsletterCampaignController extends Controller
{
    /** Platte weergave van één campagne voor de app. */
    private function summary(NewsletterCampaign $c): array
    {
        return [
            'id' => $c->id,
            'name' => $c->name,
            'subject' => $c->subject,
            'status' => $c->status,
            'list_name' => $c->list?->name,
            'segment_name' => $c->segment?->name,
            'sent_count' => (int) ($c->sent_count ?? 0),
            'scheduled_at' => optional($c->scheduled_at)->toIso8601String(),
            'created_at' => optional($c->created_at)->toIso8601String(),
        ];
    }

    /** Gepagineerde campagnelijst voor de actieve site, optioneel gefilterd op status. */
    public function index(Request $request): JsonResponse
    {
        $query = NewsletterCampaign::where('site_id', Sites::getActive())
            ->with(['list:id,name', 'segment:id,name'])
            ->latest();

        if ($status = $request->query('status')) {
            $query->where('status', (string) $status);
        }

        $page = $query->paginate(25);

        return response()->json([
            'data' => collect($page->items())->map(fn (NewsletterCampaign $c) => $this->summary($c))->all(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage()],
        ]);
    }

    /** Zoek een campagne binnen de actieve site of geef 404. */
    private function findForSite(int $campaign): NewsletterCampaign
    {
        return NewsletterCampaign::where('site_id', Sites::getActive())->findOrFail($campaign);
    }

    /** Ontvangers geteld per verzendstatus. */
    private function stats(NewsletterCampaign $c): array
    {
        $counts = $c->recipients()
            ->selectRaw('status, COUNT(*) as aantal')
            ->groupBy('status')
            ->pluck('aantal', 'status');

        $keys = ['pending', 'sending', 'sent', 'skipped', 'failed', 'interrupted'];
        $out = [];
        $total = 0;
        foreach ($keys as $k) {
            $out[$k] = (int) ($counts[$k] ?? 0);
            $total += $out[$k];
        }
        $out['total'] = $total;

        return $out;
    }

    public function show(Request $request, int $campaign): JsonResponse
    {
        $c = $this->findForSite($campaign);
        $c->load(['list:id,name', 'segment:id,name']);

        return response()->json(['data' => array_merge($this->summary($c), [
            'preheader' => $c->preheader,
            'from_email' => $c->from_email,
            'reply_to_email' => $c->reply_to_email,
            'failure_reason' => $c->failure_reason,
            'newsletter_list_id' => $c->newsletter_list_id,
            'newsletter_segment_id' => $c->newsletter_segment_id,
            'stats' => $this->stats($c),
        ])]);
    }

    public function preview(Request $request, int $campaign): JsonResponse
    {
        $c = $this->findForSite($campaign);

        $url = URL::temporarySignedRoute(
            'dashed-newsletter.campaign.preview',
            now()->addMinutes(30),
            ['campaign' => $c->id],
        );

        return response()->json(['url' => $url]);
    }

    public function update(Request $request, int $campaign): JsonResponse
    {
        $c = $this->findForSite($campaign);

        if ($c->status !== NewsletterCampaign::STATUS_CONCEPT) {
            return response()->json([
                'success' => false,
                'message' => 'Alleen een concept kan bewerkt worden.',
            ], 422);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'subject' => ['sometimes', 'string', 'max:255'],
            'preheader' => ['sometimes', 'nullable', 'string', 'max:255'],
            'from_email' => ['sometimes', 'nullable', 'email'],
            'reply_to_email' => ['sometimes', 'nullable', 'email'],
            'newsletter_list_id' => ['sometimes', 'nullable', 'integer'],
            'newsletter_segment_id' => ['sometimes', 'nullable', 'integer'],
            'scheduled_at' => ['sometimes', 'nullable', 'date'],
        ]);

        $c->fill($data)->save();

        return $this->show($request, $c->id);
    }

    public function send(Request $request, int $campaign): JsonResponse
    {
        $c = $this->findForSite($campaign);

        if ($problem = CampaignGuard::problem($c)) {
            return response()->json(['success' => false, 'message' => $problem], 422);
        }

        StartCampaignJob::dispatch($c->id);

        return response()->json(['success' => true]);
    }

    public function schedule(Request $request, int $campaign): JsonResponse
    {
        $c = $this->findForSite($campaign);

        if (! in_array($c->status, [NewsletterCampaign::STATUS_CONCEPT, NewsletterCampaign::STATUS_SCHEDULED], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Alleen een concept kan ingepland worden.',
            ], 422);
        }

        if ($problem = CampaignGuard::problem($c)) {
            return response()->json(['success' => false, 'message' => $problem], 422);
        }

        $data = $request->validate([
            'scheduled_at' => ['required', 'date', 'after:now'],
        ]);

        $c->scheduled_at = $data['scheduled_at'];
        $c->status = NewsletterCampaign::STATUS_SCHEDULED;
        $c->save();

        return $this->show($request, $c->id);
    }

    public function cancel(Request $request, int $campaign): JsonResponse
    {
        $c = $this->findForSite($campaign);

        if ($c->status === NewsletterCampaign::STATUS_SCHEDULED) {
            // CampaignCanceller claimt/onderbreekt alleen STATUS_SENDING; voor
            // een ingeplande campagne raakt dat 0 rijen en blijft de status
            // "scheduled" staan, waardoor SendScheduledCampaigns 'm alsnog op
            // het geplande tijdstip verstuurt. Hier direct annuleren en de
            // planning wissen zodat de scheduler 'm overslaat.
            $c->status = NewsletterCampaign::STATUS_CANCELLED;
            $c->scheduled_at = null;
            $c->save();
        } else {
            CampaignCanceller::cancel($c);
        }

        return $this->show($request, $c->id);
    }
}
