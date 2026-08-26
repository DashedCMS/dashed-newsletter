@include('dashed-newsletter::partials.pagina-open', ['title' => 'Afgemeld'])

<div class="vinkje">&#10003;</div>
<h1>Je bent afgemeld</h1>
<p>
    <span class="adres">{{ $email }}</span> krijgt geen berichten meer van
    {{ $listName ?: 'deze nieuwsbrief' }}.
</p>

@isset($resubscribeUrl)
    <p class="zacht">Was dit een vergissing?</p>
    <form method="POST" action="{{ $resubscribeUrl }}">
        <button type="submit" class="secundair">Toch weer aanmelden</button>
    </form>
@endisset

@include('dashed-newsletter::partials.pagina-sluit')
