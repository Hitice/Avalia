<?php

/*
 * Dados cadastrais da casa, como saem do cartao CNPJ.
 *
 * Ficam num arquivo so porque o mesmo par razao social e CNPJ aparece no
 * rodape do site, no fecho de cada PDF e na fatura. Quando estavam copiados em
 * tres lugares, trocar o CNPJ deixava uma copia velha para tras, e era sempre
 * a que o cliente recebia impressa.
 *
 * Nada aqui vem de env: nao e configuracao de ambiente, e a identidade da
 * empresa, que so muda por alteracao contratual.
 */
return [

    'razao_social' => 'AVALIA ONE NEGOCIOS CORPORATIVOS LTDA',
    'nome_fantasia' => 'AVALIA 360',
    'cnpj' => '68.715.987/0001-64',
    'abertura' => '21/08/2026',

    'site' => 'avaliaone.com.br',
    'email' => 'comercial@avaliaone.com.br',

    // Como consta no cadastro da Receita. O canal de atendimento e outro e
    // mora em services.suporte.whatsapp: este aqui e dado cadastral.
    'telefone' => '(34) 9983-4072',

    'endereco' => [
        'logradouro' => 'Av Princesa Isabel',
        'numero' => '1331',
        'complemento' => 'Casa 1',
        'bairro' => 'Tabajaras',
        'cidade' => 'Uberlândia',
        'uf' => 'MG',
        'cep' => '38400-192',
    ],

];
