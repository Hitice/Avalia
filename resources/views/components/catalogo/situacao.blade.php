@props(['versao'])

{{-- Etiqueta da situacao da versao. Ativa e a unica que vende. --}}

@php
    $cores = [
        'rascunho' => 'bg-gray-100 text-gray-600 dark:bg-white/5 dark:text-gray-300',
        'agendada' => 'bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-400',
        'ativa' => 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-400',
        'encerrada' => 'bg-gray-100 text-gray-500 dark:bg-white/5 dark:text-gray-400',
    ];
@endphp

<span class="{{ $cores[$versao->situacao] ?? $cores['rascunho'] }} inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium">
    {{ $versao->rotuloSituacao() }}
</span>
