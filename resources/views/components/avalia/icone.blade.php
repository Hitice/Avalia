@props(['nome'])

{{-- Icone de acao. So os que estao em uso: icone sem tela que o chame e peso
     morto, e o projeto ja carregou nove desses vindos do boilerplate. --}}

@php
    $caminhos = [
        'lapis' => 'M16.862 4.487l1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z',
    ];
@endphp

<svg {{ $attributes->merge(['class' => 'size-4']) }} fill="none" stroke="currentColor"
     stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $caminhos[$nome] }}" />
</svg>
