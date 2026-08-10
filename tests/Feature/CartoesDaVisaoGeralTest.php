<?php

use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Os indicadores da visao geral levam ao detalhe.
 *
 * A pergunta seguinte a um numero e sempre "de onde saiu isso?", e a resposta
 * ficava a dois cliques de menu. O que se protege aqui e o destino de cada
 * cartao e, principalmente, o financeiro: cartao que leva a 403 ensina o
 * operador a ignorar o cartao.
 */
it('leva cada indicador ao lugar onde ele e detalhado', function () {
    $resposta = admin()->get(route('painel'))->assertOk();

    foreach ([
        route('empresas.index'),
        route('consultas'),
        route('catalogo.tabela', ['visao' => 'custo']),
        route('catalogo.tabela', ['visao' => 'margem']),
        route('financeiro.index', ['situacao' => 'pendente']),
        route('financeiro.index', ['situacao' => 'vencido']),
    ] as $destino) {
        $resposta->assertSee($destino, false);
    }
});

it('nao oferece o financeiro a quem nao tem a permissao', function () {
    $semFinanceiro = Staff::factory()->create(['papel' => 'admin', 'pode_financeiro' => false]);

    $resposta = comoVendedor($semFinanceiro)->get(route('painel'))->assertOk();

    // Os números continuam na tela; o que some é o link que daria 403.
    $resposta->assertSee('A receber')
        ->assertDontSee(route('financeiro.index', ['situacao' => 'pendente']), false);
});

it('mantem o cartao sem destino como cartao, e nao como link quebrado', function () {
    $semFinanceiro = Staff::factory()->create(['papel' => 'admin', 'pode_financeiro' => false]);

    $html = comoVendedor($semFinanceiro)->get(route('painel'))->getContent();

    // O trecho do cartao "Em atraso" nao pode ter virado <a href="">.
    expect($html)->not->toContain('<a  class="cartao')
        ->and($html)->not->toContain('href=""');
});
