<div class="space-y-2" wire:key="campagne-preview">
    {{-- Een iframe en geen div: e-mail-HTML zit vol tabellen en inline
         stijlen, en de Tailwind van het beheerpaneel zou daar dwars doorheen
         lopen. In een iframe staat de mail in zijn eigen document. --}}
    {{-- sandbox="" en niet leeg laten: srcdoc erft zonder sandbox de origin van
         het beheerpaneel, dus een contactveld met script erin (zie
         CampaignRenderer::substitute()) zou dan in de sessie van de ingelogde
         beheerder draaien. Deze preview is puur om te bekijken, niet om in te
         klikken: geen scripts, geen formulieren, geen top-navigatie, geen
         same-origin. Een lege sandbox-waarde zet al die rechten uit. --}}
    <iframe
        title="Voorbeeld van de nieuwsbrief"
        class="w-full border border-gray-200 rounded bg-white"
        style="height: 70vh; {{ $breedte === 'smal' ? 'max-width: 400px;' : '' }}"
        sandbox=""
        srcdoc="{{ $html }}"
    ></iframe>
</div>
