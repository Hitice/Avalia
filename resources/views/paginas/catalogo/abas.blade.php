{{-- Navegacao entre as duas metades do modulo: a tabela e quem a contrata.
     Recebe $atual por @include. --}}

@php
    $abas = [
        'versoes' => ['rotulo' => 'Versoes', 'url' => route('catalogo.index')],
        'planos' => ['rotulo' => 'Planos', 'url' => route('catalogo.planos.index')],
    ];
@endphp

<div class="mb-6 flex gap-2">
    @foreach ($abas as $chave => $aba)
        <a href="{{ $aba['url'] }}"
           class="{{ $atual === $chave
               ? 'bg-brand-500 text-white'
               : 'bg-white text-gray-600 hover:bg-gray-50 dark:bg-white/[0.03] dark:text-gray-300 dark:hover:bg-white/[0.06]' }} rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium dark:border-gray-800">
            {{ $aba['rotulo'] }}
        </a>
    @endforeach
</div>
