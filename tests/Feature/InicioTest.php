<?php

use App\Models\Cliente;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * A unica pagina publica do sistema.
 *
 * O que estes testes guardam nao e o layout: e o que a pagina NAO pode conter.
 * Ela fica exposta a qualquer visitante, entao numero interno, nome de
 * fornecedor ou preco de tabela aparecendo aqui e vazamento para concorrente,
 * nao bug de tela.
 */
it('apresenta a Avalia com as duas saidas', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Venda a prazo')
        ->assertSee('Quero contratar')
        ->assertSee('Já sou cliente')
        ->assertSee('Campanha de adesão');
});

it('nao vaza fornecedor, preco nem numero interno', function () {
    $html = $this->get('/')->assertOk()->getContent();

    // Nomes de fornecedor nunca aparecem fora da administracao (PDD, secao 7),
    // e a pagina publica e o pior lugar possivel para o primeiro deslize.
    expect($html)->not->toContain('Boa Vista')
        ->not->toContain('Equifax')
        ->not->toContain('SPC')
        ->not->toContain('Serasa')
        // Preco e proposta moram atras do login, com contrato e catalogo.
        ->not->toContain('R$')
        ->not->toContain('Custo')
        ->not->toContain('Margem');
});

it('anuncia a campanha sem levar dado pessoal na conversa', function () {
    $html = $this->get('/')->assertOk()->getContent();

    // O botao da campanha abre o WhatsApp com o assunto ja escrito. So o
    // assunto: URL de conversa passa por servidor de terceiro e fica no
    // historico, entao nela nao entra nome, documento nem valor.
    expect($html)->toContain('wa.me/')
        ->toContain(rawurlencode('Campanha de adesão'));
});

it('leva cada sessao para o proprio painel sem mostrar a apresentacao', function () {
    $staff = Staff::factory()->admin()->create();
    $this->actingAs($staff, 'staff')->get('/')->assertRedirect(route('painel'));

    $this->flushSession();
    app('auth')->forgetGuards();

    $empresa = Cliente::factory()->create();
    $this->actingAs($empresa, 'empresa')->get('/')->assertRedirect(route('empresa.painel'));
});
