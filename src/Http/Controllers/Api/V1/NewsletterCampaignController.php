<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedNewsletter\Models\NewsletterCampaign;

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
}
