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
        'resumo' => 'O código impresso é permanente, e o destino dele muda quando você quiser, sem reimprimir nada.',
        'texto' => 'Gere os códigos, mande imprimir e venda. Só depois da venda você informa para onde '
            .'cada um deve levar: o WhatsApp do cliente, o Instagram dele, a avaliação no Google, o '
            .'cardápio. O endereço é nosso e é permanente, então trocar o destino é trocar um campo '
            .'no sistema, e a placa que já está no balcão continua valendo.',
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
        'resumo' => 'Endereços longos atrás de um código curto, para caberem na tag NFC e no que for impresso.',
        'texto' => 'Um endereço de campanha com parâmetros de origem passa fácil de 140 caracteres, e '
            .'não cabe numa tag NFC comum nem numa linha impressa. O encurtador troca isso por '
            .'avaliaone.com.br/l/K7M2PX, conta os cliques e deixa você trocar o destino depois.',
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
        'texto' => 'Digite um endereço, escolha o tamanho e baixe. O SVG sai em vetor, no tamanho '
            .'em milímetros que você pedir, pronto para o CorelDRAW. É um QR estático: ele leva '
            .'para sempre ao endereço que você digitou, e trocar de destino exige reimprimir.',
        'icone' => 'M3 8V5a2 2 0 0 1 2-2h3M16 3h3a2 2 0 0 1 2 2v3M21 16v3a2 2 0 0 1-2 2h-3M8 21H5a2 2 0 0 1-2-2v-3M7 12h10',
        'rota' => 'digitais.qr',
        'porta' => null,
        'ferramenta' => null,
        'selo' => 'Grátis',
        'publico' => true,
    ],

];
