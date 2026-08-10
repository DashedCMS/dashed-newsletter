<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Segments\Conditions;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Builder;
use Dashed\DashedNewsletter\Models\NewsletterField;
use Dashed\DashedNewsletter\Segments\Contracts\SegmentCondition;

class FieldCondition implements SegmentCondition
{
    public function key(): string
    {
        return 'field';
    }

    public function label(): string
    {
        return 'Waarde van een contactveld';
    }

    public function group(): string
    {
        return 'Contactvelden';
    }

    public function schema(): array
    {
        return [
            Select::make('key')
                ->label('Veld')
                ->options(fn () => NewsletterField::pluck('label', 'key')->all())
                ->required(),
            Select::make('operator')
                ->label('Vergelijking')
                ->options([
                    'is' => 'is gelijk aan',
                    'is_not' => 'is niet gelijk aan',
                    'contains' => 'bevat',
                    '>' => 'is groter dan',
                    '<' => 'is kleiner dan',
                    'is_empty' => 'is leeg',
                ])
                ->required(),
            TextInput::make('value')
                ->label('Waarde')
                ->visible(fn ($get) => $get('operator') !== 'is_empty'),
        ];
    }

    public function apply(Builder $query, array $config, string $boolean): void
    {
        $fieldKey = $config['key'] ?? null;
        $operator = $config['operator'] ?? 'is';
        $value = $config['value'] ?? null;

        $method = $boolean === 'or' ? 'orWhereHas' : 'whereHas';

        $query->{$method}('fieldValues', function (Builder $values) use ($fieldKey, $operator, $value): void {
            $values->whereHas('field', fn (Builder $f) => $f->where('key', $fieldKey));

            match ($operator) {
                'is' => $values->where('value', (string) $value),
                'is_not' => $values->where('value', '!=', (string) $value),
                'contains' => $values->where('value', 'like', '%' . $value . '%'),
                // Bewust op value_number: een tekstvergelijking zou '90' groter
                // maken dan '250' en dan valt het verkeerde contact in het segment.
                '>' => $values->where('value_number', '>', (float) $value),
                '<' => $values->where('value_number', '<', (float) $value),
                'is_empty' => $values->whereNull('value'),
                default => $values->whereRaw('1 = 0'),
            };
        });
    }
}
