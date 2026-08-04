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
 * Servicos ausentes desta lista continuam sem custo cadastrado, o que a tela
 * deixa em branco. Sao eles, na tabela recebida:
 *
 *   - os tres de SCR (relatorio-top-scr, prime-completa-scr, scr-score);
 *   - cadastro-especial-pf e cadastro-especial-pj;
 *   - infobusca-por-documento;
 *   - todos os 17 servicos veiculares.
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
    'maxi-top' => 1_275,
    'prime-basica' => 1_495,
    'prime-completa' => 1_775,
    'telefones-por-documento' => 85,
    'enderecos-por-documento' => 85,
    'infobusca-por-nome' => 150,
    'localizador-por-telefone' => 150,
    'localizador-por-cep' => 150,
    'negativacao' => 895,
];
