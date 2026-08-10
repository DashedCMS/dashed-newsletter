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
                ->label('Vergelijking')
                ->options(['is' => 'is', 'is_not' => 'is niet'])
                ->required(),
            Select::make('value')
                ->label('Status')
                ->options([
                    NewsletterSubscriber::STATUS_ACTIVE => 'Actief',
                    NewsletterSubscriber::STATUS_UNSUBSCRIBED => 'Uitgeschreven',
                    NewsletterSubscriber::STATUS_CLEANED => 'Opgeschoond',
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
