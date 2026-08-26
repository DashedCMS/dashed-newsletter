<x-filament-panels::page>
    @if (count($lijsten) > 1)
        <div class="mb-4">
            <label for="lijstId" class="mb-1 block text-sm text-gray-500">Lijst</label>
            <select wire:model.live="lijstId" id="lijstId"
                class="rounded-lg border-gray-300 text-sm dark:bg-gray-900 dark:border-gray-700">
                <option value="">Alle lijsten</option>
                @foreach ($lijsten as $id => $naam)
                    <option value="{{ $id }}">{{ $naam }}</option>
                @endforeach
            </select>
        </div>
    @endif

    @include('dashed-newsletter::filament.unsubscribe-reasons', [
        'totaal' => $totaal,
        'zonderReden' => $zonderReden,
        'redenen' => $redenen,
        'toelichtingen' => $toelichtingen,
        'omschrijvingen' => $omschrijvingen,
    ])
</x-filament-panels::page>
