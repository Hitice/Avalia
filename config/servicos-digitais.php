<?php

/*
 * Os servicos digitais que a casa vende direto, sem projeto.
 *
 * Diferentes dos softwares sob demanda de config/softwares.php: aqueles
 * comecam por uma conversa e terminam num orcamento; estes tem preco de
 * tabela, se contratam no mesmo dia e sao os mesmos para todo mundo.
 *
 * Uma entrada por servico, lida pela aba do site, pela pagina de indice e pela
 * secao dentro de /softwares. Tres lugares mostrando a mesma coisa escrita uma
 * vez so: foi a licao que config/softwares.php ja tinha custado, quando o
 * cartao prometia um nome e a secao entregava outro.
 *
 * `publico` false mantem a entrada registrada sem mostrar cartao: serve para o
 * servico que ja esta decidido e ainda nao esta pronto, sem espalhar "em
 * breve" pela vitrine.
 */
return [

    'plaquinhas' => [
        'titulo' => 'Plaquinhas de QR Code e NFC',
        'resumo' => 'A placa no balcão leva o cliente para onde você quiser, e o destino pode mudar depois de impressa.',
        'texto' => 'Uma plaquinha de acrílico com QR Code e tag NFC, apontando para o seu WhatsApp, '
            .'o seu Instagram, a sua avaliação no Google ou o seu cardápio. O endereço é nosso e é '
            .'permanente: quando você quiser mandar o cliente para outro lugar, a gente troca no '
            .'sistema e a mesma placa passa a levar para lá. Nada é reimpresso.',
        'icone' => 'M4 4h6v6H4V4Zm10 0h6v6h-6V4ZM4 14h6v6H4v-6Zm10 4h2m-2-4h6m-2 4v2',
        'rota' => 'digitais.plaquinhas',
        'selo' => null,
        'publico' => true,
    ],

    'qr-code' => [
        'titulo' => 'Gerador de QR Code',
        'resumo' => 'Gere um QR Code e baixe em SVG ou PNG, sem cadastro e sem marca d\'água.',
        'texto' => 'Digite um endereço, escolha o tamanho e baixe. O SVG sai em vetor, no tamanho '
            .'em milímetros que você pedir, pronto para o CorelDRAW. É um QR estático: ele leva '
            .'para sempre ao endereço que você digitou, e trocar de destino exige reimprimir.',
        'icone' => 'M3 8V5a2 2 0 0 1 2-2h3M16 3h3a2 2 0 0 1 2 2v3M21 16v3a2 2 0 0 1-2 2h-3M8 21H5a2 2 0 0 1-2-2v-3M7 12h10',
        'rota' => 'digitais.qr',
        'selo' => 'Grátis',
        'publico' => true,
    ],

    // Decidido, ainda nao construido. Fica registrado aqui para nascer com o
    // resto quando chegar a vez, e nao aparece na vitrine ate la.
    'nfc' => [
        'titulo' => 'Editor de tag NFC',
        'resumo' => 'Grave endereço, contato ou Wi-Fi numa tag NFC pelo navegador do celular.',
        'texto' => '',
        'icone' => 'M6 8.5a8 8 0 0 1 0 7M9.5 6a12 12 0 0 1 0 12M13 3.5a16 16 0 0 1 0 17M17 12h.01',
        'rota' => null,
        'selo' => null,
        'publico' => false,
    ],
];
