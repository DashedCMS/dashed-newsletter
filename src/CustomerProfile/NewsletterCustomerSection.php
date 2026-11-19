<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\CustomerProfile;

use Dashed\DashedNewsletter\Models\NewsletterSubscriber;
use Dashed\DashedNewsletter\Models\NewsletterSuppression;
use Dashed\DashedNewsletter\Filament\Resources\NewsletterSubscriberResource;

/**
 * Nieuwsbriefstatus van een klant voor het klantprofiel in ec-core
 * (cms()->builder('customerProfileSections')). Per lijst de status, en of
 * het adres op de blokkadelijst staat.
 */
class NewsletterCustomerSection
{
    public static function label(): string
    {
        return __('Nieuwsbrief');
    }

    public static function sort(): int
    {
        return 50;
    }

    public static function forCustomer(?int $userId, ?string $email): ?array
    {
        $subscribers = NewsletterSubscriber::query()
            ->where(fn ($q) => $q->whereRaw('1 = 0')
                ->when($userId, fn ($q) => $q->orWhere('user_id', $userId))
                ->when($email, fn ($q) => $q->orWhere('email', $email)))
            ->with('list')
            ->orderBy('id')
            ->get();

        $suppressed = $email && NewsletterSuppression::query()->where('email', $email)->exists();

        $statuses = [
            NewsletterSubscriber::STATUS_ACTIVE => __('Ingeschreven'),
            NewsletterSubscriber::STATUS_UNSUBSCRIBED => __('Uitgeschreven'),
            NewsletterSubscriber::STATUS_CLEANED => __('Opgeschoond'),
        ];

        return [
            'stats' => array_values(array_filter([
                ['label' => __('Actieve inschrijvingen'), 'value' => (string) $subscribers->where('status', NewsletterSubscriber::STATUS_ACTIVE)->count()],
                $suppressed ? ['label' => __('Blokkadelijst'), 'value' => __('Ja, ontvangt niets')] : null,
            ])),
            'columns' => [
                'list' => __('Lijst'),
                'status' => __('Status'),
                'since' => __('Sinds'),
            ],
            'rows' => $subscribers->map(fn (NewsletterSubscriber $subscriber) => [
                'list' => (string) ($subscriber->list?->name ?? '-'),
                'status' => (string) ($statuses[$subscriber->status] ?? $subscriber->status),
                'since' => ($subscriber->status === NewsletterSubscriber::STATUS_UNSUBSCRIBED ? $subscriber->unsubscribed_at : $subscriber->subscribed_at)?->format('d-m-Y'),
                '_url' => rescue(fn () => NewsletterSubscriberResource::getUrl('view', ['record' => $subscriber->id]), null, false),
            ])->all(),
            'empty' => __('Niet ingeschreven voor de nieuwsbrief.'),
        ];
    }
}
