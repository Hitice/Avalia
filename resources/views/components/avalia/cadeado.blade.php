@props(['titulo' => 'Informação comercial suprimida'])

{{-- Cadeado discreto: marca a linha sem competir com o nome do servico.
     Transparente de proposito, porque e estado, nao alerta. --}}

<span class="cadeado" title="{{ $titulo }}">
    <svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round"
              d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75M6.75 10.5h10.5a1.5 1.5 0 0 1 1.5 1.5v6.75a1.5 1.5 0 0 1-1.5 1.5H6.75a1.5 1.5 0 0 1-1.5-1.5V12a1.5 1.5 0 0 1 1.5-1.5Z" />
    </svg>
    <span class="sr-only">{{ $titulo }}</span>
</span>
