<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Segments\Conditions;

use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Builder;
use Dashed\DashedNewsletter\Models\NewsletterSubscriber;
use Dashed\DashedNewsletter\Segments\Contracts\SegmentCondition;

class StatusCondition implements SegmentCondition
{
    public function key(): string
    {
        return 'subscriber.status';
    }

    public function label(): string
    {
        return 'Status';
    }

    public function group(): string
    {
        return 'Aanmelding';
    }

    public function schema(): array
    {
        return [
            Select::make('operator')
                ->label(__('Vergelijking'))
                ->options(['is' => __('is'), 'is_not' => __('is niet')])
                ->required(),
            Select::make('value')
                ->label(__('Status'))
                ->options([
                    NewsletterSubscriber::STATUS_ACTIVE => __('Actief'),
                    NewsletterSubscriber::STATUS_UNSUBSCRIBED => __('Uitgeschreven'),
                    NewsletterSubscriber::STATUS_CLEANED => __('Opgeschoond'),
                ])
                ->required(),
        ];
    }

    public function apply(Builder $query, array $config, string $boolean): void
    {
        $operator = ($config['operator'] ?? 'is') === 'is_not' ? '!=' : '=';

        $query->where('status', $operator, $config['value'] ?? null, boolean: $boolean);
    }
}
