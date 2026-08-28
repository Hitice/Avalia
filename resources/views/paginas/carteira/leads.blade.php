@extends('layouts.app', ['title' => 'Leads'])

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">Leads</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Os leads que a administração distribuiu para você.
        </p>
    </div>

    @include('paginas.catalogo.avisos')

    @if ($total > 0 || $convertidos > 0)
        <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-avalia.cartao-indicador rotulo="Leads" :valor="number_format($total, 0, ',', '.')" />

            <x-avalia.cartao-indicador rotulo="Em prospecção" :valor="number_format($emAberto, 0, ',', '.')"
                                       ajuda="Novos, em atendimento ou agendados"
                                       :href="route('carteira.leads', ['situacao' => 'em_aberto'])" />

            {{-- Vermelho e clicável: é a única linha da tela que pede ação hoje. --}}
            <x-avalia.cartao-indicador rotulo="Agendamento vencido" :valor="number_format($atrasados, 0, ',', '.')"
                                       :tom="$atrasados > 0 ? 'text-error-600 dark:text-error-500' : null"
                                       ajuda="Passou da data marcada"
                                       :href="route('carteira.leads', ['situacao' => 'atrasado'])" />

            <x-avalia.cartao-indicador rotulo="Convertidos" :valor="number_format($convertidos, 0, ',', '.')"
                                       tom="text-success-600 dark:text-success-500" />
        </div>

        {{-- Sem o filtro de vendedor: a lista já é a dele, e oferecer a escolha
             sugeriria que existe a de outro para pedir. --}}
        <x-avalia.filtro-leads :acao="route('carteira.leads')" :ufs="$ufs" :cidades="$cidades"
                               :origens="$origens" :escolha="$escolha" :com-vendedor="false" />
    @endif

    <div class="cartao overflow-hidden">
        <div class="tabela-rolagem">
            <table class="tabela min-w-[60rem]">
                <thead class="tabela-cabecalho tabela-cabecalho-fixo">
                    <tr>
                        <th scope="col" class="px-5 py-3 text-left font-medium">Lead</th>
                        <th scope="col" class="px-5 py-3 text-left font-medium">Situação</th>
                        <th scope="col" class="px-5 py-3 text-left font-medium">Contato</th>
                        <th scope="col" class="px-5 py-3 text-left font-medium">Cidade</th>
                        <th scope="col" class="px-5 py-3 text-right font-medium">Abrir</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($leads as $lead)
                        @php $atrasado = $lead->agendado_para?->isPast() && $lead->situacao->exigeData(); @endphp
                        <tr>
                            <td class="px-5 py-4 text-left">
                                <a href="{{ route('carteira.leads.editar', $lead) }}"
                                   class="hover:text-brand-500 dark:hover:text-brand-400 font-medium text-gray-800 dark:text-white/90">
                                    {{ $lead->nome }}
                                </a>
                                @if ($lead->observacao)
                                    <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">{{ Str::limit($lead->observacao, 90) }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-left">
                                <span class="etiqueta {{ $lead->situacao->etiqueta() }}">{{ $lead->situacao->rotulo() }}</span>
                                @if ($lead->agendado_para)
                                    <span class="mt-1 block text-xs {{ $atrasado ? 'text-error-600 dark:text-error-500' : 'text-gray-500 dark:text-gray-400' }}">
                                        {{ $lead->agendado_para->format('d/m/Y H:i') }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-left text-gray-600 dark:text-gray-300">
                                @if ($lead->telefone)
                                    <a href="tel:{{ preg_replace('/[^0-9+]/', '', $lead->telefone) }}"
                                       class="hover:text-brand-500 dark:hover:text-brand-400 block whitespace-nowrap tabular-nums">
                                        {{ $lead->telefone }}
                                    </a>
                                @endif
                                @if ($lead->email)
                                    <a href="mailto:{{ $lead->email }}"
                                       class="hover:text-brand-500 dark:hover:text-brand-400 mt-0.5 block text-xs">
                                        {{ $lead->email }}
                                    </a>
                                @endif
                                @unless ($lead->temContato())
                                    <span class="text-gray-400 dark:text-gray-500">Sem contato</span>
                                @endunless
                            </td>
                            <td class="px-5 py-4 text-left text-gray-600 dark:text-gray-300">
                                @if ($lead->cidadeRotulo())
                                    {{ $lead->cidadeRotulo() }}
                                @else
                                    <span class="text-gray-400 dark:text-gray-500">Não informada</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-1">
                                    <a class="acao-linha" href="{{ route('carteira.leads.editar', $lead) }}"
                                       title="Abrir a ficha do lead">
                                        <x-avalia.icone nome="lapis" />
                                        <span class="sr-only">Abrir</span>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="tabela-vazia">
                                @if ($total === 0)
                                    Nenhum lead distribuído para você ainda.
                                @else
                                    Nenhum lead neste recorte. Ajuste o filtro.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-gray-100 px-6 py-4 text-sm text-gray-500 dark:border-gray-800 dark:text-gray-400">
            {{ number_format($leads->count(), 0, ',', '.') }}
            {{ $leads->count() === 1 ? 'lead' : 'leads' }} nesta lista
        </div>
    </div>
@endsection
