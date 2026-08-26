{{--
    De rij kerncijfers. Twee dingen zijn hier bewuste keuzes en geen opmaak.

    Een percentage dat null is betekent "niet gemeten" en toont een streepje,
    geen nul procent. Het verschil tussen "niemand opende" en "we meten niet"
    is de hele reden dat dit scherm bestaat.

    En bij het openingspercentage staat de kanttekening over Apple Mail
    Privacy Protection in beeld, met klik na opening ernaast. Een getal dat je
    moet wantrouwen hoort die waarschuwing bij zich te dragen.
--}}
@php
    $toon = fn (?float $waarde): string => $waarde === null ? '-' : number_format($waarde, 1, ',', '.') . '%';
    // Waar de percentages tegen afgezet zijn. Zonder Postmark-koppeling komt
    // er nooit een bezorgbevestiging binnen, en dan is verzonden het beste
    // wat we zelf zeker weten. Dat hoort erbij te staan: anders verandert een
    // getal stilletjes van betekenis zodra er wel webhooks binnenkomen, en
    // vergelijk je twee campagnes die niet hetzelfde meten.
    $grondslag = $cijfers['percentageBase'] === 'delivered' ? 'van bezorgd' : 'van verzonden';
@endphp

<div class="grid grid-cols-2 gap-4 md:grid-cols-4">
    <div>
        <div class="text-sm text-gray-500">Ontvangers</div>
        <div class="text-2xl font-semibold">{{ $cijfers['recipients'] }}</div>
    </div>
    <div>
        <div class="text-sm text-gray-500">Verzonden</div>
        <div class="text-2xl font-semibold">{{ $cijfers['sent'] }}</div>
        @if ($cijfers['failed'] || $cijfers['skipped'])
            <div class="text-xs text-gray-500">
                {{ $cijfers['failed'] }} mislukt, {{ $cijfers['skipped'] }} overgeslagen
            </div>
        @endif
    </div>
    {{-- Een streepje en geen nul als er niets over bezorging bekend is. Nul
         leest als "er kwam niets aan", en dat weten we niet: we weten alleen
         dat niemand het ons verteld heeft. --}}
    <div>
        <div class="text-sm text-gray-500">Bezorgd</div>
        @if ($cijfers['hasDeliveryInfo'])
            <div class="text-2xl font-semibold">{{ $cijfers['delivered'] }}</div>
            <div class="text-xs text-gray-500">{{ $toon($cijfers['deliveredPercentage']) }}</div>
        @else
            <div class="text-2xl font-semibold text-gray-400">-</div>
            <div class="text-xs text-gray-500">Niet gemeten</div>
        @endif
    </div>
    <div>
        <div class="text-sm text-gray-500">Gebounced</div>
        @if ($cijfers['hasDeliveryInfo'])
            <div class="text-2xl font-semibold">{{ $cijfers['bounced'] }}</div>
        @else
            <div class="text-2xl font-semibold text-gray-400">-</div>
            <div class="text-xs text-gray-500">Niet gemeten</div>
        @endif
    </div>

    <div>
        <div class="text-sm text-gray-500">Geopend</div>
        @if ($cijfers['tracksOpens'])
            <div class="text-2xl font-semibold">{{ $toon($cijfers['openPercentage']) }}</div>
            <div class="text-xs text-gray-500">
                {{ $cijfers['openers'] }} openers {{ $grondslag }}, {{ $cijfers['opens'] }} keer geopend
            </div>
        @else
            <div class="text-2xl font-semibold text-gray-400">-</div>
            <div class="text-xs text-gray-500">Openen wordt niet gemeten voor deze lijst</div>
        @endif
    </div>
    <div>
        <div class="text-sm text-gray-500">Geklikt</div>
        @if ($cijfers['tracksClicks'])
            <div class="text-2xl font-semibold">{{ $toon($cijfers['clickPercentage']) }}</div>
            <div class="text-xs text-gray-500">
                {{ $cijfers['clickers'] }} klikkers {{ $grondslag }}, {{ $cijfers['clicks'] }} klikken
            </div>
        @else
            <div class="text-2xl font-semibold text-gray-400">-</div>
            <div class="text-xs text-gray-500">Klikken wordt niet gemeten voor deze lijst</div>
        @endif
    </div>
    <div>
        <div class="text-sm text-gray-500">Klik na opening</div>
        <div class="text-2xl font-semibold">{{ $toon($cijfers['clickToOpenPercentage']) }}</div>
        <div class="text-xs text-gray-500">Zegt meer over de inhoud dan het klikpercentage</div>
    </div>
    <div>
        <div class="text-sm text-gray-500">Afgemeld</div>
        <div class="text-2xl font-semibold">{{ $cijfers['unsubscribed'] }}</div>
        <div class="text-xs text-gray-500">{{ $toon($cijfers['unsubscribePercentage']) }} {{ $grondslag }}</div>
    </div>
</div>

@unless ($cijfers['hasDeliveryInfo'])
    <p class="mt-4 text-xs text-gray-500">
        Er is niets bekend over bezorging, bounces en spamklachten. Die komen binnen via de webhook
        van de mailprovider, en die bereikt deze installatie niet. Alles wat hierboven wel staat is
        door de website zelf gemeten en klopt dus onafhankelijk daarvan; de percentages zijn daarom
        afgezet tegen het aantal verzonden mails.
    </p>
@endunless

@if ($cijfers['durationInSeconds'] !== null)
    <p class="mt-4 text-xs text-gray-500">
        De verzending duurde
        {{ \Illuminate\Support\Carbon::now()->subSeconds($cijfers['durationInSeconds'])->diffForHumans(null, true) }}.
    </p>
@endif

@if ($cijfers['tracksOpens'])
    <p class="mt-4 text-xs text-gray-500">
        Let op bij het openingspercentage: Apple Mail haalt het meetplaatje standaard op zonder dat
        iemand kijkt, dus dit getal valt structureel te hoog uit. Klik na opening is het cijfer waar
        je op kunt sturen.
    </p>
@endif

@if ($links !== [])
    <div class="mt-6">
        <div class="mb-2 text-sm font-medium">Waar is op geklikt</div>
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-gray-500">
                    <th class="py-1">Link</th>
                    <th class="py-1">Klikkers</th>
                    <th class="py-1">Klikken</th>
                    <th class="py-1">Aandeel</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($links as $link)
                    <tr class="border-t border-gray-200">
                        <td class="py-1 break-all">{{ $link['url'] }}</td>
                        <td class="py-1">{{ $link['clickers'] }}</td>
                        <td class="py-1">{{ $link['clicks'] }}</td>
                        <td class="py-1">{{ number_format($link['share'], 1, ',', '.') }}%</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
