<?php

use App\Actions\Consumo\FecharCompetencia;
use App\Actions\Consumo\RegistrarConsulta;
use App\Actions\Financeiro\RegistrarLiquidacao;
use App\Models\Cliente;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| O que o vendedor ve
|--------------------------------------------------------------------------
*/

it('mostra ao vendedor as empresas da carteira dele', function () {
    [$vendedor, $empresa] = carteira();

    comoVendedor($vendedor)->get(route('carteira'))
        ->assertOk()
        ->assertSee('Empresa da Carteira LTDA');
});

it('nao mostra empresa de outra carteira', function () {
    // Nao ha parametro de rota que escolha a carteira: e sempre a de quem
    // esta logado, entao nao ha URL que peca a de outro.
    [$vendedor] = carteira();
    Cliente::factory()->create([
        'razao_social' => 'Empresa de Outro LTDA',
        'vendedor_id' => Staff::factory()->create(['papel' => 'vendedor'])->id,
    ]);

    comoVendedor($vendedor)->get(route('carteira'))
        ->assertOk()
        ->assertDontSee('Empresa de Outro LTDA');
});

it('nao leva custo, lucro nem margem para a tela do vendedor', function () {
    // Sao numeros internos: a carteira e tela separada justamente para nao
    // depender de um `@if` para nao vazar.
    [$vendedor, $empresa, $servico] = carteira();

    app(RegistrarConsulta::class)($empresa, $servico, 10);
    app(FecharCompetencia::class)($empresa, '2026-07');

    $html = comoVendedor($vendedor)->get(route('carteira'))->assertOk()->getContent();

    expect($html)->not->toContain('Custo')
        ->not->toContain('Lucro')
        ->not->toContain('Margem');
});

it('soma na comissao liberada so o que ja foi pago', function () {
    [$vendedor, $empresa] = carteira();

    $fatura = app(FecharCompetencia::class)($empresa, '2026-07')['fatura'];

    $resposta = comoVendedor($vendedor)->get(route('carteira'))->assertOk();

    expect($resposta->viewData('aReceber'))->toBe(0)
        ->and($resposta->viewData('aConfirmar'))->toBe($fatura->comissao_cents);

    app(RegistrarLiquidacao::class)($fatura);

    $depois = comoVendedor($vendedor)->get(route('carteira'))->assertOk();

    expect($depois->viewData('aReceber'))->toBe($fatura->comissao_cents)
        ->and($depois->viewData('aConfirmar'))->toBe(0);
});

it('mostra o consumo do mes que ainda nao fechou', function () {
    [$vendedor, $empresa, $servico] = carteira();

    app(RegistrarConsulta::class)($empresa, $servico, 5);   // 5 x R$ 3,24

    comoVendedor($vendedor)->get(route('carteira'))
        ->assertOk()
        ->assertSee("R$\u{00A0}16,20", false);
});

/*
|--------------------------------------------------------------------------
| O que ele continua sem poder abrir
|--------------------------------------------------------------------------
*/

it('nao abre catalogo, empresas nem financeiro pela carteira', function () {
    [$vendedor] = carteira();

    foreach (['catalogo.tabela', 'empresas.index', 'financeiro.index', 'auditoria'] as $rota) {
        comoVendedor($vendedor)->get(route($rota))->assertForbidden();
    }
});

it('poe a carteira no menu do vendedor e esconde do admin', function () {
    [$vendedor] = carteira();

    comoVendedor($vendedor)->get('/painel')->assertOk()->assertSee('href="/carteira"', false);

    admin()->get('/painel')->assertOk()->assertDontSee('href="/carteira"', false);
});

/*
|--------------------------------------------------------------------------
| Cadastro pelo vendedor
|--------------------------------------------------------------------------
*/

