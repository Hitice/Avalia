<?php

/*
 * As regras de dinheiro do Avalia 360.
 *
 * Ficam aqui, e nao no codigo, porque mudam por decisao comercial e nao por
 * mudanca de programa: taxa, teto de parcelas e piso por parcela sao numeros
 * que o dono do negocio revisa, e cada um deles espalhado por dentro de um
 * `if` seria uma renegociacao virando bug.
 *
 * Tudo em centavos ou em pontos base (bps): 500 bps sao 5%. Percentual em
 * float vira divergencia de centavo no repasse, e repasse errado e o unico
 * erro que o produtor confere todo mes.
 */
return [

    // O que a plataforma retem de cada pagamento. 500 = 5%.
    'taxa_bps' => env('COBRANCA_TAXA_BPS', 500),

    'parcelamento' => [
        // Teto de parcelas. Acima disto a inadimplencia cresce mais rapido que
        // o ganho, e o carne passa a durar mais que a memoria da compra.
        'maximo' => 12,

        // Piso por parcela. Sem ele, um ticket de R$ 600 vira doze parcelas de
        // R$ 50, e cada uma custa mais em cobranca do que traz de receita.
        'minimo_parcela_cents' => 10000,
    ],

    'entrada' => [
        // Percentual minimo do valor total. Entrada e o primeiro sinal de que
        // quem comprou pretende pagar: sem ela, o teste de intencao so vem na
        // primeira parcela, trinta dias depois.
        'minimo_bps' => 1000,

        // Prazo de vencimento do boleto de entrada. O provedor recusa menos de
        // 3 dias; acima de 60 a venda fica pendurada tempo demais.
        'dias' => 7,
        'dias_minimo' => 3,
        'dias_maximo' => 60,
    ],

    // Prazo de arrependimento padrao, em dias corridos a partir do aceite.
    // Vale o do produto quando ele define um proprio.
    'arrependimento_dias' => 7,

    'analise' => [
        // A versao da regra fica gravada em cada decisao. Sem ela, mudar a
        // regra torna impossivel explicar a recusa do mes passado.
        'versao' => '2026.09',

        'idade_minima' => 18,

        // Restricao em birô nao reprova sozinha: reduz o teto de parcelas.
        // Reprovar quem tem uma negativacao antiga de R$ 80 e recusar venda
        // boa, e o produtor e quem paga essa conta.
        'teto_com_restricao' => 6,

        // Consulta a birô entra desligada. Custa por consulta, e o motor
        // precisa funcionar e ser auditavel antes de gastar dinheiro por
        // proposta que nem virou venda.
        'consulta_bureau' => env('COBRANCA_CONSULTA_BUREAU', false),
    ],

];
