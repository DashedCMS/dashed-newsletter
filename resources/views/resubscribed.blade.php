@include('dashed-newsletter::partials.pagina-open', ['title' => 'Weer aangemeld'])

<div class="vinkje">&#10003;</div>
<h1>Je staat er weer op</h1>
<p>
    <span class="adres">{{ $email }}</span> ontvangt weer berichten van
    {{ $listName ?: 'deze nieuwsbrief' }}.
</p>
<p class="zacht" style="margin-bottom:0;">
    Onderaan elke mail staat opnieuw een afmeldlink, mocht je van gedachten veranderen.
</p>

@include('dashed-newsletter::partials.pagina-sluit')
