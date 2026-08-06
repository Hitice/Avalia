<?php

use App\Mail\ConviteDeAcesso;
use App\Models\Cliente;
use App\Models\Staff;
use App\Support\Convite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

/**
 * O convite de acesso: cadastro sem senha manda o link, e o link define uma vez.
 *
 * O que se protege aqui e o desenho de seguranca inteiro: senha nunca viaja
 * por e-mail, a conta nasce inutilizavel (senha aleatoria que ninguem conhece),
 * o link morre pelo prazo e morre pelo uso.
 */
it('cadastrar vendedor sem senha dispara o convite', function () {
    Mail::fake();

    admin()->post(route('equipe.salvar'), [
        'nome' => 'Vendedora Nova',
        'email' => 'nova@avalia.com.br',
        'papel' => 'vendedor',
        'comissao_pct' => 10,
        'ativo' => 1,
        'senha' => '',
    ])->assertRedirect(route('equipe.index'));

    Mail::assertSent(ConviteDeAcesso::class, fn ($m) => $m->hasTo('nova@avalia.com.br'));

    // A conta nasce com senha aleatoria: existe, mas ninguem entra com ela.
    expect(Staff::firstWhere('email', 'nova@avalia.com.br')->senha)->not->toBeNull();
});

it('senha enviada no POST e ignorada: o convite decide', function () {
    // O formulario nem tem o campo; se alguem forjar o POST com senha, ela nao
    // vira credencial. A conta nasce com aleatoria e o convite segue.
    Mail::fake();

    admin()->post(route('equipe.salvar'), [
        'nome' => 'Vendedor Forjado',
        'email' => 'forjado@avalia.com.br',
        'papel' => 'vendedor',
        'comissao_pct' => 10,
        'ativo' => 1,
        'senha' => 'senha-forjada-123',
    ])->assertRedirect(route('equipe.index'));

    Mail::assertSent(ConviteDeAcesso::class);

    // A senha forjada nao entra. Sessao do admin fora antes: logado, o
    // formulario de entrada nem valida, so redireciona.
    $this->flushSession();
    app('auth')->forgetGuards();

    $this->post('/entrar', [
        'email' => 'forjado@avalia.com.br',
        'senha' => 'senha-forjada-123',
    ])->assertSessionHasErrors();
});

it('cadastrar empresa sem senha dispara o convite para o e-mail dela', function () {
    Mail::fake();

    [$vendedor] = carteira();

    comoVendedor($vendedor)->post(route('empresas.salvar'), [
        'razao_social' => 'Convidada LTDA',
        'cnpj' => '12.345.678/0001-95',
        'email' => 'acesso@convidada.com.br',
        'senha' => '',
        'situacao' => 'ativo',
    ])->assertRedirect(route('carteira'));

    Mail::assertSent(ConviteDeAcesso::class, fn ($m) => $m->hasTo('acesso@convidada.com.br') && $m->ehEmpresa);
});

it('o link do convite define a senha e passa a valer no login', function () {
    $staff = Staff::factory()->create(['papel' => 'vendedor', 'email' => 'define@avalia.com.br']);

    $link = Convite::link($staff, 'staff');

    $this->get($link)->assertOk()->assertSee('Defina sua senha');

    $this->post($link, [
        'senha' => 'minha-senha-nova-10',
        'senha_confirmation' => 'minha-senha-nova-10',
    ])->assertRedirect(route('entrar'));

    $this->post('/entrar', [
        'email' => 'define@avalia.com.br',
        'senha' => 'minha-senha-nova-10',
    ])->assertRedirect(route('painel'));
});

it('o link morre depois de usado', function () {
    $staff = Staff::factory()->create(['papel' => 'vendedor']);

    $link = Convite::link($staff, 'staff');

    $this->post($link, [
        'senha' => 'primeira-senha-10x',
        'senha_confirmation' => 'primeira-senha-10x',
    ])->assertRedirect(route('entrar'));

    // O carimbo mudou junto com a senha: o mesmo link agora e recusado.
    $this->get($link)->assertForbidden();
});

it('o link morre pelo prazo', function () {
    $staff = Staff::factory()->create(['papel' => 'vendedor']);

    $link = Convite::link($staff, 'staff');

    $this->travel(Convite::HORAS_DE_VALIDADE + 1)->hours();

    $this->get($link)->assertForbidden();
});

it('link com assinatura adulterada nem chega ao formulario', function () {
    $staff = Staff::factory()->create(['papel' => 'vendedor']);

    $link = Convite::link($staff, 'staff');

    // Trocar o id de destino invalida a assinatura inteira.
    $adulterado = str_replace('/staff/'.$staff->id, '/staff/'.($staff->id + 7), $link);

    $this->get($adulterado)->assertForbidden();
});

it('reenvia a redefinicao pela edicao da equipe', function () {
    Mail::fake();

    $staff = Staff::factory()->create(['papel' => 'vendedor', 'email' => 'reenvio@avalia.com.br']);

    admin()->post(route('equipe.convite', $staff))->assertRedirect();

    Mail::assertSent(ConviteDeAcesso::class, fn ($m) => $m->hasTo('reenvio@avalia.com.br'));
});

it('vendedor so reenvia redefinicao para empresa da propria carteira', function () {
    Mail::fake();

    [$vendedor, $minha] = carteira();
    $alheia = Cliente::factory()->create();

    comoVendedor($vendedor)->post(route('empresas.convite', $minha))->assertRedirect();
    Mail::assertSent(ConviteDeAcesso::class, fn ($m) => $m->hasTo($minha->email));

    comoVendedor($vendedor)->post(route('empresas.convite', $alheia))->assertForbidden();
});

it('empresa tambem define a senha pelo proprio link', function () {
    $empresa = Cliente::factory()->create(['email' => 'portal@cliente.com.br']);

    $link = Convite::link($empresa, 'empresa');

    $this->post($link, [
        'senha' => 'senha-do-portal-10',
        'senha_confirmation' => 'senha-do-portal-10',
    ])->assertRedirect(route('entrar'));

    $this->post('/entrar', [
        'email' => 'portal@cliente.com.br',
        'senha' => 'senha-do-portal-10',
    ])->assertRedirect(route('empresa.painel'));
});
