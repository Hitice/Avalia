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
 *
 * O editor de tag NFC saiu daqui e vive so em docs/SERVICOS-DIGITAIS.md ate
 * voltar a ser prioridade. Entrada parada no catalogo vira codigo que ninguem
 * le e que todo mundo tem que entender ao passar por perto.
 */
return [

    'plaquinhas' => [
        'titulo' => 'Gerador de QR Code dinâmico',
        'resumo' => 'O código impresso é permanente e o destino é editável, sem necessidade de reimpressão.',
        'texto' => 'Gere os códigos, imprima o material e defina o destino depois: WhatsApp, '
            .'Instagram, avaliação no Google, cardápio ou o site do cliente. O endereço impresso é '
            .'permanente e pertence à Avalia; o destino é um registro editável, e o material já '
            .'distribuído continua válido depois de cada alteração.',
        // QR com a seta de recomeço: o codigo e o mesmo, o destino gira.
        'icone' => 'M4 4h6v6H4V4Zm10 0h6v6h-6V4ZM4 14h6v6H4v-6Zm10 1.5a3.5 3.5 0 1 0 3.5 3.5m0-3.5V12m0 3.5H17',
        'rota' => 'digitais.plaquinhas',
        // Abre o acesso da administracao no proprio cartao e leva a ferramenta
        // de criar codigo. Apelido, e nao endereco: ver LoginController.
        'porta' => 'plaquinhas',
        'ferramenta' => 'etiquetas.index',
        'selo' => 'Premium',
        'publico' => true,
    ],

    'encurtador' => [
        'titulo' => 'Encurtador de links',
        'resumo' => 'Reduz endereços longos a um código curto, para uso em tag NFC e em material impresso.',
        'texto' => 'Endereços de campanha com parâmetros de origem passam facilmente de 140 '
            .'caracteres e não cabem numa tag NFC comum nem numa linha impressa. O encurtador '
            .'reduz o endereço, registra os acessos e permite alterar o destino a qualquer momento.',
        'icone' => 'M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7',
        // Sem pagina publica: o cartao abre o acesso e cai direto na tela.
        // Encurtador aberto a qualquer um vira alvo de phishing em dias, e o
        // dia em que o dominio entrar numa lista de bloqueio, todas as
        // etiquetas vendidas param de abrir junto.
        'rota' => null,
        'porta' => 'encurtador',
        'ferramenta' => 'etiquetas.links.index',
        'selo' => 'Premium',
        'publico' => true,
    ],

    'qr-code' => [
        'titulo' => 'Gerador de QR Code',
        'resumo' => 'Gere um QR Code e baixe em SVG ou PNG, sem cadastro e sem marca d\'água.',
        'texto' => 'Informe o endereço, escolha o tamanho e baixe. O SVG sai em vetor, na medida '
            .'em milímetros solicitada, pronto para uso em CorelDRAW ou Illustrator. É um código '
            .'estático: o destino fica gravado no desenho e só muda com reimpressão.',
        'icone' => 'M3 8V5a2 2 0 0 1 2-2h3M16 3h3a2 2 0 0 1 2 2v3M21 16v3a2 2 0 0 1-2 2h-3M8 21H5a2 2 0 0 1-2-2v-3M7 12h10',
        'rota' => 'digitais.qr',
        'porta' => null,
        'ferramenta' => null,
        'selo' => 'Grátis',
        'publico' => true,
    ],

];
