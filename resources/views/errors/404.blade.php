@extends('layouts.site', [
    'titulo' => 'Página não encontrada',
    'descricao' => 'A página que você procurou não existe ou mudou de endereço.',
    'robots' => 'noindex',
])

@php
    use App\Support\Empresa;
@endphp

@section('content')
    <x-site.cabecalho selo="Erro 404" titulo="Esta página saiu do fluxo."
                      icone="M12 9v4.5M12 17h.01M10.3 4.3 2.9 17a2 2 0 0 0 1.7 3h14.8a2 2 0 0 0 1.7-3L13.7 4.3a2 2 0 0 0-3.4 0Z">
        <x-slot:rodape>
            <p class="max-w-2xl text-lg leading-relaxed text-white/60">
                O endereço que você acessou não existe ou mudou de lugar. Os caminhos abaixo levam
                de volta ao que importa.
            </p>

            <div class="mt-8 flex flex-wrap items-center gap-3">
                <x-avalia.botao :href="route('inicio')">
                    Ir para o início
                    <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6" />
                    </svg>
                </x-avalia.botao>

                <a href="{{ route('area') }}" class="botao border border-white/20 text-white transition hover:bg-white/10">
                    Área do produtor
                </a>

                <a href="{{ route('site.contato') }}" class="botao border border-white/20 text-white transition hover:bg-white/10">
                    Falar com a {{ Empresa::marca() }}
                </a>
            </div>
        </x-slot:rodape>
    </x-site.cabecalho>
@endsection
