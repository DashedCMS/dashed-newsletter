<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Segments\Conditions;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Builder;
use Dashed\DashedNewsletter\Segments\Contracts\SegmentCondition;

class SubscribedAtCondition implements SegmentCondition
{
    public function key(): string
    {
        return 'subscriber.subscribed_at';
    }

    public function label(): string
    {
        return 'Aangemeld';
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
                ->options([
                    'last_days' => 'in de laatste x dagen',
                    'before_days' => 'langer dan x dagen geleden',
                ])
                ->required(),
            TextInput::make('value')
                ->label('Aantal dagen')
                ->numeric()
                ->required(),
        ];
    }

    public function apply(Builder $query, array $config, string $boolean): void
    {
        $days = (int) ($config['value'] ?? 0);
        $moment = now()->subDays($days);
        $operator = ($config['operator'] ?? 'last_days') === 'before_days' ? '<' : '>=';

        $query->where('subscribed_at', $operator, $moment, boolean: $boolean);
    }
}
