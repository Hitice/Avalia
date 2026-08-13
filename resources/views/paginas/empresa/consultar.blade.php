@extends('layouts.app', ['title' => 'Consultar'])

@section('content')
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">Nova consulta</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $empresa->razao_social }}</p>
        </div>
        <x-avalia.ajuda assunto="Consulta">Falar com a Avalia</x-avalia.ajuda>
    </div>

    {{-- A consulta que acabou de sair abre aqui, por cima da tela: nao ha
         pagina de resultado no meio do caminho. --}}
    @if (request()->filled('laudo'))
        <div class="mb-6">
            <x-avalia.visor-laudo :url="route('empresa.consultas.pdf', (int) request('laudo'))"
                                  rotulo="Ver último relatório" :aberto="true" />
        </div>
    @endif

    @if (! $empresa->podeConsultar())
        <div class="aviso aviso-alerta mb-6">{{ $empresa->motivoSuspensao() }}</div>
    @endif

    @if ($pendentes->isNotEmpty())
        <div class="aviso aviso-alerta mb-6">
            As consultas ficam bloqueadas até o aceite dos documentos pendentes.
            <a class="font-medium underline" href="{{ route('empresa.documentos') }}">Ver documentos</a>
        </div>
    @endif

    @if (session('erro'))
        <div class="aviso aviso-erro mb-6">{{ session('erro') }}</div>
    @endif

    @if ($servicos->isEmpty())
        <div class="cartao p-6">
            <p class="text-sm text-gray-600 dark:text-gray-300">
                Nenhum serviço liberado para o seu plano. Fale com a sua equipe comercial.
            </p>
        </div>
    @else
        <x-avalia.cards-consulta :servicos="$servicos" :precos="$precos" :estrelas="$estrelas"
                                 :franquias="$franquias" :acao="route('empresa.consultas.executar')" />
    @endif
@endsection
