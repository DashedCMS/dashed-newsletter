<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Dashed\DashedAi\Facades\Ai;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\URL;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Support\Facades\Mail;
use Dashed\DashedNewsletter\Ai\CampaignPlan;
use Dashed\DashedNewsletter\Ai\CampaignPlanner;
use Dashed\DashedNewsletter\Facades\Newsletter;
use Dashed\DashedNewsletter\Ai\CampaignBriefing;
use Dashed\DashedNewsletter\Ai\CampaignComposer;
use Dashed\DashedNewsletter\Jobs\StartCampaignJob;
use Dashed\DashedNewsletter\Models\NewsletterList;
use Dashed\DashedNewsletter\Campaigns\CampaignGuard;
use Dashed\DashedNewsletter\Mail\NewsletterCampaignMail;
use Dashed\DashedNewsletter\Models\NewsletterCampaign;
use Dashed\DashedNewsletter\Campaigns\CampaignCanceller;
use Dashed\DashedNewsletter\Campaigns\CampaignStatistics;
use Dashed\DashedNewsletter\Campaigns\UnsubscribeReasons;
use Dashed\DashedCore\Classes\ContentStudio\BlockCatalog;
use Dashed\DashedNewsletter\Models\NewsletterCampaignRecipient;
use Dashed\DashedNewsletter\Ai\Exceptions\AiGenerationFailedException;
use Dashed\DashedNewsletter\Filament\Resources\NewsletterCampaignResource;

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

    /**
     * Bewaakt dat een expliciet meegegeven newsletter_list_id ook echt bij de
     * actieve site hoort. Zonder deze check kan een newsletter.write-operator
     * op site A een lijst-id van site B doorgeven en zo een campagne aan de
     * verkeerde lijst koppelen. Een via Newsletter::defaultList() opgehaalde
     * default hoeft hier niet doorheen: die is al site-scoped.
     */
    private function assertListOnSite(?int $listId): ?JsonResponse
    {
        if ($listId === null) {
            return null;
        }

        $onSite = NewsletterList::where('site_id', Sites::getActive())->whereKey($listId)->exists();

        if (! $onSite) {
            return response()->json(['success' => false, 'message' => 'Onbekende lijst voor deze site.'], 422);
        }

        return null;
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

    public function statistics(Request $request, int $campaign): JsonResponse
    {
        $c = $this->findForSite($campaign);
        $stats = new CampaignStatistics($c);

        return response()->json(['totals' => $stats->totals(), 'links' => $stats->links()]);
    }

    public function recipients(Request $request, int $campaign): JsonResponse
    {
        $c = $this->findForSite($campaign);

        $query = $c->recipients()->latest('id');
        if ($status = $request->query('status')) {
            $query->where('status', (string) $status);
        }
        $page = $query->paginate(50);

        return response()->json([
            'data' => collect($page->items())->map(fn (NewsletterCampaignRecipient $r) => [
                'id' => $r->id,
                'email' => $r->email,
                'status' => $r->status,
                'reason' => $r->skip_reason ?? $r->bounce_reason ?? null,
                'delivered_at' => optional($r->delivered_at)->toIso8601String(),
                'opened_at' => optional($r->opened_at)->toIso8601String(),
                'open_count' => (int) ($r->open_count ?? 0),
                'clicked_at' => optional($r->clicked_at)->toIso8601String(),
                'click_count' => (int) ($r->click_count ?? 0),
                'unsubscribed_at' => optional($r->unsubscribed_at)->toIso8601String(),
            ])->all(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage()],
        ]);
    }

    public function unsubscribeReasons(Request $request, int $campaign): JsonResponse
    {
        $c = $this->findForSite($campaign);

        return response()->json([
            'totals' => UnsubscribeReasons::totals(null, $c),
            'total' => UnsubscribeReasons::total(null, $c),
            'without_reason' => UnsubscribeReasons::withoutReason(null, $c),
            'comments' => UnsubscribeReasons::comments(null, $c),
        ]);
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

        if (array_key_exists('newsletter_list_id', $data) && $data['newsletter_list_id'] !== null) {
            if ($problem = $this->assertListOnSite($data['newsletter_list_id'])) {
                return $problem;
            }
        }

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

    /** Is er een AI-provider gekoppeld (bepaalt of de app de AI-knop toont)? */
    public function aiAvailable(): JsonResponse
    {
        return response()->json(['available' => class_exists(Ai::class) && Ai::hasProvider()]);
    }

    /** Fase 1: laat de AI een opbouw (producten/artikelen/outline) voorstellen. Slaat niets op. */
    public function aiPlan(Request $request): JsonResponse
    {
        if (! (class_exists(Ai::class) && Ai::hasProvider())) {
            return response()->json(['success' => false, 'message' => 'Er is geen AI-provider gekoppeld.'], 422);
        }

        $data = $request->validate([
            'audience' => ['required', 'string'],
            'occasion' => ['required', 'string'],
            'promote' => ['required', 'string'],
            'length' => ['required', 'string'],
            'instruction' => ['sometimes', 'nullable', 'string'],
        ]);
        $data['instruction'] = $data['instruction'] ?? '';

        try {
            $plan = app(CampaignPlanner::class)->plan(CampaignBriefing::fromFormData($data), Sites::getActive());
        } catch (AiGenerationFailedException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['plan' => $plan->toArray()]);
    }

    /** Fase 2: schrijf de nieuwsbrief o.b.v. het (gefilterde) plan en maak een concept aan. */
    public function aiCompose(Request $request): JsonResponse
    {
        if (! (class_exists(Ai::class) && Ai::hasProvider())) {
            return response()->json(['success' => false, 'message' => 'Er is geen AI-provider gekoppeld.'], 422);
        }

        $data = $request->validate([
            'plan' => ['required', 'array'],
            'briefing' => ['required', 'array'],
            'adjustment' => ['sometimes', 'nullable', 'string'],
            'keep_product_ids' => ['sometimes', 'array'],
            'keep_article_ids' => ['sometimes', 'array'],
            'newsletter_list_id' => ['sometimes', 'nullable', 'integer'],
            'newsletter_segment_id' => ['sometimes', 'nullable', 'integer'],
        ]);

        $plan = CampaignPlan::fromArray($data['plan']);
        if (array_key_exists('keep_product_ids', $data) || array_key_exists('keep_article_ids', $data)) {
            $plan = $plan->only(
                array_map('intval', $data['keep_product_ids'] ?? $plan->productIds()),
                array_map('intval', $data['keep_article_ids'] ?? $plan->articleIds()),
            );
        }

        $briefing = CampaignBriefing::fromFormData($data['briefing']);
        $catalog = (new BlockCatalog())->fromBlocks(NewsletterCampaignResource::newsletterBlocks());

        // newsletter_list_id is een NOT NULL FK: zonder gekozen lijst valt terug
        // op de standaardlijst van de site, en zonder die instelling hoort er
        // een nette 422 te komen in plaats van een database-fout.
        $listId = $data['newsletter_list_id'] ?? Newsletter::defaultList()?->id;
        if (! $listId) {
            return response()->json(['success' => false, 'message' => 'Kies een lijst of stel een standaardlijst in.'], 422);
        }
        if (($data['newsletter_list_id'] ?? null) !== null) {
            if ($problem = $this->assertListOnSite($listId)) {
                return $problem;
            }
        }

        try {
            $draft = app(CampaignComposer::class)->compose($plan, $briefing, $catalog, trim((string) ($data['adjustment'] ?? '')));
        } catch (AiGenerationFailedException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $campaign = NewsletterCampaign::create([
            'site_id' => Sites::getActive(),
            'name' => $draft->name,
            'subject' => $draft->subject,
            'preheader' => $draft->preheader,
            'blocks' => $draft->blocks,
            'status' => NewsletterCampaign::STATUS_CONCEPT,
            'newsletter_list_id' => $listId,
            'newsletter_segment_id' => $data['newsletter_segment_id'] ?? null,
        ]);

        return $this->show($request, $campaign->id);
    }

    /** Maak een leeg concept aan (voor wie zonder AI wil starten). */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'subject' => ['sometimes', 'nullable', 'string', 'max:255'],
            'newsletter_list_id' => ['sometimes', 'nullable', 'integer'],
        ]);

        // newsletter_list_id is een NOT NULL FK: zonder gekozen lijst valt terug
        // op de standaardlijst van de site, en zonder die instelling hoort er
        // een nette 422 te komen in plaats van een database-fout.
        $listId = $data['newsletter_list_id'] ?? Newsletter::defaultList()?->id;
        if (! $listId) {
            return response()->json(['success' => false, 'message' => 'Kies een lijst of stel een standaardlijst in.'], 422);
        }
        if (($data['newsletter_list_id'] ?? null) !== null) {
            if ($problem = $this->assertListOnSite($listId)) {
                return $problem;
            }
        }

        $campaign = NewsletterCampaign::create([
            'site_id' => Sites::getActive(),
            'name' => $data['name'],
            'subject' => $data['subject'] ?? '',
            'status' => NewsletterCampaign::STATUS_CONCEPT,
            'newsletter_list_id' => $listId,
        ]);

        return $this->show($request, $campaign->id)->setStatusCode(201);
    }

    public function sendTest(Request $request, int $campaign): JsonResponse
    {
        $c = $this->findForSite($campaign);
        $data = $request->validate(['email' => ['required', 'email']]);

        // Spiegelt de Filament sendTest-actie: wegwerp-ontvanger, bewust geen
        // suppression/status-check zodat een beheerder zichzelf altijd een proef kan sturen.
        $recipient = new NewsletterCampaignRecipient([
            'newsletter_campaign_id' => $c->id,
            'email' => $data['email'],
            'status' => NewsletterCampaignRecipient::STATUS_PENDING,
        ]);

        Mail::to($data['email'])->send(new NewsletterCampaignMail($c, $recipient));

        return response()->json(['success' => true]);
    }

    public function duplicate(Request $request, int $campaign): JsonResponse
    {
        $c = $this->findForSite($campaign);
        $copy = $c->duplicate();

        return $this->show($request, $copy->id)->setStatusCode(201);
    }
}
