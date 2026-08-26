@include('dashed-newsletter::partials.pagina-open', ['title' => 'Afmelden'])

<h1>Wil je je afmelden?</h1>
<p>
    <span class="adres">{{ $email }}</span> krijgt nu berichten van
    {{ $listName ?: 'deze nieuwsbrief' }}. Klik hieronder om dat te stoppen.
</p>

{{-- Geen CSRF-veld: deze route staat buiten de web-groep, want hij komt uit
     een mailprogramma zonder sessie. De ondertekende URL is de beveiliging. --}}
<form method="POST" action="{{ $confirmUrl }}">
    <div class="veld">
        <label for="reason">Mogen we weten waarom? (niet verplicht)</label>
        <select name="reason" id="reason">
            <option value="">Liever niet zeggen</option>
            @foreach ($reasons as $waarde => $omschrijving)
                <option value="{{ $waarde }}">{{ $omschrijving }}</option>
            @endforeach
        </select>
    </div>

    <div class="veld">
        <label for="comment">Wil je er iets bij zeggen?</label>
        <textarea name="comment" id="comment" maxlength="1000" placeholder="Optioneel"></textarea>
    </div>

    <button type="submit" class="primair">Ja, meld me af</button>
</form>

<p class="zacht" style="margin-top:18px;margin-bottom:0;">
    Wil je toch blijven? Sluit dit venster gewoon, dan verandert er niets.
</p>

@include('dashed-newsletter::partials.pagina-sluit')
