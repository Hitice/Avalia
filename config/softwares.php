<?php

/*
 * O que a casa desenvolve sob demanda.
 *
 * Uma entrada por frente, lida pela vitrine da pagina inicial e pela pagina de
 * softwares. As duas mostravam a mesma frente com textos escritos em lugares
 * diferentes: o cartao prometia um nome e a secao entregava outro, e corrigir
 * um lado deixava o outro para tras.
 *
 * `resumo` e a linha do cartao; `texto` e o paragrafo do detalhe. Sao
 * diferentes de proposito, porque cabem em espacos diferentes, mas moram lado
 * a lado para nao contarem historias diferentes.
 *
 * A ancora e o endereco da frente dentro da pagina de softwares. Renomear uma
 * chave aqui quebra o link de quem guardou o endereco antigo.
 */
return [

    'rpa' => [
        'titulo' => 'Automação de processos',
        'resumo' => 'Robôs que executam rotinas fiscais e financeiras e fazem sistemas diferentes trocarem informações, eliminando os erros comuns do trabalho manual.',
        'texto' => 'Robôs que executam rotinas fiscais e financeiras e fazem sistemas diferentes trocarem informações, sem digitação.',
        'icone' => 'M9 3h6a2 2 0 0 1 2 2v1h1a3 3 0 0 1 3 3v8a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V9a3 3 0 0 1 3-3h1V5a2 2 0 0 1 2-2ZM9 13h.01M15 13h.01M9.5 17h5',
        'imagem' => 'rpa.jpg',
        'alt' => 'Código de automação projetado sobre a tela de um notebook enquanto alguém digita',
        'itens' => [
            'Rotinas fiscais executadas sem intervenção manual',
            'Fluxos financeiros automatizados, com registro de cada etapa',
            'Integração entre sistemas que hoje não se comunicam',
        ],
    ],

    'mercado' => [
        'titulo' => 'Análise de mercado',
        'resumo' => 'Monitoramento contínuo do mercado e cotações de ativos atualizadas, no formato que sua equipe já usa.',
        'texto' => 'Robôs configurados para o seu negócio, que acompanham o mercado e mantêm as cotações de moedas e ativos sempre atualizadas.',
        'icone' => 'M3 17l5-5 4 4 8-9M16 7h5v5',
        'imagem' => 'mercado.jpg',
        'alt' => 'Painéis de indicadores e gráficos flutuando sobre um notebook e um tablet',
        'itens' => [
            'Monitoramento dos ativos e indicadores que importam para você',
            'Cotações de moedas e ativos atualizadas automaticamente',
            'Relatórios e alertas no formato que sua equipe já usa',
        ],
    ],

    'cobrancas' => [
        'titulo' => 'Automação de cobranças',
        'resumo' => 'Gestão de alertas e cobranças, enviados no momento certo, com acompanhamento de cada pagamento em aberto.',
        'texto' => 'Da emissão do boleto à negativação, toda a jornada de cobrança roda de forma automática, no momento certo e no tom certo, com acompanhamento de cada pagamento em aberto.',
        'icone' => 'M12 3v18M16 7.5c0-1.4-1.8-2.5-4-2.5S8 6.1 8 7.5 9.8 10 12 10s4 1.1 4 2.5S14.2 15 12 15s-4-1.1-4-2.5',
        'imagem' => 'cobrancas.jpg',
        'alt' => 'Relatórios impressos com gráficos de barras, linhas e pizza sobre uma mesa',
        'itens' => [
            'Emissão e envio automático de boletos',
            'Alertas de vencimento e cobranças pelo WhatsApp e por e-mail',
            'Acompanhamento de cada pagamento, com baixa automática',
            'Negativação automática ao fim da régua, com a notificação prévia exigida por lei',
            'Régua de cobrança ajustada ao seu negócio',
        ],
    ],

    'ura' => [
        'titulo' => 'Chat e atendimento humanizados',
        'resumo' => 'Segmentação de leads, atendimento ao cliente, chat inteligente e URA com voz natural gerada por IA, integrados aos seus sistemas.',
        'texto' => 'Da segmentação de leads ao atendimento ao cliente: chat inteligente, URA com voz natural gerada por IA e passagem para a sua equipe quando for preciso, integrados aos seus sistemas.',
        'icone' => 'M8 12h8M8 8.5h8M21 12a8 8 0 0 1-8 8H7l-4 3v-6.5A8 8 0 0 1 11 4h2a8 8 0 0 1 8 8Z',
        // Sem foto: a que havia trazia o logotipo de outra empresa impresso no
        // canto, e assinar uma secao nossa com marca de terceiro nao e opcao.
        'imagem' => null,
        'alt' => null,
        'itens' => [
            'Segmentação e qualificação de leads',
            'Conversas em linguagem natural, no tom da sua marca',
            'URA com respostas em áudio e voz natural gerada por IA',
            'Transferência para um atendente quando o cliente precisar',
            'Integração com a API do WhatsApp Business',
        ],
    ],

    'gestao' => [
        'titulo' => 'Controle de produção e CRM',
        'resumo' => 'Acompanhamento da produção e um CRM enxuto, desenhados para o jeito que sua empresa trabalha.',
        'texto' => 'Acompanhamento da produção e um CRM enxuto, desenhados para o jeito que sua empresa trabalha.',
        'icone' => 'M4 20V10m5 10V4m5 16v-7m5 7V8',
        'imagem' => 'gestao.jpg',
        'alt' => 'Equipe reunida diante de monitores acompanhando um painel de operação',
        'itens' => [
            'Acompanhamento de cada etapa da produção',
            'CRM enxuto, apenas com o que sua equipe usa',
            'Fluxos desenhados a partir do processo da sua empresa',
        ],
    ],

    'integracao' => [
        'titulo' => 'Integração de sistemas',
        'resumo' => 'Integramos ERP, bancos e APIs em uma única camada, sem substituir nenhum sistema.',
        'texto' => 'Analisamos sua operação de perto, fazemos o levantamento das necessidades e desenvolvemos integrações que resolvem gargalos e centralizam os dados, sem substituir os sistemas que você já usa.',
        'icone' => 'M9 12h6M7 7h10a4 4 0 0 1 0 8h-1M17 17H7a4 4 0 0 1 0-8h1',
        'imagem' => 'integracao.jpg',
        'alt' => 'Duas pessoas revisando código lado a lado em monitores de um escritório',
        'itens' => [
            'Levantamento das necessidades junto à sua equipe',
            'Integrações entre ERP, bancos, APIs e WhatsApp',
            'Dados centralizados em uma fonte única e confiável',
            'Painéis claros para acompanhar a operação',
        ],
    ],

    'desenvolvimento' => [
        'titulo' => 'Sites, SaaS e web apps',
        'resumo' => 'Produtos digitais sob medida, do site institucional à plataforma SaaS, com a mesma engenharia das nossas automações.',
        'texto' => 'Produtos digitais sob medida, do site institucional à plataforma SaaS, com a mesma engenharia das nossas automações.',
        'icone' => 'm9 8-5 4 5 4M15 8l5 4-5 4',
        'imagem' => 'desenvolvimento.jpg',
        'alt' => 'Desenvolvedor diante da bancada com notebook e dois monitores mostrando código',
        'itens' => [
            'Sites institucionais e páginas de venda',
            'Plataformas SaaS e web apps sob medida',
            'Integração com os sistemas e automações que você já usa',
        ],
    ],

];
