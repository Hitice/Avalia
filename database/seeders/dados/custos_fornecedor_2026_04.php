<?php

/*
 * Custo do fornecedor por servico, em centavos.
 *
 * Transcrito da tabela de custo enviada pela operacao em 04/08/2026.
 *
 * O custo e UM por servico, nao por faixa: o fornecedor cobra o mesmo da
 * Avalia independentemente da faixa de consumo minimo do cliente final. Quem
 * varia com a faixa e o preco de venda, e e por isso que a margem muda tanto
 * de coluna para coluna.
 *
 * Os 26 servicos de credito estao cobertos. Continuam sem custo, e a tela
 * deixa em branco:
 *
 *   - os 17 servicos veiculares, que nao vieram na tabela de custo.
 *
 * A tabela recebida traz ainda "CARTORIOS DIRETO (CENPROT) PF / PJ" a R$ 1,35,
 * que nao existe no catalogo da Avalia. Criar servico e decisao comercial e
 * exige preco de venda, entao ficou de fora deste carregamento.
 */

return [
    'cheques-sem-fundos' => 49,
    'acoes-judiciais' => 150,
    'scpc-bvs' => 280,
    'relatorio-plus' => 460,
    'credito-net-basica' => 861,
    'mix' => 1_031,
    'credito-net' => 1_087,
    'credito-net-top' => 1_258,
    'score-positivo' => 597,
    'risco-credito-top' => 1_250,
    'relatorio-top' => 1_450,
    'relatorio-top-scr' => 1_997,
    'maxi-top' => 1_275,
    'prime-basica' => 1_495,
    'prime-completa' => 1_775,
    'prime-completa-scr' => 2_297,
    'scr-score' => 857,
    'cadastro-especial-pf' => 150,
    'cadastro-especial-pj' => 150,
    'telefones-por-documento' => 85,
    'enderecos-por-documento' => 85,
    'infobusca-por-documento' => 150,
    'infobusca-por-nome' => 150,
    'localizador-por-telefone' => 150,
    'localizador-por-cep' => 150,
    'negativacao' => 895,
];
