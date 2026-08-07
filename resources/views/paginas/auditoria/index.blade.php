@extends('layouts.app', ['title' => 'Auditoria'])

@php
    use App\Support\Rotulos;
@endphp

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">Auditoria</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Histórico das ações feitas na plataforma.
        </p>
    </div>

    @if ($acoes->isNotEmpty())
        <div class="mb-6 flex flex-wrap gap-2">
            <a href="{{ route('auditoria') }}"
               class="segmento {{ $acao === '' ? 'segmento-ativo' : 'segmento-inativo' }}">Todas</a>

            @foreach ($acoes as $opcao)
                <a href="{{ route('auditoria', ['acao' => $opcao]) }}"
                   class="segmento {{ $acao === $opcao ? 'segmento-ativo' : 'segmento-inativo' }}">{{ Rotulos::acao($opcao) }}</a>
            @endforeach
        </div>
    @endif

    <div class="cartao overflow-hidden">
        <div class="tabela-rolagem">
            <table class="tabela min-w-[52rem]">
                <thead class="tabela-cabecalho tabela-cabecalho-fixo">
                    <tr>
                        <th class="px-5 py-3 text-left font-medium">Data e hora</th>
                        <th class="px-5 py-3 text-left font-medium">Responsável</th>
                        <th class="px-5 py-3 text-left font-medium">Atividade</th>
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
                                {{ $registro->staff?->nome ?? 'Rotina automática' }}
                                @if ($registro->ip_address)
                                    <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">
                                        Origem: {{ $registro->ip_address }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-left">
                                <span class="text-sm text-gray-700 dark:text-gray-300">{{ Rotulos::acao($registro->acao) }}</span>
                            </td>
                            <td class="px-5 py-4 text-left text-sm text-gray-700 dark:text-gray-300">
                                {{-- O nome congelado no registro; tipo e numero como reserva
                                     para as linhas anteriores a existencia do rotulo. --}}
                                @if ($registro->entidade_rotulo)
                                    {{ $registro->entidade_rotulo }}
                                    <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">
                                        {{ Rotulos::entidadeAuditada($registro->entidade_tipo) }}
                                    </span>
                                @elseif ($registro->entidade_tipo)
                                    {{ Rotulos::entidadeAuditada($registro->entidade_tipo) }}@if ($registro->entidade_id) nº {{ $registro->entidade_id }}@endif
                                @endif
                            </td>
                            <td class="px-5 py-4 text-left text-xs text-gray-500 dark:text-gray-400">
                                @foreach ((array) $registro->dados as $chave => $valor)
                                    <span class="mr-3 whitespace-nowrap">
                                        {{ Rotulos::detalheAuditado($chave) }}:
                                        <span class="text-gray-700 dark:text-gray-300">{{ Rotulos::valorAuditado($chave, $valor) }}</span>
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
