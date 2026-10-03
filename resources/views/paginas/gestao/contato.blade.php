@extends('layouts.app', ['title' => $contato->nome])

@section('content')
    <x-avalia.cabecalho-pagina :titulo="$contato->nome">
        <x-slot:subtitulo>
            {{ $contato->documento ? App\Support\Documento::formatar($contato->documento) : 'sem documento' }}
            {{ $contato->whatsapp ? ' · '.$contato->whatsapp : '' }}{{ $contato->email ? ' · '.$contato->email : '' }}
        </x-slot:subtitulo>
        <x-avalia.botao variante="secundario" :href="route('gestao.contatos')">Voltar</x-avalia.botao>
    </x-avalia.cabecalho-pagina>

    <div class="grid gap-6 lg:grid-cols-[1fr_1.6fr]">
        <div class="cartao p-6">
            <h2 class="titulo-cartao">Onde ele está</h2>
            <ul class="mt-4 space-y-3">
                @forelse ($contato->vinculos as $vinculo)
                    <li class="flex items-center justify-between gap-3 text-sm">
                        <span class="text-gray-800 dark:text-white/90">
                            <span class="etiqueta etiqueta-neutra mr-2">{{ $vinculo->papel }}</span>
                            {{ class_basename($vinculo->entidade_tipo) }} #{{ $vinculo->entidade_id }}
                        </span>
                        <span class="ajuda-campo">desde {{ $vinculo->created_at->format('d/m/Y') }}</span>
                    </li>
                @empty
                    <li class="tabela-vazia">Sem vínculo.</li>
                @endforelse
            </ul>
        </div>

        <div class="cartao overflow-hidden">
            <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                <h2 class="titulo-cartao">Linha do tempo</h2>
            </div>
            <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($contato->interacoes as $interacao)
                    <li class="flex items-start justify-between gap-3 px-6 py-3 text-sm">
                        <span class="text-gray-800 dark:text-white/90">
                            {{ $interacao->descricao }}
                            <span class="ajuda-campo">{{ $interacao->tipo }}{{ $interacao->staff ? ' · '.$interacao->staff->nome : '' }}</span>
                        </span>
                        <span class="shrink-0 tabular-nums text-gray-500 dark:text-gray-400">{{ $interacao->ocorrido_em->format('d/m/Y H:i') }}</span>
                    </li>
                @empty
                    <li class="tabela-vazia">Nada registrado ainda.</li>
                @endforelse
            </ul>
        </div>
    </div>
@endsection
