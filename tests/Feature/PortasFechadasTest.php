<?php

use App\Models\Cliente;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Nenhuma porta aberta por esquecimento
|--------------------------------------------------------------------------
|
| Cada teste de tela cobre a rota que conhece. Este cobre as que ninguem
| lembrou: percorre a tabela de rotas inteira e cobra protecao de todas.
|
| Rota nova nasce coberta. Se alguem publicar uma sem middleware, o teste
| falha citando o nome dela, e nao ha como o esquecimento chegar em producao
| esperando que outra pessoa perceba.
|
*/

/** Rotas que sao publicas de propósito, com a razao de cada uma. */
const PORTAS_PUBLICAS = [
    'inicio' => 'apresentacao publica; quem tem sessao e redirecionado para o proprio painel',
    'interesse.salvar' => 'formulario de contato da campanha, com teto por origem e campo armadilha',
    'entrar' => 'formulario de acesso',
    'entrar.enviar' => 'envio do formulario de acesso',
    'token' => 'renova o token da tela de entrada, e nao devolve mais nada',
    'webhooks.asaas' => 'autenticada pelo token do provedor, no proprio controller',
];

/** @return list<\Illuminate\Routing\Route> */
function rotasDaAplicacao(): array
{
    return collect(Route::getRoutes())
        ->filter(fn ($rota) => ! str_starts_with($rota->uri(), '_'))
        ->filter(fn ($rota) => $rota->uri() !== 'up')
        ->filter(fn ($rota) => $rota->getName() !== null)
        ->values()
        ->all();
}

it('exige autenticacao em toda rota que nao seja publica de proposito', function () {
    $desprotegidas = [];

    foreach (rotasDaAplicacao() as $rota) {
        if (array_key_exists($rota->getName(), PORTAS_PUBLICAS)) {
            continue;
        }

        $middlewares = $rota->gatherMiddleware();
        $temAuth = collect($middlewares)->contains(fn ($m) => is_string($m) && str_starts_with($m, 'auth:'));

        if (! $temAuth) {
            $desprotegidas[] = $rota->getName().' ('.$rota->uri().')';
        }
    }

    expect($desprotegidas)->toBeEmpty(
        'sem auth: '.implode(', ', $desprotegidas),
    );
});

it('confere a sessao em toda rota autenticada', function () {
    // auth sozinho aceita cookie valido de conta ja revogada. E o middleware
    // de sessao que compara com o estado atual da conta a cada requisicao.
    //
    // Sair e a excecao, de proposito: quem teve a sessao revogada precisa
    // conseguir sair mesmo assim, senao o cookie fica no navegador dele.
    $semConferencia = [];

    foreach (rotasDaAplicacao() as $rota) {
        if ($rota->getName() === 'sair') {
            continue;
        }

        $middlewares = $rota->gatherMiddleware();
        $temAuth = collect($middlewares)->contains(fn ($m) => is_string($m) && str_starts_with($m, 'auth:'));
        $temSessao = collect($middlewares)->contains(fn ($m) => is_string($m) && str_starts_with($m, 'sessao'));

        if ($temAuth && ! $temSessao) {
            $semConferencia[] = $rota->getName();
        }
    }

    expect($semConferencia)->toBeEmpty(
        'sem conferencia de sessao: '.implode(', ', $semConferencia),
    );
});

it('mantem a area de gestao fora do alcance da empresa', function () {
    // Um guard por natureza de conta: rota de gestao nao aceita guard empresa,
    // e a da empresa nao aceita staff. Sem isso, um cliente com sessao valida
    // alcancaria custo e margem.
    $empresa = Cliente::factory()->create();

    foreach (rotasDaAplicacao() as $rota) {
        $middlewares = $rota->gatherMiddleware();
        $daGestao = collect($middlewares)->contains('auth:staff');

        if (! $daGestao || ! in_array('GET', $rota->methods(), true) || str_contains($rota->uri(), '{')) {
            continue;
        }

        $this->actingAs($empresa, 'empresa')
            ->withSession(['versao_empresa' => $empresa->sessao_versao])
            ->get('/'.ltrim($rota->uri(), '/'))
            ->assertRedirect(route('entrar'), "rota {$rota->getName()} aceitou a empresa");
    }
});

it('mantem a area da empresa fora do alcance do staff', function () {
    $staff = Staff::factory()->admin()->create();

    foreach (rotasDaAplicacao() as $rota) {
        $daEmpresa = collect($rota->gatherMiddleware())->contains('auth:empresa');

        if (! $daEmpresa || ! in_array('GET', $rota->methods(), true) || str_contains($rota->uri(), '{')) {
            continue;
        }

        $this->actingAs($staff, 'staff')
            ->withSession(['versao_staff' => $staff->sessao_versao])
            ->get('/'.ltrim($rota->uri(), '/'))
            ->assertRedirect(route('entrar'), "rota {$rota->getName()} aceitou o staff");
    }
});

it('exige permissao de administracao em tudo que nao e do vendedor', function () {
    // O vendedor abre a carteira e o cadastro de empresa da carteira dele.
    // O resto da gestao mostra custo, margem ou dinheiro de terceiros.
    $doVendedor = ['carteira', 'carteira.consultas', 'carteira.servicos', 'carteira.simulacao',
        'painel', 'sair', 'empresas.criar', 'empresas.salvar',
        'empresas.editar', 'empresas.atualizar', 'empresas.remover'];

    $abertas = [];

    foreach (rotasDaAplicacao() as $rota) {
        $middlewares = $rota->gatherMiddleware();

        if (! collect($middlewares)->contains('auth:staff')) {
            continue;
        }

        if (in_array($rota->getName(), $doVendedor, true)) {
            continue;
        }

        if (! collect($middlewares)->contains('admin')) {
            $abertas[] = $rota->getName();
        }
    }

    expect($abertas)->toBeEmpty(
        'sem exigir administracao: '.implode(', ', $abertas),
    );
});
