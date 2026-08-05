@extends('layouts.app', ['title' => 'Documentos'])

@section('content')
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">Documentos</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Publique versões atualizadas para clientes e equipe.</p>
        </div>
        <x-avalia.botao :href="route('documentos.criar')">Novo documento</x-avalia.botao>
    </div>
    @include('paginas.catalogo.avisos')
    <div class="cartao overflow-hidden"><div class="overflow-x-auto"><table class="tabela min-w-[48rem]">
        <thead class="tabela-cabecalho"><tr><th class="px-5 py-3 text-left font-medium">Documento</th><th class="px-5 py-3 text-left font-medium">Tipo</th><th class="px-5 py-3 text-left font-medium">Versão</th><th class="px-5 py-3 text-left font-medium">Situação</th><th class="px-5 py-3 text-left font-medium">Aceite</th></tr></thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
            @forelse ($documentos as $documento)
                <tr><td class="px-5 py-4 font-medium text-gray-800 dark:text-white/90">{{ $documento->titulo }}</td><td class="px-5 py-4">{{ $documento->tipo }}</td><td class="px-5 py-4">{{ $documento->versao }}</td><td class="px-5 py-4"><span class="etiqueta {{ $documento->ativo ? 'etiqueta-sucesso' : 'etiqueta-neutra' }}">{{ $documento->ativo ? 'vigente' : 'histórico' }}</span></td><td class="px-5 py-4">{{ $documento->exige_aceite ? 'obrigatório' : 'opcional' }}</td></tr>
            @empty
                <tr><td colspan="5" class="tabela-vazia">Nenhum documento publicado.</td></tr>
            @endforelse
        </tbody>
    </table></div></div>
@endsection
