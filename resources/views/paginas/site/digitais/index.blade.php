@extends('layouts.site', [
    'titulo' => 'Serviços digitais',
    'descricao' => 'QR Code dinâmico, cujo destino muda depois de impresso, e gerador de QR Code grátis em SVG e PNG.',
])

@section('content')
    <x-site.cabecalho selo="Serviços" titulo="Serviços digitais, com preço de tabela"
                      icone="M4 4h6v6H4V4Zm10 0h6v6h-6V4ZM4 14h6v6H4v-6Zm10 4h2m-2-4h6m-2 4v2">
        Serviços de contratação imediata, com preço de tabela e escopo definido. Sem
        levantamento e sem proposta: o mesmo serviço, pelo mesmo valor, para todos os clientes.
    </x-site.cabecalho>

    <section class="py-16 lg:py-20">
        <div class="mx-auto w-full max-w-[87rem] px-6">
            <div class="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($servicos as $servico)
                    <x-site.cartao-servico :servico="$servico" />
                @endforeach
            </div>
        </div>
    </section>

    <x-site.porta-acesso />

    <x-site.chamada titulo="Precisa de algo que não está nesta lista?">
        Sistemas sob medida são desenvolvidos sob demanda, com escopo e investimento definidos em
        proposta.
    </x-site.chamada>
@endsection
