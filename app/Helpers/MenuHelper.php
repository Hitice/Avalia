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
            ['icon' => 'pessoas', 'name' => 'Clientes', 'path' => '/empresas', 'papeis' => ['admin']],
            ['icon' => 'pesquisa', 'name' => 'Consultas', 'path' => '/consultas', 'papeis' => ['admin']],
            ['icon' => 'paginas', 'name' => 'Catálogo', 'path' => '/catalogo', 'papeis' => ['admin']],
            // "Simulador" nos tres papeis: e a mesma ferramenta, e o portal do
            // cliente ja chamava assim. Nome diferente para a mesma coisa
            // conforme quem abre a tela e o que a PDD manda evitar.
            ['icon' => 'calculadora', 'name' => 'Simulador', 'path' => '/simulacao', 'papeis' => ['admin']],
            ['icon' => 'campanha', 'name' => 'Campanhas', 'path' => '/campanhas', 'papeis' => ['admin']],
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

    /** As rotas do back office: o que e da casa, e nao de um produto. */
    public const ROTAS_GESTAO = ['gestao.', 'equipe.', 'financeiro.', 'socios.', 'controladoria', 'auditoria', 'conexoes.', 'leads.', 'documentos.'];

    public static function naSales(): bool
    {
        return self::rotaEm(self::ROTAS_SALES);
    }

    public static function naGestao(): bool
    {
        return self::rotaEm(self::ROTAS_GESTAO);
    }

    private static function rotaEm(array $prefixos): bool
    {
        $rota = (string) request()->route()?->getName();

        foreach ($prefixos as $prefixo) {
            if (str_starts_with($rota, $prefixo)) {
                return true;
            }
        }

        return false;
    }

    public static function marcaDaArea(): string
    {
        return match (true) {
            self::naSales() => 'vendas',
            self::naGestao() => 'gestao',
            default => 'credito',
        };
    }

    public static function inicioDaArea(): string
    {
        if (self::naSales()) {
            // A equipe tem home; cliente e produtor caem no QR, que e o que veem.
            return auth('staff')->check() ? route('sales.inicio') : route('etiquetas.index');
        }

        if (self::naGestao()) {
            return route('gestao.inicio');
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

    /** O back office. A lateral inteira e de administracao; o que exige permissao propria some de quem nao a tem. */
    public static function getItensDaGestao()
    {
        return [
            ['icon' => 'inicio', 'name' => 'Início', 'path' => '/gestao'],
            ['icon' => 'pessoas', 'name' => 'Contatos', 'path' => '/gestao/contatos'],
            ['icon' => 'lead', 'name' => 'Leads', 'path' => '/leads'],
            ['icon' => 'grafico', 'name' => 'Financeiro', 'path' => '/financeiro', 'exigeFinanceiro' => true],
            ['icon' => 'tarefa', 'name' => 'Sexta-feira', 'path' => '/gestao/sexta', 'exigeFinanceiro' => true],
            ['icon' => 'boleto', 'name' => 'Contas a pagar', 'path' => '/gestao/contas', 'exigeFinanceiro' => true],
            ['icon' => 'grafico', 'name' => 'Sócios', 'path' => '/socios', 'exigeSocios' => true],
            ['icon' => 'grafico', 'name' => 'Controladoria', 'path' => '/controladoria', 'exigeSocios' => true],
            ['icon' => 'documentos', 'name' => 'Documentos', 'path' => '/documentos'],
            ['icon' => 'tarefa', 'name' => 'Equipe', 'path' => '/equipe'],
            ['icon' => 'conexao', 'name' => 'Conexões', 'path' => '/conexoes'],
            ['icon' => 'cadeado', 'name' => 'Auditoria', 'path' => '/auditoria'],
        ];
    }

    /**
     * As outras areas que esta conta abre, no pe de toda lateral. E a unica
     * porta entre produtos: item de um produto dentro do menu do outro fazia
     * quem clicava trocar de sistema sem perceber.
     */
    private static function outrasAreas(string $atual): array
    {
        $conta = auth('staff')->user();

        if ($conta === null) {
            return [];
        }

        $areas = [
            'credito' => ['icon' => 'pesquisa', 'name' => \App\Support\Empresa::marcaCredito(), 'path' => '/painel', 'abre' => $conta->acessa('one')],
            'vendas' => ['icon' => 'qr', 'name' => \App\Support\Empresa::marcaVendas(), 'path' => '/sales', 'abre' => $conta->acessa('sales')],
            'gestao' => ['icon' => 'inicio', 'name' => \App\Support\Empresa::marcaGestao(), 'path' => '/gestao', 'abre' => $conta->ehAdmin() || $conta->ehSuper()],
        ];

        unset($areas[$atual]);

        return array_values(array_filter($areas, fn (array $area) => $area['abre']));
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
            && (empty($item['exigeSales']) || $conta === null || $conta->acessa('sales'));

        $area = self::marcaDaArea();

        [$titulo, $itens] = match ($area) {
            'vendas' => [\App\Support\Empresa::marcaVendas(), self::getItensDaSales()],
            'gestao' => [\App\Support\Empresa::marcaGestao(), self::getItensDaGestao()],
            default => ['Menu', self::getMainNavItems()],
        };

        $grupos = [['title' => $titulo, 'items' => array_values(array_filter($itens, $permitido))]];

        if ($outras = self::outrasAreas($area)) {
            $grupos[] = ['title' => 'Áreas', 'items' => $outras];
        }

        return $grupos;
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
