@extends('layouts.app', ['title' => 'Consultar'])

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">
            {{ $vendedor->ehAdmin() ? 'Consultar' : 'Minha carteira' }}
        </h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            @if ($vendedor->ehAdmin())
                Consulta da operação: nenhuma empresa é cobrada e o custo do fornecedor entra
                no custo do período, sem comissão.
            @else
                Demonstração para fechar venda: consulte o documento do seu prospect e mostre
                o resultado na hora. Ninguém é cobrado; o custo sai da sua comissão.
            @endif
            Você ainda tem {{ $restantes }} {{ $restantes === 1 ? 'consulta' : 'consultas' }} hoje.
        </p>
    </div>

    @unless ($vendedor->ehAdmin())
        @include('paginas.carteira.abas')
    @endunless

    @if (session('erro'))
        <div class="aviso aviso-erro mb-6">{{ session('erro') }}</div>
    @endif

    {{-- A consulta que acabou de sair abre AQUI, por cima da grade: nao ha
         pagina de resultado no meio do caminho, e fechar o visor ja deixa a
         pessoa em frente aos cards para a proxima. --}}
    @if (request()->filled('laudo'))
        <div class="mb-6">
            <x-avalia.visor-laudo :url="route('carteira.demonstracoes.pdf', (int) request('laudo'))"
                                  rotulo="Ver último relatório" :aberto="true" />
        </div>
    @endif

    <x-avalia.cards-consulta :servicos="$servicos" :precos="$precos" :estrelas="$estrelas"
                             :acao="route('carteira.consultar.executar')" />
@endsection
