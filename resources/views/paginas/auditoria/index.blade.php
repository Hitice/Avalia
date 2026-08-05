@extends('layouts.app', ['title' => 'Auditoria'])

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">Auditoria</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Quem fez o quê, quando e de onde. Registro apenas de leitura.
        </p>
    </div>

    @if ($acoes->isNotEmpty())
        <div class="mb-6 flex flex-wrap gap-2">
            <a href="{{ route('auditoria') }}"
               class="segmento {{ $acao === '' ? 'segmento-ativo' : 'segmento-inativo' }}">Todas</a>

            @foreach ($acoes as $opcao)
                <a href="{{ route('auditoria', ['acao' => $opcao]) }}"
                   class="segmento {{ $acao === $opcao ? 'segmento-ativo' : 'segmento-inativo' }}">{{ $opcao }}</a>
            @endforeach
        </div>
    @endif

    <div class="cartao overflow-hidden">
        <div class="overflow-x-auto">
            <table class="tabela min-w-[52rem]">
                <thead class="tabela-cabecalho">
                    <tr>
                        <th class="px-5 py-3 text-left font-medium">Quando</th>
                        <th class="px-5 py-3 text-left font-medium">Quem</th>
                        <th class="px-5 py-3 text-left font-medium">Ação</th>
                        <th class="px-5 py-3 text-left font-medium">Sobre</th>
                        <th class="px-5 py-3 text-left font-medium">Detalhes</th>
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
                                        {{ $registro->ip_address }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-left">
                                <code class="text-xs text-gray-600 dark:text-gray-300">{{ $registro->acao }}</code>
                            </td>
                            <td class="px-5 py-4 text-left text-xs text-gray-500 dark:text-gray-400">
                                {{ class_basename($registro->entidade_tipo ?? '') }}
                                @if ($registro->entidade_id) #{{ $registro->entidade_id }} @endif
                            </td>
                            <td class="px-5 py-4 text-left text-xs text-gray-500 dark:text-gray-400">
                                @foreach ((array) $registro->dados as $chave => $valor)
                                    <span class="mr-3 whitespace-nowrap">
                                        {{ $chave }}: <span class="text-gray-700 dark:text-gray-300">{{ is_scalar($valor) ? $valor : json_encode($valor) }}</span>
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
