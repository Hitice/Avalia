@php
    use App\Support\Laudo;

    $ausentes = Laudo::ausentes($resposta);
    $indisponiveis = Laudo::fontesIndisponiveis($resposta);
@endphp

{{-- O resultado da consulta em blocos.

     A leitura vem de App\Support\Laudo, que é a mesma fonte do PDF. Tela e
     papel que montam a ordem por conta própria divergem no primeiro campo novo,
     e o cliente liga perguntando qual dos dois está certo.

     A ordem é a de quem decide crédito: score, quem é, o que pesa contra,
     contexto. Quem lê de cima para baixo conclui antes de acabar a tela. --}}

{{-- Base que foi consultada e nao respondeu. Vem ANTES do resultado, porque
     muda como se le tudo o que vem depois: o laudo saiu incompleto, e quem
     decide precisa saber disso antes de decidir, e nao depois. --}}
@if ($indisponiveis !== [] && empty($resposta['simulado']))
    {{-- Laudo simulado nao anuncia incompletude: quem opera em homologacao ja
         sabe que o dado e de exercicio, e o aviso vira ruido repetido. Num
         laudo REAL ele e obrigatorio, porque muda a decisao de quem le. --}}
    <div class="aviso aviso-alerta mb-6">
        <span class="block font-medium">Este resultado está incompleto.</span>
        @foreach ($indisponiveis as $fonte => $motivo)
            <span class="mt-1 block">{{ Laudo::nomeDaFonte($fonte) }}: {{ $motivo }}</span>
        @endforeach
        <span class="mt-2 block">
            O que aparece abaixo veio das bases que responderam. Consultar de novo mais tarde
            pode trazer o que faltou.
        </span>
    </div>
@endif

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

{{-- As mesmas ressalvas que vão no PDF, da mesma fonte, e no mesmo lugar:
     fechando o relatório, em corpo menor. É onde se procura por elas em
     qualquer laudo do mercado, e o tamanho comunica hierarquia. Elas precisam
     estar e precisam ser encontráveis, mas não podem disputar a leitura com o
     resultado, que é o que a pessoa abriu a tela para ver.

     Fixas, e não recolhidas atrás de um clique: ressalva que depende de alguém
     clicar para aparecer não protege ninguém. --}}
<div class="cartao mt-6 p-5">
    <h2 class="rotulo-grupo mb-3 block">Informações importantes</h2>

    <div class="space-y-2">
        @foreach (Laudo::ressalvas(App\Support\Documento::mascarar($documento ?? '')) as $ressalva)
            <p class="text-xs leading-relaxed text-gray-500 dark:text-gray-400">{{ $ressalva }}</p>
        @endforeach
    </div>
</div>
