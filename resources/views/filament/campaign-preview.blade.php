<div class="space-y-2" wire:key="campagne-preview">
    <div class="flex items-center gap-2">
        <button type="button" wire:click="$set('breedte', 'breed')"
            @class(['px-2 py-1 text-xs rounded', 'bg-primary-600 text-white' => $breedte === 'breed', 'bg-gray-100' => $breedte !== 'breed'])>
            Breed
        </button>
        <button type="button" wire:click="$set('breedte', 'smal')"
            @class(['px-2 py-1 text-xs rounded', 'bg-primary-600 text-white' => $breedte === 'smal', 'bg-gray-100' => $breedte !== 'smal'])>
            Telefoon
        </button>
    </div>

    {{-- Een iframe en geen div: e-mail-HTML zit vol tabellen en inline
         stijlen, en de Tailwind van het beheerpaneel zou daar dwars doorheen
         lopen. In een iframe staat de mail in zijn eigen document. --}}
    <iframe
        title="Voorbeeld van de nieuwsbrief"
        class="w-full border border-gray-200 rounded bg-white"
        style="height: 70vh; {{ $breedte === 'smal' ? 'max-width: 400px;' : '' }}"
        srcdoc="{{ $html }}"
    ></iframe>
</div>
