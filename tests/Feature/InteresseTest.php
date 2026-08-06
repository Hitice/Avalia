<?php

use App\Models\Interessado;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * O formulario da campanha e o unico POST publico que grava no banco.
 *
 * O que se protege aqui: o dado pessoal do interessado entra pelo nosso banco
 * em vez de viajar numa URL de WhatsApp, o robo nao enche a fila, e o pedido
 * aparece para quem vai ligar de volta.
 */
it('grava o pedido de contato e confirma na tela', function () {
    $this->from('/')->post(route('interesse.salvar'), [
        'nome' => 'Marina Costa',
        'empresa' => 'Costa Autopeças',
        'telefone' => '(34) 99911-2233',
        'email' => 'marina@costapecas.com.br',
        'funcionarios' => '6 a 20',
    ])->assertRedirect('/')->assertSessionHas('interesse_ok');

    $interessado = Interessado::sole();

    expect($interessado->empresa)->toBe('Costa Autopeças')
        ->and($interessado->origem)->toBe('campanha')
        ->and($interessado->atendido_em)->toBeNull();
});

it('descarta robo em silencio', function () {
    // O campo armadilha e invisivel para gente e irresistivel para script.
    // A resposta finge sucesso: acusar a deteccao e ensinar o robo a passar.
    $this->from('/')->post(route('interesse.salvar'), [
        'nome' => 'Robo Qualquer',
        'empresa' => 'Spam LTDA',
        'telefone' => '(00) 00000-0000',
        'email' => 'spam@spam.com',
        'funcionarios' => 'Até 5',
        'site' => 'https://spam.example',
    ])->assertRedirect('/')->assertSessionHas('interesse_ok');

    expect(Interessado::count())->toBe(0);
});

it('recusa pedido incompleto sem perder o que foi digitado', function () {
    $this->from('/')->post(route('interesse.salvar'), [
        'nome' => 'Jo',
        'empresa' => 'Sem Telefone ME',
        'telefone' => '',
        'email' => 'nao-e-email',
        'funcionarios' => '6 a 20',
    ])->assertRedirect('/')->assertSessionHasErrors(['nome', 'telefone', 'email']);

    expect(Interessado::count())->toBe(0);
});

it('mostra a fila de interessados no painel da administracao', function () {
    Interessado::create([
        'nome' => 'Marina Costa',
        'empresa' => 'Costa Autopeças',
        'telefone' => '(34) 99911-2233',
        'email' => 'marina@costapecas.com.br',
        'funcionarios' => '6 a 20',
    ]);

    admin()->get(route('painel'))->assertOk()
        ->assertSee('Pedidos de contato aguardando retorno')
        ->assertSee('Costa Autopeças');
});

it('marca o pedido como atendido e tira da fila', function () {
    $interessado = Interessado::create([
        'nome' => 'Marina Costa',
        'empresa' => 'Costa Autopeças',
        'telefone' => '(34) 99911-2233',
        'email' => 'marina@costapecas.com.br',
        'funcionarios' => '6 a 20',
    ]);

    admin()->from(route('painel'))
        ->post(route('interessados.atendido', $interessado))
        ->assertRedirect(route('painel'))
        ->assertSessionHas('ok');

    // Sai da fila, fica no banco: o registro mede qual porta converte.
    expect($interessado->fresh()->atendido_em)->not->toBeNull()
        ->and(Interessado::aguardando()->count())->toBe(0)
        ->and(Interessado::count())->toBe(1);

    admin()->get(route('painel'))->assertDontSee('Costa Autopeças');
});

it('nao deixa o vendedor atender pedido da fila', function () {
    // A fila e da administracao: o lead ainda nao tem carteira.
    $interessado = Interessado::create([
        'nome' => 'Marina Costa',
        'empresa' => 'Costa Autopeças',
        'telefone' => '(34) 99911-2233',
        'email' => 'marina@costapecas.com.br',
        'funcionarios' => '6 a 20',
    ]);

    [$vendedor] = carteira();

    comoVendedor($vendedor)->post(route('interessados.atendido', $interessado))
        ->assertForbidden();

    expect($interessado->fresh()->atendido_em)->toBeNull();
});

it('nao mostra a fila ao vendedor', function () {
    // O lead ainda nao tem carteira: distribui-lo e decisao da administracao.
    Interessado::create([
        'nome' => 'Marina Costa',
        'empresa' => 'Costa Autopeças',
        'telefone' => '(34) 99911-2233',
        'email' => 'marina@costapecas.com.br',
        'funcionarios' => '6 a 20',
    ]);

    [$vendedor] = carteira();

    comoVendedor($vendedor)->get(route('painel'))->assertOk()
        ->assertDontSee('Costa Autopeças');
});
