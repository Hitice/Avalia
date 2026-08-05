@extends('layouts.app', ['title' => 'Auditoria'])

@php
    use App\Support\Dinheiro;

    $rotulosAcao = [
        'fatura.fechada' => 'Fatura fechada',
        'fatura.liquidada' => 'Pagamento confirmado',
        'cliente.inadimplente' => 'Empresa marcada como inadimplente',
        'documento.aceito' => 'Documento aceito',
    ];
    $rotulosEntidade = [
        App\Models\Fatura::class => 'Fatura',
        App\Models\Cliente::class => 'Empresa',
        App\Models\DocumentoLegal::class => 'Documento',
    ];
    $rotulosDetalhe = [
        'competencia' => 'Período',
        'total_cents' => 'Valor da fatura',
        'franquia_cents' => 'Valor incluído na franquia',
        'comissao_liberada_cents' => 'Comissão liberada',
        'fatura_id' => 'Fatura',
        'cliente_id' => 'Empresa',
        'versao' => 'Versão',
        'hash_conteudo' => 'Comprovante do conteúdo',
    ];
@endphp

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">Auditoria</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Acompanhe as ações registradas na plataforma.
        </p>
    </div>

    @if ($acoes->isNotEmpty())
        <div class="mb-6 flex flex-wrap gap-2">
            <a href="{{ route('auditoria') }}"
               class="segmento {{ $acao === '' ? 'segmento-ativo' : 'segmento-inativo' }}">Todas</a>

            @foreach ($acoes as $opcao)
                <a href="{{ route('auditoria', ['acao' => $opcao]) }}"
                   class="segmento {{ $acao === $opcao ? 'segmento-ativo' : 'segmento-inativo' }}">{{ $rotulosAcao[$opcao] ?? 'Outras ações' }}</a>
            @endforeach
        </div>
    @endif

    <div class="cartao overflow-hidden">
        <div class="overflow-x-auto">
            <table class="tabela min-w-[52rem]">
                <thead class="tabela-cabecalho">
                    <tr>
                        <th class="px-5 py-3 text-left font-medium">Data e hora</th>
                        <th class="px-5 py-3 text-left font-medium">Responsável</th>
                        <th class="px-5 py-3 text-left font-medium">Atividade</th>
                        <th class="px-5 py-3 text-left font-medium">Registro</th>
                        <th class="px-5 py-3 text-left font-medium">Informações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($registros as $registro)
                        <tr>
                            <td class="px-5 py-4 text-left whitespace-nowrap text-gray-600 dark:text-gray-300">
                                {{ $registro->ocorreu_em?->format('d/m/Y H:i') }}
                            </td>
                            <td class="px-5 py-4 text-left text-gray-800 dark:text-white/90">
                                {{ $registro->staff?->nome ?? 'sistema' }}
                                @if ($registro->ip_address)
                                    <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">
                                        Origem: {{ $registro->ip_address }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-left">
                                <span class="text-sm text-gray-700 dark:text-gray-300">{{ $rotulosAcao[$registro->acao] ?? 'Ação registrada' }}</span>
                            </td>
                            <td class="px-5 py-4 text-left text-xs text-gray-500 dark:text-gray-400">
                                {{ $rotulosEntidade[$registro->entidade_tipo] ?? 'Registro' }}
                                @if ($registro->entidade_id) #{{ $registro->entidade_id }} @endif
                            </td>
                            <td class="px-5 py-4 text-left text-xs text-gray-500 dark:text-gray-400">
                                @foreach ((array) $registro->dados as $chave => $valor)
                                    <span class="mr-3 whitespace-nowrap">
                                        {{ $rotulosDetalhe[$chave] ?? 'Informação' }}:
                                        <span class="text-gray-700 dark:text-gray-300">
                                            @if ($chave === 'hash_conteudo')
                                                Registrado
                                            @elseif (str_ends_with($chave, '_cents'))
                                                {{ Dinheiro::brl((int) $valor) }}
                                            @else
                                                {{ is_scalar($valor) ? $valor : json_encode($valor) }}
                                            @endif
                                        </span>
                                    </span>
                                @endforeach
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="tabela-vazia">Nenhum registro.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($registros->hasPages())
        <div class="mt-6">{{ $registros->links() }}</div>
    @endif
@endsection
