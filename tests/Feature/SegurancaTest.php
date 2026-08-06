<?php

use App\Models\Cliente;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Defesas que o navegador aplica por nos
|--------------------------------------------------------------------------
*/

it('manda os cabecalhos de seguranca em toda resposta', function () {
    $resposta = $this->get(route('entrar'))->assertOk();

    expect($resposta->headers->get('Content-Security-Policy'))->toContain("frame-ancestors 'none'")
        ->and($resposta->headers->get('X-Frame-Options'))->toBe('DENY')
        ->and($resposta->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($resposta->headers->get('Referrer-Policy'))->toBe('same-origin');
});

it('manda os cabecalhos tambem em redirecionamento e erro', function () {
    // Resposta de erro tambem renderiza HTML, e e onde um script injetado
    // teria mais chance de passar despercebido.
    $redirecionamento = $this->get('/painel')->assertRedirect();
    $naoEncontrado = $this->get('/rota-que-nao-existe')->assertNotFound();

    expect($redirecionamento->headers->get('X-Frame-Options'))->toBe('DENY')
        ->and($naoEncontrado->headers->get('X-Frame-Options'))->toBe('DENY');
});

it('impede o navegador de adivinhar o tipo de arquivo', function () {
    expect($this->get(route('token'))->headers->get('X-Content-Type-Options'))->toBe('nosniff');
});

/*
|--------------------------------------------------------------------------
| Limite por origem
|--------------------------------------------------------------------------
*/

it('trava a tentativa em massa de login por origem', function () {
    // O bloqueio progressivo por conta ja existe. Este teto fecha a tentativa
    // contra muitas contas diferentes a partir do mesmo lugar.
    foreach (range(1, 20) as $tentativa) {
        $this->post(route('entrar.enviar'), [
            'email' => "conta{$tentativa}@exemplo.com",
            'senha' => 'senha-errada-123',
        ]);
    }

    $this->post(route('entrar.enviar'), [
        'email' => 'mais.uma@exemplo.com',
        'senha' => 'senha-errada-123',
    ])->assertStatus(429);
});

it('limita a renovacao de token', function () {
    foreach (range(1, 60) as $vez) {
        $this->getJson(route('token'));
    }

    $this->getJson(route('token'))->assertStatus(429);
});

/*
|--------------------------------------------------------------------------
| O que a resposta de erro conta
|--------------------------------------------------------------------------
*/

it('nao revela se o e-mail existe ao falhar o login', function () {
    // Mensagem diferente para conta inexistente entregaria a lista de clientes
    // a quem tentar um e-mail por vez.
    $empresa = Cliente::factory()->create(['email' => 'existe@empresa.com.br']);

    $comConta = $this->post(route('entrar.enviar'), [
        'email' => $empresa->email, 'senha' => 'senha-errada-123',
    ]);

    $semConta = $this->post(route('entrar.enviar'), [
        'email' => 'nao.existe@empresa.com.br', 'senha' => 'senha-errada-123',
    ]);

    expect(session('errors')?->first('email'))->not->toBeNull();
    expect($comConta->getStatusCode())->toBe($semConta->getStatusCode());
});
