<?php

use App\Mail\ConviteDeAcesso;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

/**
 * Esqueci minha senha.
 *
 * O que importa aqui: quem tem conta recebe o link, e quem observa de fora
 * nao descobre nada. A resposta na tela e identica exista ou nao o e-mail;
 * a diferenca entre os casos so pode aparecer na caixa de entrada.
 */
const MENSAGEM_NEUTRA = 'Se este e-mail estiver cadastrado';

it('mostra o formulario de recuperacao', function () {
    $this->get(route('senha.esqueci'))
        ->assertOk()
        ->assertSee('Esqueci minha senha');
});

it('envia o link de redefinicao para staff ativo', function () {
    Mail::fake();
    $vendedor = Staff::factory()->create(['papel' => 'vendedor']);

    $this->from(route('senha.esqueci'))
        ->post(route('senha.esqueci.enviar'), ['email' => $vendedor->email])
        ->assertRedirect(route('senha.esqueci'))
        ->assertSessionHas('ok', fn ($ok) => str_contains($ok, MENSAGEM_NEUTRA));

    Mail::assertSent(ConviteDeAcesso::class, fn ($m) => $m->hasTo($vendedor->email));
});

it('envia o link de redefinicao para empresa com acesso', function () {
    Mail::fake();
    $empresa = empresaComPlano();

    $this->post(route('senha.esqueci.enviar'), ['email' => $empresa->email]);

    Mail::assertSent(ConviteDeAcesso::class, fn ($m) => $m->hasTo($empresa->email));
});

it('responde igual para e-mail que nao existe, e nao envia nada', function () {
    Mail::fake();

    $this->from(route('senha.esqueci'))
        ->post(route('senha.esqueci.enviar'), ['email' => 'ninguem@exemplo.com.br'])
        ->assertRedirect(route('senha.esqueci'))
        ->assertSessionHas('ok', fn ($ok) => str_contains($ok, MENSAGEM_NEUTRA));

    Mail::assertNothingSent();
});

it('nao envia link para staff inativo', function () {
    // Conta desligada nao volta a viver pela porta da recuperacao.
    Mail::fake();
    $desligado = Staff::factory()->inativo()->create();

    $this->post(route('senha.esqueci.enviar'), ['email' => $desligado->email])
        ->assertSessionHas('ok');

    Mail::assertNothingSent();
});

it('nao envia link para empresa sem acesso liberado', function () {
    Mail::fake();
    $bloqueada = empresaComPlano(['situacao' => 'inativo']);

    $this->post(route('senha.esqueci.enviar'), ['email' => $bloqueada->email])
        ->assertSessionHas('ok');

    Mail::assertNothingSent();
});
