{{--
    Waarom mensen zich afmeldden. Het aantal zonder reden staat er bewust bij:
    zonder dat getal lijkt het alsof iedereen antwoordde, en dan overschat je
    hoe representatief de rest is.
--}}
@if ($totaal === 0)
    <p class="text-sm text-gray-500">Nog niemand heeft zich afgemeld.</p>
@else
    <div class="mb-3 text-sm text-gray-500">
        {{ $totaal }} {{ $totaal === 1 ? 'afmelding' : 'afmeldingen' }},
        waarvan {{ $zonderReden }} zonder opgegeven reden.
    </div>

    @if ($redenen !== [])
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-gray-500">
                    <th class="py-1">Reden</th>
                    <th class="py-1">Aantal</th>
                    <th class="py-1">Aandeel</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($redenen as $sleutel => $aantal)
                    <tr class="border-t border-gray-200">
                        <td class="py-1">{{ $omschrijvingen[$sleutel] ?? $sleutel }}</td>
                        <td class="py-1">{{ $aantal }}</td>
                        <td class="py-1">{{ number_format(($aantal / $totaal) * 100, 1, ',', '.') }}%</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if ($toelichtingen !== [])
        <div class="mt-6">
            <div class="mb-2 text-sm font-medium">Wat mensen erbij schreven</div>
            <ul class="space-y-2 text-sm">
                @foreach ($toelichtingen as $regel)
                    <li class="border-t border-gray-200 pt-2">
                        <div>{{ $regel['comment'] }}</div>
                        <div class="text-xs text-gray-500">
                            {{ $regel['email'] }}
                            @if ($regel['reason']) &middot; {{ $omschrijvingen[$regel['reason']] ?? $regel['reason'] }} @endif
                            @if ($regel['campaign']) &middot; {{ $regel['campaign'] }} @endif
                            @if ($regel['at']) &middot; {{ $regel['at'] }} @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
@endif
