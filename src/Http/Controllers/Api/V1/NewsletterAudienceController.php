<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedNewsletter\Models\NewsletterList;
use Dashed\DashedNewsletter\Facades\Newsletter;
use Dashed\DashedNewsletter\Models\NewsletterSegment;
use Dashed\DashedNewsletter\Models\NewsletterSubscriber;
use Dashed\DashedNewsletter\Models\NewsletterSuppression;
use Dashed\DashedNewsletter\Exceptions\InvalidEmailException;

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

    public function addSubscriber(Request $request, int $list): JsonResponse
    {
        $model = $this->findList($list);

        $data = $request->validate([
            'email' => ['required', 'email'],
            'fields' => ['sometimes', 'array'],
        ]);

        try {
            $subscriber = Newsletter::subscribe($data['email'], $model, $data['fields'] ?? [], source: 'app');
        } catch (InvalidEmailException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return $this->subscriber($request, $subscriber->id)->setStatusCode(201);
    }

    public function unsubscribe(Request $request, int $subscriber): JsonResponse
    {
        $s = $this->findSubscriber($subscriber);
        Newsletter::changeStatus($s, NewsletterSubscriber::STATUS_UNSUBSCRIBED, source: 'app');

        return $this->subscriber($request, $s->id);
    }

    public function suppressions(Request $request): JsonResponse
    {
        $page = NewsletterSuppression::where('site_id', Sites::getActive())->latest()->paginate(30);

        return response()->json([
            'data' => collect($page->items())->map(fn (NewsletterSuppression $x) => [
                'id' => $x->id,
                'email' => $x->email,
                'reason' => $x->reason,
                'source' => $x->source,
                'created_at' => optional($x->created_at)->toIso8601String(),
            ])->all(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage()],
        ]);
    }

    public function segments(Request $request): JsonResponse
    {
        $segments = NewsletterSegment::whereHas('list', fn ($q) => $q->where('site_id', Sites::getActive()))
            ->with('list:id,name')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $segments->map(fn (NewsletterSegment $sg) => [
                'id' => $sg->id,
                'name' => $sg->name,
                'list_name' => $sg->list?->name,
            ])->all(),
        ]);
    }
}
