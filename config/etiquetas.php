<?php

/*
 * As regras de negocio das plaquinhas de QR e NFC.
 *
 * Numero que decide dinheiro ou prazo mora aqui, e nao espalhado pelo codigo:
 * o dia em que a renovacao mudar de preco, muda em um lugar, e a placa vendida
 * ontem continua explicavel porque o valor cobrado fica gravado na propria
 * etiqueta.
 */
return [

    // Quanto tempo a venda compra. Renovacao empurra a mesma quantidade.
    'validade_meses' => 12,

    /*
     * Dias que a plaquinha continua redirecionando depois de vencer.
     *
     * Existe porque quem esquece de pagar quase nunca esta desistindo, e
     * derrubar a loja do cliente no dia seguinte ao vencimento transforma um
     * atraso de boleto em cliente perdido.
     */
    'carencia_dias' => 30,

    /*
     * Quantos dias antes do vencimento o aviso sai. Um so.
     *
     * Tres avisos viram ruido, e o terceiro e ignorado junto com o primeiro.
     */
    'aviso_dias' => 15,

    'precos' => [
        'placa_cents' => 7_990,
        'renovacao_cents' => 4_990,
        'avulso_mensal_cents' => 1_990,
    ],

    // Teto de uma tiragem. Mais que isto e o navegador desenhando QR por
    // minutos a fio, e nenhuma grafica imprime mil placas de uma vez.
    'lote_maximo' => 1_000,

    /*
     * Por quanto tempo o destino fica em cache.
     *
     * A rota de leitura e a que mais roda no sistema inteiro, e ir ao MySQL em
     * toda encostada de celular e desperdicio. Um minuto de atraso numa troca
     * de destino e aceitavel, e a tela avisa disso a quem troca.
     */
    'cache_segundos' => 60,
];
