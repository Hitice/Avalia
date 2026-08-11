@php
    use App\Support\Laudo;

    $ausentes = Laudo::ausentes($resposta);
@endphp

{{-- O resultado da consulta em blocos.

     A leitura vem de App\Support\Laudo, que é a mesma fonte do PDF. Tela e
     papel que montam a ordem por conta própria divergem no primeiro campo novo,
     e o cliente liga perguntando qual dos dois está certo.

     A ordem é a de quem decide crédito: score, quem é, o que pesa contra,
     contexto. Quem lê de cima para baixo conclui antes de acabar a tela. --}}

@foreach (Laudo::blocos($resposta) as $bloco)
    <div class="cartao mb-6 overflow-hidden">
        <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
            <h2 class="font-medium text-gray-800 dark:text-white/90">{{ $bloco['titulo'] }}</h2>
        </div>

        <div class="tabela-rolagem">
            <table class="tabela min-w-[32rem]">
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($bloco['linhas'] as $linha)
                        <tr>
                            <th scope="row" class="px-6 py-3 text-left font-medium text-gray-600 dark:text-gray-300">
                                {{ $linha['rotulo'] }}
                            </th>
                            <td class="px-6 py-3 text-right tabular-nums">{{ $linha['valor'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endforeach

{{-- Ausência de pendência e ausência de informação são coisas opostas, e a tela
     que cala sobre a segunda deixa quem lê concluir a primeira. --}}
@if ($ausentes !== [])
    <div class="aviso aviso-alerta mb-6">
        Esta consulta não contempla {{ implode(', ', $ausentes) }}.
        A ausência aqui não significa ausência de ocorrências: significa que estas bases
        não foram pesquisadas neste produto.
    </div>
@endif

{{-- As mesmas ressalvas que vão no PDF, da mesma fonte.

     Ficam recolhidas porque quem abre a tela veio ver o resultado, e cinco
     parágrafos de aviso antes dele empurram o que importa para baixo da dobra.
     No PDF elas vêm abertas e antes do conteúdo, porque lá não há como
     expandir e o papel circula sem quem o explique. --}}
<details class="cartao mb-6 p-5">
    <summary class="cursor-pointer text-sm font-medium text-gray-700 dark:text-gray-200">
        Informações importantes sobre o uso desta consulta
    </summary>

    <div class="mt-4 space-y-3">
        @foreach (Laudo::ressalvas(App\Support\Documento::mascarar($documento ?? '')) as $ressalva)
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $ressalva }}</p>
        @endforeach
    </div>
</details>
