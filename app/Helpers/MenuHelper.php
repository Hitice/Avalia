<?php

namespace App\Helpers;

class MenuHelper
{
    /**
     * Menu da area de gestao.
     *
     * `papeis` restringe o item. Ausente = todo mundo do staff ve.
     */
    public static function getMainNavItems()
    {
        return [
            ['icon' => 'inicio', 'name' => 'Visão geral', 'path' => '/painel'],

            // Modulos do vendedor, um a um na lateral: a aba escondida dentro
            // de Carteira era o modulo que ninguem achava.
            ['icon' => 'tarefa', 'name' => 'Carteira', 'path' => '/carteira', 'papeis' => ['vendedor']],
            // O que a administracao passou para ele prospectar. Rotulo igual ao
            // do menu da administracao de proposito: e a mesma coisa, vista de
            // um lado so.
            ['icon' => 'lead', 'name' => 'Leads', 'path' => '/carteira/leads', 'papeis' => ['vendedor']],
            // Consultar serve aos dois papeis: o vendedor demonstra, a
            // administracao consulta a trabalho. A regra de dinheiro difere
            // (um desconta comissao, o outro e custo da casa), a tela nao.
            ['icon' => 'pesquisa', 'name' => 'Consultar', 'path' => '/carteira/consultar'],
            ['icon' => 'lista', 'name' => 'Consultas', 'path' => '/carteira/consultas', 'papeis' => ['vendedor']],
            ['icon' => 'paginas', 'name' => 'Serviços', 'path' => '/carteira/servicos', 'papeis' => ['vendedor']],
            ['icon' => 'calculadora', 'name' => 'Simulador', 'path' => '/carteira/simulacao', 'papeis' => ['vendedor']],
            ['icon' => 'documentos', 'name' => 'Termos', 'path' => '/termos', 'papeis' => ['vendedor']],

            // "Clientes" e nao "Empresas": e assim que a operacao fala de quem
            // contrata, e e o mesmo nome que o vendedor ja usa na carteira. A
            // rota continua /empresas para nao quebrar link salvo.
            // Antes de Clientes porque e essa a ordem do funil: o lead de hoje
            // e o cliente do mes que vem.
            ['icon' => 'lead', 'name' => 'Leads', 'path' => '/leads', 'papeis' => ['admin']],
            ['icon' => 'pessoas', 'name' => 'Clientes', 'path' => '/empresas', 'papeis' => ['admin']],
            ['icon' => 'pesquisa', 'name' => 'Consultas', 'path' => '/consultas', 'papeis' => ['admin']],
            ['icon' => 'paginas', 'name' => 'Catálogo', 'path' => '/catalogo', 'papeis' => ['admin']],
            // "Simulador" nos tres papeis: e a mesma ferramenta, e o portal do
            // cliente ja chamava assim. Nome diferente para a mesma coisa
            // conforme quem abre a tela e o que a PDD manda evitar.
            ['icon' => 'calculadora', 'name' => 'Simulador', 'path' => '/simulacao', 'papeis' => ['admin']],
            ['icon' => 'grafico', 'name' => 'Financeiro', 'path' => '/financeiro', 'papeis' => ['admin'], 'exigeFinanceiro' => true],
            // O resultado da CASA: Financeiro e Vendas QR respondem por produto,
            // e esta soma os tres. Mesma permissao de Socios.
            ['icon' => 'grafico', 'name' => 'Controladoria', 'path' => '/controladoria', 'papeis' => ['admin'], 'exigeSocios' => true],
            ['icon' => 'grafico', 'name' => 'Sócios', 'path' => '/socios', 'papeis' => ['admin'], 'exigeSocios' => true],
            ['icon' => 'documentos', 'name' => 'Documentos', 'path' => '/documentos', 'papeis' => ['admin']],
            ['icon' => 'campanha', 'name' => 'Campanhas', 'path' => '/campanhas', 'papeis' => ['admin']],
            ['icon' => 'tarefa', 'name' => 'Equipe', 'path' => '/equipe', 'papeis' => ['admin']],
            ['icon' => 'conexao', 'name' => 'Conexões', 'path' => '/conexoes', 'papeis' => ['admin']],
            /*
             * Avalia Sales, o produto de vendas externas.
             *
             * UM item, e nao os tres que havia (QR dinamico, Vendas QR,
             * Negocios): aquilo misturava as telas de outro produto no menu
             * deste, e quem entrava no QR dinamico pela lateral do Avalia One
             * saia do CRM sem perceber que tinha trocado de sistema. O produto
             * tem casca e menu proprios, e daqui sai so a porta.
             *
             * Sem `papeis`: cliente e produtor tambem entram, e o que limita
             * cada um e o dono gravado no codigo, e nao o papel.
             */
            ['icon' => 'qr', 'name' => 'Avalia Sales', 'path' => '/sales', 'exigeSales' => true],
            ['icon' => 'cadeado', 'name' => 'Auditoria', 'path' => '/auditoria', 'papeis' => ['admin']],
        ];
    }

