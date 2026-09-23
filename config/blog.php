<?php

/*
 * Os artigos do blog.
 *
 * A ficha de cada artigo mora aqui, e o texto mora na view de mesmo nome em
 * `paginas/site/artigos`. Os dois lados leem esta mesma entrada: o cartao da
 * listagem e o cabecalho do artigo nunca divergem porque nao existe uma
 * segunda copia da manchete para esquecer de atualizar.
 *
 * A ordem da lista sai da data, nao da posicao no arquivo: artigo novo entra
 * em qualquer lugar e aparece no topo sozinho.
 */
return [

    'ura-whatsapp' => [
        'chamada' => 'Quer automatizar o atendimento no WhatsApp?',
        'manchete' => 'URA no WhatsApp: quando faz sentido e como acertar',
        'titulo' => 'URA no WhatsApp: quando faz sentido',
        'resumo' => 'Quando vale a pena usar uma URA no WhatsApp, como desenhar menus que o cliente entende e onde a voz gerada por IA ajuda no atendimento.',
        'data' => '2026-09-23',
        'leitura' => '3 min',
    ],

    'rpa-rotina-fiscal' => [
        'chamada' => 'Quer saber se a sua rotina fiscal pode ser automatizada?',
        'manchete' => 'RPA na rotina fiscal: por onde começar',
        'titulo' => 'RPA na rotina fiscal: por onde começar',
        'resumo' => 'Como identificar as tarefas fiscais que valem a pena automatizar, o que avaliar antes e como fazer um primeiro projeto de RPA com segurança.',
        'data' => '2026-09-23',
        'leitura' => '4 min',
    ],

    'cobranca-automatizada' => [
        'chamada' => 'Quer uma régua de cobrança sob medida?',
        'manchete' => 'Cobrança automatizada sem desgastar o relacionamento',
        'titulo' => 'Cobrança automatizada sem desgastar o relacionamento',
        'resumo' => 'Como montar uma régua de cobrança automática que reduz a inadimplência e preserva o relacionamento com o cliente, dentro das regras do CDC e da LGPD.',
        'data' => '2026-09-23',
        'leitura' => '4 min',
    ],

];
