@extends('layouts.app', ['title' => 'Consultar'])

@section('content')
    <x-avalia.cabecalho-pagina :titulo="$vendedor->ehAdmin() ? 'Consultar' : 'Minha carteira'"
                               :subtitulo="$vendedor->ehAdmin() ? 'Consulta da operação, sem cobrança' : 'Empresas, consultas e serviços'" />

    @unless ($vendedor->ehAdmin())
        @include('paginas.carteira.abas')

    <p class="subtitulo-pagina mb-6 -mt-2">{{ $vendedor->ehAdmin() ? 'Sem cobrança à empresa.' : 'Demonstração: ninguém é cobrado, o custo sai da sua comissão.' }} Restam {{ $restantes }} hoje.</p>
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