    /**
     * Menu da area do cliente.
     *
     * Cada assunto em uma tela: quem entra para pagar a fatura nao passa pelo
     * formulario de consulta, e quem entra para consultar nao rola a pagina
     * inteira ate o campo. A tela unica so funcionava enquanto o cliente tinha
     * meia duzia de consultas.
     */
    public static function getItensDaEmpresa()
    {
        return [
            ['icon' => 'inicio', 'name' => 'Painel', 'path' => '/empresa'],
            ['icon' => 'pesquisa', 'name' => 'Consultar', 'path' => '/empresa/consultar'],
            ['icon' => 'lista', 'name' => 'Consultas', 'path' => '/empresa/consultas'],
            ['icon' => 'grafico', 'name' => 'Faturas', 'path' => '/empresa/faturas'],
            ['icon' => 'calculadora', 'name' => 'Simulador', 'path' => '/empresa/simulador'],
            ['icon' => 'documentos', 'name' => 'Documentos', 'path' => '/empresa/documentos'],
            ['icon' => 'qr', 'name' => 'QR dinâmico', 'path' => '/etiquetas'],
        ];
    }

    /*
     * As rotas do Avalia Sales: a lateral decide por elas qual menu e qual
     * marca mostrar. Por prefixo de rota, e nao por URL.
     */
    public const ROTAS_SALES = ['etiquetas.', 'negocios', 'plaquinhas.', 'sales.'];

    public static function naSales(): bool
    {
        $rota = (string) request()->route()?->getName();

        foreach (self::ROTAS_SALES as $prefixo) {
            if (str_starts_with($rota, $prefixo)) {
                return true;
            }
        }

        return false;
    }

    public static function marcaDaArea(): string
    {
        return self::naSales() ? 'vendas' : 'credito';
    }

    public static function inicioDaArea(): string
    {
        if (self::naSales()) {
            // A equipe tem home; cliente e produtor caem no QR, que e o que veem.
            return auth('staff')->check() ? route('sales.inicio') : route('etiquetas.index');
        }

        return auth('empresa')->check() ? route('empresa.painel') : \App\Support\Porta::painelDe('staff');
    }

    /**
     * Menu do Avalia Sales. Tela de administracao aparece so para quem
     * administra: margem da casa nao e assunto de quem vende.
     */
    public static function getItensDaSales()
    {
        return [
            ['icon' => 'inicio', 'name' => 'Início', 'path' => '/sales'],
            ['icon' => 'qr', 'name' => 'QR dinâmico', 'path' => '/etiquetas'],
            ['icon' => 'paginas', 'name' => 'Gerar códigos', 'path' => '/etiquetas/gerar', 'papeis' => ['admin']],
            ['icon' => 'lista', 'name' => 'Meu estoque', 'path' => '/estoque'],
            ['icon' => 'pessoas', 'name' => 'Negócios', 'path' => '/negocios', 'papeis' => ['admin']],
            ['icon' => 'conexao', 'name' => 'Encurtador', 'path' => '/etiquetas/links'],
            ['icon' => 'grafico', 'name' => 'Vendas', 'path' => '/plaquinhas/vendas', 'papeis' => ['admin']],
        ];
    }

    public static function getMenuGroups()
    {
        // A area do cliente tem menu proprio: os dois guards nunca coexistem na
        // mesma sessao, entao quem responde e o guard que esta autenticado.
        if (auth('empresa')->check()) {
            return [['title' => 'Menu', 'items' => self::getItensDaEmpresa()]];
        }

        $papel = auth('staff')->user()?->papel;

        $conta = auth('staff')->user();

        $permitido = fn (array $item) => (! isset($item['papeis']) || in_array($papel, $item['papeis'], true))
            // Item que exige permissao financeira some de quem nao a tem: menu
            // que leva a 403 ensina o operador a ignorar o menu.
            && (empty($item['exigeFinanceiro']) || (bool) $conta?->podeFinanceiro())
            && (empty($item['exigeSocios']) || (bool) $conta?->podeSocios())
            // Sem acesso ao Sales, a porta some; cliente e produtor (sem conta) entram.
            && (empty($item['exigeSales']) || $conta === null || $conta->acessa('sales'));

        if (self::naSales()) {
            return [[
                'title' => \App\Support\Empresa::marcaVendas(),
                'items' => array_values(array_filter(self::getItensDaSales(), $permitido)),
            ]];
        }

        return [
            ['title' => 'Menu', 'items' => array_values(array_filter(self::getMainNavItems(), $permitido))],
        ];
    }

    public static function isActive($path)
    {
        if (! $path) {
            return false;
        }

        $rota = ltrim($path, '/');

        // Casa tambem as telas internas do modulo: /catalogo continua marcado
        // enquanto o operador olha /catalogo/3.
        return request()->is($rota) || ($rota !== '' && request()->is($rota.'/*'));
    }
}
