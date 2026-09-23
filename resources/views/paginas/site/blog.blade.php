@extends('layouts.site', [
    'titulo' => 'Blog',
    'descricao' => 'Artigos práticos sobre automação de processos, atendimento humanizado no WhatsApp, cobrança e tecnologia para empresas.',
    'secao' => 'site.blog',
])

@section('content')
    <x-site.cabecalho selo="Blog" titulo="Tecnologia na prática">
        Artigos diretos sobre automação, atendimento humanizado e tecnologia para empresas,
        escritos por quem desenvolve essas soluções.
    </x-site.cabecalho>

    <section class="py-16 lg:py-20">
        <div class="mx-auto w-full max-w-[87rem] px-6">
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($artigos as $artigo)
                    <a href="{{ route('site.artigo', $artigo['slug']) }}" class="bloco group">
                        <span class="text-xs text-gray-500">
                            <time datetime="{{ $artigo['data'] }}">{{ $artigo['data_extenso'] }}</time>
                            · {{ $artigo['leitura'] }} de leitura
                        </span>
                        <h2 class="text-lg font-semibold text-gray-900">{{ $artigo['manchete'] }}</h2>
                        <p class="text-sm leading-relaxed text-gray-600">{{ $artigo['resumo'] }}</p>
                        <span class="mt-auto inline-flex items-center gap-1.5 text-sm font-medium text-brand-600">
                            Ler artigo
                            <svg class="size-4 transition group-hover:translate-x-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6" />
                            </svg>
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
    </section>
@endsection
