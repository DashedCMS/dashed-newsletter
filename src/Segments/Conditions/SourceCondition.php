<?php

declare(strict_types=1);

namespace Dashed\DashedNewsletter\Segments\Conditions;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Builder;
use Dashed\DashedNewsletter\Segments\Contracts\SegmentCondition;

class SourceCondition implements SegmentCondition
{
    public function key(): string
    {
        return 'subscriber.source';
    }

    public function label(): string
    {
        return 'Bron';
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
            // Net als bij StatusCondition verplicht: een lege waarde levert een
            // voorwaarde op die nooit waar is, maar er in het scherm uitziet
            // alsof hij iets doet.
            TextInput::make('value')
                ->label('Bron')
                ->required(),
        ];
    }

    public function apply(Builder $query, array $config, string $boolean): void
    {
        $operator = ($config['operator'] ?? 'is') === 'is_not' ? '!=' : '=';

        $query->where('source', $operator, $config['value'] ?? null, boolean: $boolean);
    }
}
