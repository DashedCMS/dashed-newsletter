<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedNewsletter\Models\NewsletterList;
use Dashed\DashedNewsletter\Models\NewsletterSubscriber;

class NewsletterAudienceController extends Controller
{
    /** Lijst binnen de actieve site of 404. */
    private function findList(int $list): NewsletterList
    {
        return NewsletterList::where('site_id', Sites::getActive())->findOrFail($list);
    }

    /** Abonnee binnen de actieve site (via zijn lijst) of 404. */
    private function findSubscriber(int $subscriber): NewsletterSubscriber
    {
        return NewsletterSubscriber::whereHas('list', fn ($q) => $q->where('site_id', Sites::getActive()))->findOrFail($subscriber);
    }

    public function lists(Request $request): JsonResponse
    {
        $lists = NewsletterList::where('site_id', Sites::getActive())
            ->withCount('subscribers')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $lists->map(fn (NewsletterList $l) => [
                'id' => $l->id,
                'name' => $l->name,
                'from_email' => $l->from_email,
                'from_name' => $l->from_name,
                'subscriber_count' => (int) ($l->subscribers_count ?? 0),
            ])->all(),
        ]);
    }

    public function subscribers(Request $request, int $list): JsonResponse
    {
        $model = $this->findList($list);

        $query = $model->subscribers()->latest();
        if ($search = trim((string) $request->query('search'))) {
            $query->where('email', 'like', "%{$search}%");
        }

        $page = $query->paginate(30);

        return response()->json([
            'data' => collect($page->items())->map(fn (NewsletterSubscriber $s) => [
                'id' => $s->id,
                'email' => $s->email,
                'status' => $s->status,
                'source' => $s->source,
                'created_at' => optional($s->created_at)->toIso8601String(),
            ])->all(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage()],
        ]);
    }

    public function subscriber(Request $request, int $subscriber): JsonResponse
    {
        $s = $this->findSubscriber($subscriber);
        $s->load(['list:id,name', 'fieldValues.field:id,key', 'consents', 'events']);

        return response()->json(['data' => [
            'id' => $s->id,
            'email' => $s->email,
            'status' => $s->status,
            'source' => $s->source,
            'list_name' => $s->list?->name,
            'unsubscribed_at' => optional($s->unsubscribed_at)->toIso8601String(),
            'created_at' => optional($s->created_at)->toIso8601String(),
            'fields' => $s->fieldValues->map(fn ($f) => ['key' => $f->field?->key ?? (string) $f->newsletter_field_id, 'value' => $f->value])->all(),
            'events' => $s->events->map(fn ($e) => ['type' => $e->type, 'created_at' => optional($e->created_at)->toIso8601String()])->all(),
        ]]);
    }
}