it('deixa o vendedor cadastrar empresa, sempre na carteira dele', function () {
    // Cadastrar nao pode virar uma forma de pegar cliente de outro vendedor.
    [$vendedor] = carteira();
    $outro = Staff::factory()->create(['papel' => 'vendedor']);

    comoVendedor($vendedor)->post(route('empresas.salvar'), [
        'razao_social' => 'Cliente do Vendedor LTDA',
        'cnpj' => '12.345.678/0001-95',
        'email' => 'cliente@vendedor.com.br',
        'senha' => 'senha-valida-123',
        'situacao' => 'ativo',
        'vendedor_id' => $outro->id,   // forjado no POST
    ])->assertRedirect(route('carteira'));

    expect(Cliente::firstWhere('cnpj', '12345678000195')->vendedor_id)->toBe($vendedor->id);
});

it('nao deixa o vendedor mexer na situacao da empresa', function () {
    // Situacao responde por acesso e cobranca: marcar como ativa desfaria um
    // bloqueio por inadimplencia.
    [$vendedor, $empresa] = carteira();
    $empresa->update(['situacao' => 'inadimplente', 'cnpj' => '12345678000195']);

    comoVendedor($vendedor)->put(route('empresas.atualizar', $empresa), [
        'razao_social' => $empresa->razao_social,
        'cnpj' => $empresa->cnpj,
        'email' => $empresa->email,
        'situacao' => 'ativo',
    ])->assertRedirect(route('carteira'));

    expect($empresa->fresh()->situacao)->toBe('inadimplente');
});

it('nao deixa o vendedor editar empresa de outra carteira', function () {
    [$vendedor] = carteira();
    $alheia = Cliente::factory()->create([
        'vendedor_id' => Staff::factory()->create(['papel' => 'vendedor'])->id,
    ]);

    comoVendedor($vendedor)->get(route('empresas.editar', $alheia))->assertForbidden();
    comoVendedor($vendedor)->delete(route('empresas.remover', $alheia))->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| Remocao
|--------------------------------------------------------------------------
*/

it('remove da carteira sem apagar, e a administracao continua vendo', function () {
    [$vendedor, $empresa] = carteira();

    comoVendedor($vendedor)->delete(route('empresas.remover', $empresa))
        ->assertRedirect(route('carteira'));

    // Sumiu da carteira dele. A assercao e sobre os dados da lista, e nao
    // sobre a pagina: o nome ainda aparece na mensagem de sucesso.
    expect(comoVendedor($vendedor)->get(route('carteira'))->assertOk()->viewData('empresas'))
        ->toBeEmpty();

    // E continua la para a administracao.
    expect(Cliente::withTrashed()->find($empresa->id))->not->toBeNull();

    admin()->get(route('empresas.index', ['removidas' => 1]))
        ->assertOk()
        ->assertSee($empresa->razao_social);
});

it('derruba a sessao da empresa removida', function () {
    // Sem isso ela continuaria consultando ate o cookie expirar.
    [$vendedor, $empresa] = carteira();
    $versao = $empresa->sessao_versao;

    comoVendedor($vendedor)->delete(route('empresas.remover', $empresa));

    expect(Cliente::withTrashed()->find($empresa->id)->sessao_versao)->toBeGreaterThan($versao);
});

it('nao deixa o vendedor remover empresa que ja tem fatura', function () {
    // A partir da primeira cobranca ela deixou de ser cadastro e virou
    // financeiro: sumir com ela mudaria numero de fechamento.
    [$vendedor, $empresa] = carteira();
    app(FecharCompetencia::class)($empresa, '2026-07');

    comoVendedor($vendedor)->delete(route('empresas.remover', $empresa))
        ->assertSessionHas('erro');

    expect(Cliente::find($empresa->id))->not->toBeNull();
});

it('deixa a administracao remover e restaurar', function () {
    [, $empresa] = carteira();
    app(FecharCompetencia::class)($empresa, '2026-07');

    admin()->delete(route('empresas.remover', $empresa))->assertRedirect(route('empresas.index'));
    expect(Cliente::find($empresa->id))->toBeNull();

    admin()->post(route('empresas.restaurar', $empresa->id))->assertRedirect(route('empresas.index'));
    expect(Cliente::find($empresa->id))->not->toBeNull();
});
