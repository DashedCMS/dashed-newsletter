<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedNewsletter\Facades\Newsletter;
use Dashed\DashedNewsletter\Models\NewsletterField;
use Dashed\DashedNewsletter\Models\NewsletterList;
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

    /** Platte lijst-detail (bewerkbare velden). */
    private function listPayload(NewsletterList $l): array
    {
        return [
            'id' => $l->id,
            'name' => $l->name,
            'locale' => $l->locale,
            'from_name' => $l->from_name,
            'from_email' => $l->from_email,
            'reply_to_email' => $l->reply_to_email,
            'notify_on_subscribe' => (bool) $l->notify_on_subscribe,
            'notify_on_unsubscribe' => (bool) $l->notify_on_unsubscribe,
            'send_rate_per_minute' => $l->send_rate_per_minute,
        ];
    }

    private function findField(int $field): NewsletterField
    {
        return NewsletterField::whereHas('list', fn ($q) => $q->where('site_id', Sites::getActive()))->findOrFail($field);
    }

    private function fieldPayload(NewsletterField $f): array
    {
        return [
            'id' => $f->id, 'key' => $f->key, 'label' => $f->label, 'type' => $f->type,
            'required' => (bool) $f->required, 'options' => $f->options ?? [],
            'default_value' => $f->default_value, 'show_in_signup_form' => (bool) $f->show_in_signup_form, 'sort' => (int) ($f->sort ?? 0),
        ];
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

    public function updateSubscriber(Request $request, int $subscriber): JsonResponse
    {
        $s = $this->findSubscriber($subscriber);

        $data = $request->validate([
            'status' => ['sometimes', 'in:active,unsubscribed,cleaned'],
            'source' => ['sometimes', 'nullable', 'string', 'max:255'],
            'reactivation_consent_text' => ['sometimes', 'nullable', 'string'],
            'fields' => ['sometimes', 'array'],
        ]);

        $payload = [];
        if (array_key_exists('status', $data)) { $payload['status'] = $data['status']; }
        if (array_key_exists('source', $data)) { $payload['source'] = $data['source']; }
        if (array_key_exists('reactivation_consent_text', $data)) { $payload['reactivation_consent_text'] = $data['reactivation_consent_text']; }
        foreach (($data['fields'] ?? []) as $key => $value) {
            $payload['field_' . $key] = $value;
        }

        Newsletter::updateFromAdmin($s, $payload);

        return $this->subscriber($request, $s->id);
    }

    public function deleteSubscriber(Request $request, int $subscriber): JsonResponse
    {
        $this->findSubscriber($subscriber)->delete();

        return response()->json(['success' => true]);
    }

    public function listDetail(Request $request, int $list): JsonResponse
    {
        return response()->json(['data' => $this->listPayload($this->findList($list))]);
    }

    public function storeList(Request $request): JsonResponse
    {
        $data = $this->validatedList($request);
        $data['site_id'] = Sites::getActive();
        $list = NewsletterList::create($data);

        return response()->json(['data' => $this->listPayload($list)], 201);
    }

    public function updateList(Request $request, int $list): JsonResponse
    {
        $l = $this->findList($list);
        $l->fill($this->validatedList($request))->save();

        return response()->json(['data' => $this->listPayload($l)]);
    }

    public function deleteList(Request $request, int $list): JsonResponse
    {
        $this->findList($list)->delete();

        return response()->json(['success' => true]);
    }

    /** @return array<string,mixed> */
    private function validatedList(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'locale' => ['sometimes', 'nullable', 'string', 'max:5'],
            'from_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'from_email' => ['sometimes', 'nullable', 'email'],
            'reply_to_email' => ['sometimes', 'nullable', 'email'],
            'notify_on_subscribe' => ['sometimes', 'boolean'],
            'notify_on_unsubscribe' => ['sometimes', 'boolean'],
            'send_rate_per_minute' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ]);
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

    public function fields(Request $request, int $list): JsonResponse
    {
        $l = $this->findList($list);
        return response()->json(['data' => $l->fields()->orderBy('sort')->get()->map(fn (NewsletterField $f) => $this->fieldPayload($f))->all()]);
    }

    public function storeField(Request $request, int $list): JsonResponse
    {
        $l = $this->findList($list);
        $data = $this->validatedField($request);
        $data['newsletter_list_id'] = $l->id;
        $field = NewsletterField::create($data);

        return response()->json(['data' => $this->fieldPayload($field)], 201);
    }

    public function createDefaultFields(Request $request, int $list): JsonResponse
    {
        $l = $this->findList($list);
        foreach ([['key' => 'voornaam', 'label' => 'Voornaam'], ['key' => 'achternaam', 'label' => 'Achternaam']] as $def) {
            if (! $l->fields()->where('key', $def['key'])->exists()) {
                NewsletterField::create(['newsletter_list_id' => $l->id, 'key' => $def['key'], 'label' => $def['label'], 'type' => NewsletterField::TYPE_TEXT]);
            }
        }

        return response()->json(['data' => $l->fields()->orderBy('sort')->get()->map(fn (NewsletterField $f) => $this->fieldPayload($f))->all()]);
    }

    public function updateField(Request $request, int $field): JsonResponse
    {
        $f = $this->findField($field);
        $f->fill($this->validatedField($request, $f))->save();

        return response()->json(['data' => $this->fieldPayload($f)]);
    }

    public function deleteField(Request $request, int $field): JsonResponse
    {
        $this->findField($field)->delete();

        return response()->json(['success' => true]);
    }

    /** @return array<string,mixed> */
    private function validatedField(Request $request, ?NewsletterField $existing = null): array
    {
        return $request->validate([
            'key' => ['required', 'string', 'max:255'],
            'label' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:text,number,date,select,checkbox'],
            'required' => ['sometimes', 'boolean'],
            'options' => ['sometimes', 'array'],
            'default_value' => ['sometimes', 'nullable', 'string'],
            'show_in_signup_form' => ['sometimes', 'boolean'],
            'sort' => ['sometimes', 'nullable', 'integer'],
        ]);
    }

    public function blockAddress(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'reason' => ['sometimes', 'string', 'max:255'],
            'note' => ['sometimes', 'nullable', 'string'],
        ]);

        $sup = NewsletterSuppression::block((string) Sites::getActive(), $data['email'], $data['reason'] ?? 'manual', 'app');
        if (array_key_exists('note', $data)) { $sup->notes = $data['note']; $sup->save(); }

        return response()->json(['data' => ['id' => $sup->id, 'email' => $sup->email, 'reason' => $sup->reason, 'source' => $sup->source, 'created_at' => optional($sup->created_at)->toIso8601String()]], 201);
    }

    public function unblock(Request $request, int $suppression): JsonResponse
    {
        NewsletterSuppression::where('site_id', Sites::getActive())->findOrFail($suppression)->delete();

        return response()->json(['success' => true]);
    }

    public function settings(Request $request): JsonResponse
    {
        $v = Customsetting::get('newsletter_default_list_id', Sites::getActive());

        return response()->json(['default_list_id' => $v !== null ? (int) $v : null]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate(['default_list_id' => ['sometimes', 'nullable', 'integer']]);
        Customsetting::set('newsletter_default_list_id', $data['default_list_id'] ?? null, (string) Sites::getActive());

        return $this->settings($request);
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
