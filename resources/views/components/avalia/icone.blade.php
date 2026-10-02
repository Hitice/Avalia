@props(['nome'])

{{-- O desenho vem de App\Support\Icones, o mapa unico. A cor e currentColor:
     quem chama diz o tom pela classe. --}}
<svg {{ $attributes->merge(['class' => 'size-4']) }} fill="none" stroke="currentColor"
     stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
    {!! App\Support\Icones::miolo($nome) !!}
</svg>
