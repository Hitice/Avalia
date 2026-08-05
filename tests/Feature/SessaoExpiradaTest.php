<?php

use App\Models\Staff;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;

uses(RefreshDatabase::class);

/*
 * O middleware de CSRF nao roda em teste, entao o caminho e exercitar o
 * renderizador de excecao direto, que e exatamente a peca registrada em
 * bootstrap/app.php.
 */

it('manda o visitante de volta ao login em vez da tela Page Expired', function () {
    $resposta = app(ExceptionHandler::class)->render(
        Request::create('/entrar', 'POST', ['email' => 'quem@avalia.local', 'senha' => 'segredo']),
        new TokenMismatchException,
    );

    expect($resposta->getStatusCode())->toBe(302)
        ->and($resposta->getTargetUrl())->toBe(route('entrar'));
});

it('explica o que aconteceu em vez de so redirecionar', function () {
    app(ExceptionHandler::class)->render(
        Request::create('/entrar', 'POST'),
        new TokenMismatchException,
    );

    expect(session('erro'))->toContain('expirou');
});

it('nunca devolve a senha digitada na volta', function () {
    // O redirect de volta repopula o formulario; senha nao pode ir junto.
    $this->actingAs(Staff::factory()->admin()->create(), 'staff');

    app(ExceptionHandler::class)->render(
        Request::create('/catalogo/planos', 'POST', ['nome' => 'Plano X', 'senha' => 'segredo']),
        new TokenMismatchException,
    );

    $antigos = session()->getOldInput();

    expect($antigos)->toHaveKey('nome')
        ->and($antigos)->not->toHaveKey('senha');
});

it('devolve quem ja esta dentro para a propria pagina, nao para o login', function () {
    $this->actingAs(Staff::factory()->admin()->create(), 'staff');

    // Guardado antes de trocar a requisicao do container: url() muda de raiz
    // junto com ela.
    $origem = url('/catalogo/planos');

    $requisicao = Request::create('/catalogo/planos', 'POST');
    $requisicao->headers->set('referer', $origem);

    // back() le a requisicao do container, nao a que chega no renderizador.
    app()->instance('request', $requisicao);

    $resposta = app(ExceptionHandler::class)->render($requisicao, new TokenMismatchException);

    expect($resposta->getTargetUrl())->toBe($origem);
});

/*
|--------------------------------------------------------------------------
| Cada conta volta para a area dela
|--------------------------------------------------------------------------
*/

it('manda a empresa autenticada para a area dela, e nao para a gestao', function () {
    // O padrao do Laravel manda todo mundo para "/", que aqui e a gestao. Com
    // duas naturezas de conta isso vira laco: a empresa abre "/", o auth:staff
    // recusa e manda para /entrar, o guest devolve para "/", sem fim.
    $empresa = App\Models\Cliente::factory()->create();

    $this->actingAs($empresa, 'empresa')
        ->withSession(['versao_empresa' => $empresa->sessao_versao])
        ->get('/')
        ->assertRedirect(route('entrar'));

    $this->actingAs($empresa, 'empresa')
        ->withSession(['versao_empresa' => $empresa->sessao_versao])
        ->get(route('entrar'))
        ->assertRedirect(route('empresa.painel'));
});

it('manda o staff autenticado para a gestao ao tentar a tela de entrada', function () {
    $staff = App\Models\Staff::factory()->admin()->create();

    $this->actingAs($staff, 'staff')
        ->withSession(['versao_staff' => $staff->sessao_versao])
        ->get(route('entrar'))
        ->assertRedirect(route('painel'));
});

it('renova o token da tela de entrada sem exigir recarregar', function () {
    // A tela de entrada e a que mais fica esquecida em aba. Sem renovacao, o
    // token morre com a sessao e quem volta depois toma "formulario expirou"
    // antes de digitar qualquer coisa.
    $this->get(route('entrar'))
        ->assertOk()
        ->assertSee(route('token'), false);

    $resposta = $this->getJson(route('token'))->assertOk();

    expect($resposta->json('token'))->toBe(csrf_token());
});

it('nao deixa o token virar porta de entrada para outra coisa', function () {
    // A rota devolve o token e mais nada: nem sessao, nem conta, nem dado.
    expect(array_keys($this->getJson(route('token'))->json()))->toBe(['token']);
});
