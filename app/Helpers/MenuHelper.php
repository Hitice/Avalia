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
            ['icon' => 'dashboard', 'name' => 'Visão geral', 'path' => '/painel'],

            // Modulos do vendedor, um a um na lateral: a aba escondida dentro
            // de Carteira era o modulo que ninguem achava.
            ['icon' => 'task', 'name' => 'Carteira', 'path' => '/carteira', 'papeis' => ['vendedor']],
            // Consultar serve aos dois papeis: o vendedor demonstra, a
            // administracao consulta a trabalho. A regra de dinheiro difere
            // (um desconta comissao, o outro e custo da casa), a tela nao.
            ['icon' => 'consulta', 'name' => 'Consultar', 'path' => '/carteira/consultar'],
            ['icon' => 'tables', 'name' => 'Consultas', 'path' => '/carteira/consultas', 'papeis' => ['vendedor']],
            ['icon' => 'pages', 'name' => 'Serviços', 'path' => '/carteira/servicos', 'papeis' => ['vendedor']],
            ['icon' => 'calculadora', 'name' => 'Simulador', 'path' => '/carteira/simulacao', 'papeis' => ['vendedor']],
            ['icon' => 'documento', 'name' => 'Termos', 'path' => '/termos', 'papeis' => ['vendedor']],

            // "Clientes" e nao "Empresas": e assim que a operacao fala de quem
            // contrata, e e o mesmo nome que o vendedor ja usa na carteira. A
            // rota continua /empresas para nao quebrar link salvo.
            ['icon' => 'user-profile', 'name' => 'Clientes', 'path' => '/empresas', 'papeis' => ['admin']],
            ['icon' => 'consulta', 'name' => 'Consultas', 'path' => '/consultas', 'papeis' => ['admin']],
            ['icon' => 'pages', 'name' => 'Catálogo', 'path' => '/catalogo', 'papeis' => ['admin']],
            // "Simulador" nos tres papeis: e a mesma ferramenta, e o portal do
            // cliente ja chamava assim. Nome diferente para a mesma coisa
            // conforme quem abre a tela e o que a PDD manda evitar.
            ['icon' => 'calculadora', 'name' => 'Simulador', 'path' => '/simulacao', 'papeis' => ['admin']],
            ['icon' => 'charts', 'name' => 'Financeiro', 'path' => '/financeiro', 'papeis' => ['admin'], 'exigeFinanceiro' => true],
            ['icon' => 'documento', 'name' => 'Documentos', 'path' => '/documentos', 'papeis' => ['admin']],
            ['icon' => 'campanha', 'name' => 'Campanhas', 'path' => '/campanhas', 'papeis' => ['admin']],
            ['icon' => 'task', 'name' => 'Equipe', 'path' => '/equipe', 'papeis' => ['admin']],
            ['icon' => 'plug', 'name' => 'Conexões', 'path' => '/conexoes', 'papeis' => ['admin']],
            ['icon' => 'authentication', 'name' => 'Auditoria', 'path' => '/auditoria', 'papeis' => ['admin']],
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
            ['icon' => 'dashboard', 'name' => 'Painel', 'path' => '/empresa'],
            ['icon' => 'consulta', 'name' => 'Consultar', 'path' => '/empresa/consultar'],
            ['icon' => 'tables', 'name' => 'Consultas', 'path' => '/empresa/consultas'],
            ['icon' => 'charts', 'name' => 'Faturas', 'path' => '/empresa/faturas'],
            ['icon' => 'calculadora', 'name' => 'Simulador', 'path' => '/empresa/simulador'],
            ['icon' => 'documento', 'name' => 'Documentos', 'path' => '/empresa/documentos'],
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
            && (empty($item['exigeFinanceiro']) || (bool) $conta?->podeFinanceiro());

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

    public static function getIconSvg($iconName)
    {
        $icons = [
            'documento' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M14.25 3.5H7.5A2.25 2.25 0 0 0 5.25 5.75v12.5A2.25 2.25 0 0 0 7.5 20.5h9a2.25 2.25 0 0 0 2.25-2.25V8m-4.5-4.5L18.75 8m-4.5-4.5V8h4.5M8.75 12.5h6.5M8.75 16h4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            'campanha' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M3.75 9.75v4.5a1.5 1.5 0 0 0 1.5 1.5h2.25l6.75 4.5V5.25L7.5 9.75H5.25a1.5 1.5 0 0 0-1.5 0Zm13.5-1.5a5.25 5.25 0 0 1 0 7.5M7.5 15.75v3a1.5 1.5 0 0 0 1.5 1.5h.75a1.5 1.5 0 0 0 1.5-1.5v-1.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            'consulta' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="10.5" cy="10.5" r="6.25" stroke="currentColor" stroke-width="1.5"/><path d="m15.2 15.2 4.05 4.05M7.5 12.5l2-2 1.65 1.35 2.35-3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            'plug' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M9 3.75v4.5m6-4.5v4.5M6.75 8.25h10.5v2.5a5.25 5.25 0 0 1-4.25 5.155V20.5h-2v-4.595A5.25 5.25 0 0 1 6.75 10.75v-2.5Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            'calculadora' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="5.25" y="3.25" width="13.5" height="17.5" rx="2" stroke="currentColor" stroke-width="1.5"/><path d="M8.5 7h7M8.75 11.25h.01M12 11.25h.01M15.25 11.25h.01M8.75 14.5h.01M12 14.5h.01M15.25 14.5h.01M8.75 17.75h.01M12 17.75h.01M15.25 17.75h.01" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>',
            'dashboard' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M5.5 3.25C4.25736 3.25 3.25 4.25736 3.25 5.5V8.99998C3.25 10.2426 4.25736 11.25 5.5 11.25H9C10.2426 11.25 11.25 10.2426 11.25 8.99998V5.5C11.25 4.25736 10.2426 3.25 9 3.25H5.5ZM4.75 5.5C4.75 5.08579 5.08579 4.75 5.5 4.75H9C9.41421 4.75 9.75 5.08579 9.75 5.5V8.99998C9.75 9.41419 9.41421 9.74998 9 9.74998H5.5C5.08579 9.74998 4.75 9.41419 4.75 8.99998V5.5ZM5.5 12.75C4.25736 12.75 3.25 13.7574 3.25 15V18.5C3.25 19.7426 4.25736 20.75 5.5 20.75H9C10.2426 20.75 11.25 19.7427 11.25 18.5V15C11.25 13.7574 10.2426 12.75 9 12.75H5.5ZM4.75 15C4.75 14.5858 5.08579 14.25 5.5 14.25H9C9.41421 14.25 9.75 14.5858 9.75 15V18.5C9.75 18.9142 9.41421 19.25 9 19.25H5.5C5.08579 19.25 4.75 18.9142 4.75 18.5V15ZM12.75 5.5C12.75 4.25736 13.7574 3.25 15 3.25H18.5C19.7426 3.25 20.75 4.25736 20.75 5.5V8.99998C20.75 10.2426 19.7426 11.25 18.5 11.25H15C13.7574 11.25 12.75 10.2426 12.75 8.99998V5.5ZM15 4.75C14.5858 4.75 14.25 5.08579 14.25 5.5V8.99998C14.25 9.41419 14.5858 9.74998 15 9.74998H18.5C18.9142 9.74998 19.25 9.41419 19.25 8.99998V5.5C19.25 5.08579 18.9142 4.75 18.5 4.75H15ZM15 12.75C13.7574 12.75 12.75 13.7574 12.75 15V18.5C12.75 19.7426 13.7574 20.75 15 20.75H18.5C19.7426 20.75 20.75 19.7427 20.75 18.5V15C20.75 13.7574 19.7426 12.75 18.5 12.75H15ZM14.25 15C14.25 14.5858 14.5858 14.25 15 14.25H18.5C18.9142 14.25 19.25 14.5858 19.25 15V18.5C19.25 18.9142 18.9142 19.25 18.5 19.25H15C14.5858 19.25 14.25 18.9142 14.25 18.5V15Z" fill="currentColor"></path></svg>',

            'user-profile' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M12 3.5C7.30558 3.5 3.5 7.30558 3.5 12C3.5 14.1526 4.3002 16.1184 5.61936 17.616C6.17279 15.3096 8.24852 13.5955 10.7246 13.5955H13.2746C15.7509 13.5955 17.8268 15.31 18.38 17.6167C19.6996 16.119 20.5 14.153 20.5 12C20.5 7.30558 16.6944 3.5 12 3.5ZM17.0246 18.8566V18.8455C17.0246 16.7744 15.3457 15.0955 13.2746 15.0955H10.7246C8.65354 15.0955 6.97461 16.7744 6.97461 18.8455V18.856C8.38223 19.8895 10.1198 20.5 12 20.5C13.8798 20.5 15.6171 19.8898 17.0246 18.8566ZM2 12C2 6.47715 6.47715 2 12 2C17.5228 2 22 6.47715 22 12C22 17.5228 17.5228 22 12 22C6.47715 22 2 17.5228 2 12ZM11.9991 7.25C10.8847 7.25 9.98126 8.15342 9.98126 9.26784C9.98126 10.3823 10.8847 11.2857 11.9991 11.2857C13.1135 11.2857 14.0169 10.3823 14.0169 9.26784C14.0169 8.15342 13.1135 7.25 11.9991 7.25ZM8.48126 9.26784C8.48126 7.32499 10.0563 5.75 11.9991 5.75C13.9419 5.75 15.5169 7.32499 15.5169 9.26784C15.5169 11.2107 13.9419 12.7857 11.9991 12.7857C10.0563 12.7857 8.48126 11.2107 8.48126 9.26784Z" fill="currentColor"></path></svg>',

            'task' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M7.75586 5.50098C7.75586 5.08676 8.09165 4.75098 8.50586 4.75098H18.4985C18.9127 4.75098 19.2485 5.08676 19.2485 5.50098L19.2485 15.4956C19.2485 15.9098 18.9127 16.2456 18.4985 16.2456H8.50586C8.09165 16.2456 7.75586 15.9098 7.75586 15.4956V5.50098ZM8.50586 3.25098C7.26322 3.25098 6.25586 4.25834 6.25586 5.50098V6.26318H5.50195C4.25931 6.26318 3.25195 7.27054 3.25195 8.51318V18.4995C3.25195 19.7422 4.25931 20.7495 5.50195 20.7495H15.4883C16.7309 20.7495 17.7383 19.7421 17.7383 18.4995L17.7383 17.7456H18.4985C19.7411 17.7456 20.7485 16.7382 20.7485 15.4956L20.7485 5.50097C20.7485 4.25833 19.7411 3.25098 18.4985 3.25098H8.50586ZM16.2383 17.7456H8.50586C7.26322 17.7456 6.25586 16.7382 6.25586 15.4956V7.76318H5.50195C5.08774 7.76318 4.75195 8.09897 4.75195 8.51318V18.4995C4.75195 18.9137 5.08774 19.2495 5.50195 19.2495H15.4883C15.9025 19.2495 16.2383 18.9137 16.2383 18.4995L16.2383 17.7456Z" fill="currentColor"></path></svg>',

            'tables' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M3.25 5.5C3.25 4.25736 4.25736 3.25 5.5 3.25H18.5C19.7426 3.25 20.75 4.25736 20.75 5.5V18.5C20.75 19.7426 19.7426 20.75 18.5 20.75H5.5C4.25736 20.75 3.25 19.7426 3.25 18.5V5.5ZM5.5 4.75C5.08579 4.75 4.75 5.08579 4.75 5.5V8.58325L19.25 8.58325V5.5C19.25 5.08579 18.9142 4.75 18.5 4.75H5.5ZM19.25 10.0833H15.416V13.9165H19.25V10.0833ZM13.916 10.0833L10.083 10.0833V13.9165L13.916 13.9165V10.0833ZM8.58301 10.0833H4.75V13.9165H8.58301V10.0833ZM4.75 18.5V15.4165H8.58301V19.25H5.5C5.08579 19.25 4.75 18.9142 4.75 18.5ZM10.083 19.25V15.4165L13.916 15.4165V19.25H10.083ZM15.416 19.25V15.4165H19.25V18.5C19.25 18.9142 18.9142 19.25 18.5 19.25H15.416Z" fill="currentColor"></path></svg>',

            'pages' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M8.50391 4.25C8.50391 3.83579 8.83969 3.5 9.25391 3.5H15.2777C15.4766 3.5 15.6674 3.57902 15.8081 3.71967L18.2807 6.19234C18.4214 6.333 18.5004 6.52376 18.5004 6.72268V16.75C18.5004 17.1642 18.1646 17.5 17.7504 17.5H16.248V17.4993H14.748V17.5H9.25391C8.83969 17.5 8.50391 17.1642 8.50391 16.75V4.25ZM14.748 19H9.25391C8.01126 19 7.00391 17.9926 7.00391 16.75V6.49854H6.24805C5.83383 6.49854 5.49805 6.83432 5.49805 7.24854V19.75C5.49805 20.1642 5.83383 20.5 6.24805 20.5H13.998C14.4123 20.5 14.748 20.1642 14.748 19.75L14.748 19ZM7.00391 4.99854V4.25C7.00391 3.00736 8.01127 2 9.25391 2H15.2777C15.8745 2 16.4468 2.23705 16.8687 2.659L19.3414 5.13168C19.7634 5.55364 20.0004 6.12594 20.0004 6.72268V16.75C20.0004 17.9926 18.9931 19 17.7504 19H16.248L16.248 19.75C16.248 20.9926 15.2407 22 13.998 22H6.24805C5.00541 22 3.99805 20.9926 3.99805 19.75V7.24854C3.99805 6.00589 5.00541 4.99854 6.24805 4.99854H7.00391Z" fill="currentColor"></path></svg>',

            'charts' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M4.00002 12.0957C4.00002 7.67742 7.58174 4.0957 12 4.0957C16.4183 4.0957 20 7.67742 20 12.0957C20 16.514 16.4183 20.0957 12 20.0957H5.06068L6.34317 18.8132C6.48382 18.6726 6.56284 18.4818 6.56284 18.2829C6.56284 18.084 6.48382 17.8932 6.34317 17.7526C4.89463 16.304 4.00002 14.305 4.00002 12.0957ZM12 2.5957C6.75332 2.5957 2.50002 6.849 2.50002 12.0957C2.50002 14.4488 3.35633 16.603 4.77303 18.262L2.71969 20.3154C2.50519 20.5299 2.44103 20.8525 2.55711 21.1327C2.6732 21.413 2.94668 21.5957 3.25002 21.5957H12C17.2467 21.5957 21.5 17.3424 21.5 12.0957C21.5 6.849 17.2467 2.5957 12 2.5957ZM7.62502 10.8467C6.93467 10.8467 6.37502 11.4063 6.37502 12.0967C6.37502 12.787 6.93467 13.3467 7.62502 13.3467H7.62512C8.31548 13.3467 8.87512 12.787 8.87512 12.0967C8.87512 11.4063 8.31548 10.8467 7.62512 10.8467H7.62502ZM10.75 12.0967C10.75 11.4063 11.3097 10.8467 12 10.8467H12.0001C12.6905 10.8467 13.2501 11.4063 13.2501 12.0967C13.2501 12.787 12.6905 13.3467 12.0001 13.3467H12C11.3097 13.3467 10.75 12.787 10.75 12.0967ZM16.375 10.8467C15.6847 10.8467 15.125 11.4063 15.125 12.0967C15.125 12.787 15.6847 13.3467 16.375 13.3467H16.3751C17.0655 13.3467 17.6251 12.787 17.6251 12.0967C17.6251 11.4063 17.0655 10.8467 16.3751 10.8467H16.375Z" fill="currentColor"></path></svg>',

            'authentication' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M14 2.75C14 2.33579 14.3358 2 14.75 2C15.1642 2 15.5 2.33579 15.5 2.75V5.73291L17.75 5.73291H19C19.4142 5.73291 19.75 6.0687 19.75 6.48291C19.75 6.89712 19.4142 7.23291 19 7.23291H18.5L18.5 12.2329C18.5 15.5691 15.9866 18.3183 12.75 18.6901V21.25C12.75 21.6642 12.4142 22 12 22C11.5858 22 11.25 21.6642 11.25 21.25V18.6901C8.01342 18.3183 5.5 15.5691 5.5 12.2329L5.5 7.23291H5C4.58579 7.23291 4.25 6.89712 4.25 6.48291C4.25 6.0687 4.58579 5.73291 5 5.73291L6.25 5.73291L8.5 5.73291L8.5 2.75C8.5 2.33579 8.83579 2 9.25 2C9.66421 2 10 2.33579 10 2.75L10 5.73291L14 5.73291V2.75ZM7 7.23291L7 12.2329C7 14.9943 9.23858 17.2329 12 17.2329C14.7614 17.2329 17 14.9943 17 12.2329L17 7.23291L7 7.23291Z" fill="currentColor"></path></svg>',

        ];

        return $icons[$iconName] ?? '<svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" fill="currentColor"/></svg>';
    }
}
