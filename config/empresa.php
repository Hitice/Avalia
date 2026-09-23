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

    // Nome fantasia como consta na Receita. Nao e o que aparece na tela: a
    // marca exibida mora em `marca` logo abaixo. Separados de proposito,
    // porque trocar o nome comercial de um produto nao muda o cadastro, e
    // escrever o nome novo aqui deixaria o campo registrando algo que o cartao
    // CNPJ nao diz.
    'nome_fantasia' => 'AVALIA 360',

    // A marca da casa, e a de cada produto, do jeito que se escreve na tela.
    // A casa e a software house; os produtos sao as duas portas que ela abre.
    'marca' => 'Avalia',
    'marca_credito' => 'Avalia One',
    'marca_cobranca' => 'Avalia Gestor',

    'cnpj' => '68.715.987/0001-64',
    'abertura' => '21/08/2026',

    'site' => 'avaliaone.com.br',
    'email' => 'comercial@avaliaone.com.br',

    // Como consta no cadastro da Receita. O canal de atendimento e outro e
    // mora em services.suporte.whatsapp: este aqui e dado cadastral.
    'telefone' => '(34) 9983-4072',

    'endereco' => [
        'rotulo' => 'Matriz',
        'logradouro' => 'Av Princesa Isabel',
        'numero' => '1331',
        'complemento' => 'Casa 1',
        'bairro' => 'Tabajaras',
        'cidade' => 'Uberlândia',
        'uf' => 'MG',
        'cep' => '38400-192',
    ],

    /*
     * O braco de Florianopolis, onde fica a software house.
     *
     * Nao tem CNPJ proprio: e a mesma pessoa juridica operando em outro
     * endereco, e nao filial com inscricao separada. Por isso entra aqui como
     * endereco com rotulo, e nunca ao lado de um numero de registro: escrever
     * "filial, CNPJ tal" seria inventar uma inscricao que nao existe, e quem
     * emite nota contra ela descobre tarde.
     */
    'braco' => [
        'rotulo' => 'Software House',
        'logradouro' => 'Rua Felipe Schmidt',
        'numero' => '303',
        'complemento' => 'Sala 1003',
        'bairro' => 'Centro',
        'cidade' => 'Florianópolis',
        'uf' => 'SC',
        'cep' => '88010-903',
    ],

];
