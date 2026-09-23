@extends('layouts.site', [
    'titulo' => $artigo['titulo'],
    'descricao' => $artigo['resumo'],
    'tipoOg' => 'article',
    'secao' => 'site.blog',
])

@php
    use App\Support\Empresa;
@endphp

@section('content')
    <x-site.cabecalho selo="Blog" :href="route('site.blog')" :titulo="$artigo['manchete']"
                      icone="M5 4.5h11a2 2 0 0 1 2 2V19a1.5 1.5 0 0 0 1.5 1.5H6.5A1.5 1.5 0 0 1 5 19zM8 8.5h5M8 12h5M8 15.5h3">
        <x-slot:rodape>
            <p class="text-sm text-white/50">
                <time datetime="{{ $artigo['data'] }}">{{ $artigo['data_extenso'] }}</time>
                · {{ $artigo['leitura'] }} de leitura · Equipe {{ Empresa::marca() }}
            </p>
        </x-slot:rodape>
    </x-site.cabecalho>

    <section class="py-16 lg:py-20">
        <div class="mx-auto w-full max-w-[87rem] px-6">
            <article class="prosa">
                @include('paginas.site.artigos.'.$artigo['slug'])
            </article>
        </div>
    </section>

    <x-site.chamada :titulo="$artigo['chamada']">
        Conte como a sua empresa trabalha hoje. Respondemos com uma proposta clara,
        com escopo e investimento definidos.
    </x-site.chamada>
@endsection
