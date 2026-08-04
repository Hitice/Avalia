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
