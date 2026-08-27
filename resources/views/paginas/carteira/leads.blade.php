@extends('layouts.app', ['title' => 'Leads'])

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">Leads</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            As empresas que a administração passou para você trabalhar.
        </p>
    </div>

    @include('paginas.catalogo.avisos')

    @if ($total > 0)
        <div class="mb-6 grid gap-4 sm:grid-cols-3">
            <x-avalia.cartao-indicador rotulo="Leads com você" :valor="number_format($total, 0, ',', '.')" />
            <x-avalia.cartao-indicador rotulo="Com telefone" :valor="number_format($comTelefone, 0, ',', '.')" ajuda="Dá para ligar hoje" />
            <x-avalia.cartao-indicador rotulo="Com e-mail" :valor="number_format($comEmail, 0, ',', '.')" ajuda="Dá para escrever hoje" />
        </div>

        {{-- Sem o filtro de vendedor: a lista já é a dele, e oferecer a escolha
             sugeriria que existe a de outro para pedir. --}}
        <x-avalia.filtro-leads :acao="route('carteira.leads')" :ufs="$ufs" :cidades="$cidades"
                               :origens="$origens" :escolha="$escolha" :com-vendedor="false" />
    @endif

    <div class="cartao overflow-hidden">
        <div class="overflow-x-auto">
            <table class="tabela min-w-[56rem]">
                <thead class="tabela-cabecalho">
                    <tr>
                        <th scope="col" class="px-5 py-3 text-left font-medium">Lead</th>
                        <th scope="col" class="px-5 py-3 text-left font-medium">CNPJ</th>
                        <th scope="col" class="px-5 py-3 text-left font-medium">Cidade</th>
                        <th scope="col" class="px-5 py-3 text-left font-medium">Telefone</th>
                        <th scope="col" class="px-5 py-3 text-left font-medium">E-mail</th>
                        <th scope="col" class="px-5 py-3 text-right font-medium">Com você desde</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($leads as $lead)
                        <tr>
                            <td class="px-5 py-4 text-left">
                                <span class="font-medium text-gray-800 dark:text-white/90">{{ $lead->nome }}</span>
                                @if ($lead->observacao)
                                    <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">{{ $lead->observacao }}</span>
                                @endif
                                @unless ($lead->ativo)
                                    <span class="etiqueta etiqueta-neutra mt-1">Inativo na origem</span>
                                @endunless
                            </td>
                            <td class="px-5 py-4 text-left tabular-nums whitespace-nowrap text-gray-600 dark:text-gray-300">
                                @if ($lead->cnpj)
                                    {{ $lead->cnpjRotulo() }}
                                @else
                                    <span class="text-gray-400 dark:text-gray-500">Sem CNPJ</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-left text-gray-600 dark:text-gray-300">
                                @if ($lead->cidadeRotulo())
                                    {{ $lead->cidadeRotulo() }}
                                @else
                                    <span class="text-gray-400 dark:text-gray-500">Não informada</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-left text-gray-600 dark:text-gray-300">
                                @if ($lead->telefone)
                                    <a href="tel:{{ preg_replace('/[^0-9+]/', '', $lead->telefone) }}"
                                       class="hover:text-brand-500 dark:hover:text-brand-400 whitespace-nowrap tabular-nums">
                                        {{ $lead->telefone }}
                                    </a>
                                @else
                                    <span class="text-gray-400 dark:text-gray-500">Sem telefone</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-left text-gray-600 dark:text-gray-300">
                                @if ($lead->email)
                                    <a href="mailto:{{ $lead->email }}" class="hover:text-brand-500 dark:hover:text-brand-400">
                                        {{ $lead->email }}
                                    </a>
                                @else
                                    <span class="text-gray-400 dark:text-gray-500">Sem e-mail</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-right whitespace-nowrap text-gray-600 dark:text-gray-300">
                                {{ \Illuminate\Support\Carbon::parse($lead->vendedores->firstWhere('id', $vendedor->id)?->pivot->compartilhado_em ?? $lead->created_at)->format('d/m/Y') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="tabela-vazia">
                                @if ($total === 0)
                                    Nenhum lead com você ainda. A administração distribui a base de prospecção.
                                @else
                                    Nenhum lead neste recorte. Ajuste o filtro.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-avalia.paginacao :pagina="$leads" />
    </div>
@endsection
