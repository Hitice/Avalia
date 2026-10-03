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

    /*
     * Bytes uteis da tag NFC que a casa usa.
     *
     * NTAG213, a mais barata e a mais comum, tem cerca de 140 depois do
     * cabecalho. E esse numero que faz o encurtador existir: endereco de
     * campanha com parametros de origem nao cabe nele.
     */
    'bytes_da_tag' => 140,

    'precos' => [
        // A placa sai sempre com QR e NFC juntos, e por isso ha um preco so.
        // Mudar aqui vale da proxima venda em diante: o que foi cobrado fica
        // gravado em `etiquetas.valor_cents`, e reajuste nao reescreve fatura
        // velha. E a mesma regra da consulta e da fatura da casa.
        'placa_cents' => 9_990,
        'renovacao_cents' => 4_990,
        'avulso_mensal_cents' => 1_990,
    ],

    /*
     * Quanto a placa custa para a casa, por unidade.
     *
     * Fica aqui e e COPIADO para a etiqueta na venda, em `custo_cents`. O
     * fornecedor reajusta, e sem a copia o painel recalcularia o lucro de
     * todas as vendas passadas com o custo de hoje: o mes fechado mudaria de
     * numero depois de o repasse ja ter sido pago.
     */
    // R$ 6,00 fechado, com margem: placa, fita dupla face, tag NFC, impressao
    // e logistica. Um numero so, e nao a soma de cinco, porque e assim que a
    // casa compra e e isso que se confere contra a nota do fornecedor.
    'custo_cents' => 600,

    /*
     * O reparte de cada placa vendida.
     *
     * A comissao incide sobre o VALOR DE VENDA, decisao do dono em 02/10/2026.
     * E diferente do Avalia One, que comissiona sobre o lucro: aqui a placa tem
     * custo fixo e conhecido, e o vendedor nao influi nele.
     *
     * O que sobra depois da comissao e dividido entre os socios. Venda feita
     * por socio nao gera comissao: o liquido inteiro vai para a divisao.
     *
     * Os socios entram por e-mail, e nao por id: id de banco nao diz nada a
     * quem le o config, e a lista precisa ser conferivel sem abrir o MySQL. O
     * painel avisa em tela quando um e-mail daqui nao tem conta, porque lista
     * errada em silencio vira repasse errado.
     *
     * A ORDEM importa: e ela que decide com quem fica o centavo impar da
     * divisao, e mexer nela muda o repasse de quem ja conferiu o mes.
     *
     * Um dos e-mails e de provedor comum, e nao do dominio da casa. E o
     * endereco da conta de verdade, e a lista tem de casar com o que esta na
     * tabela `staff`, nao com o que seria mais bonito.
     */
    /*
     * A pasta onde o ZIP da tiragem e extraido, que vira o caminho da imagem no
     * CSV da mala direta.
     *
     * Fica aqui e nao em campo de tela: e sempre a mesma pasta, e o campo so
     * fazia quem gera digitar de novo o que nunca muda. O nome da campanha entra
     * sozinho, porque o ZIP ja traz uma pasta com ele dentro.
     *
     * Em branco, o CSV sai so com o nome do arquivo.
     */
    'pasta_local' => env('ETIQUETAS_PASTA_LOCAL', '/Users/pedrohenriquemorais/Downloads'),
    'comissao_pct' => 25,

    // Placas por pessoa POR DIA UTIL, socios inclusive. O grafico desenha a
    // meta do dia nos dias uteis que faltam, e soma o mes pelos dias uteis.
    'meta_por_pessoa' => 5,

    // Da parte de cada socio no lucro (metade), quanto volta ao caixa. Com 40,
    // cada socio leva 30% do lucro de tudo, vendesse quem vendesse, e 40% fica
    // na empresa (02/10/2026). Pago toda sexta. Ver RepartePlaquinha::retencao().
    'retencao_pct' => 40,

    'socios' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env(
            'ETIQUETAS_SOCIOS',
            'pedromuska@gmail.com,atendimento.coorporativo93@gmail.com',
        )),
    ))),

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
