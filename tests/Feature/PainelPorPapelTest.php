<?php

use App\Actions\Consumo\FecharCompetencia;
use App\Actions\Consumo\RegistrarConsulta;
use App\Actions\Financeiro\AtualizarInadimplencia;
use App\Actions\Financeiro\RegistrarLiquidacao;
use App\Models\Consulta;
use App\Models\Fatura;
use App\Models\Servico;
use App\Support\Alertas;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * O painel inicial e um por trabalho, e nao um so filtrado.
 *
 * O que estes testes protegem nao e o layout: e o recorte. Numero da operacao
 * inteira na tela do vendedor volta a ensina-lo a ignorar a tela, que era o
 * problema descrito na secao 12 do PDD.
 */

/*
|--------------------------------------------------------------------------
| Administracao
|--------------------------------------------------------------------------
*/

it('mostra a administracao o custo do fornecedor do periodo', function () {
    $empresa = empresaComPlano();
    $servico = Servico::firstWhere('codigo', 'scpc-bvs');

    app(RegistrarConsulta::class)($empresa, $servico, 4);

    $resposta = admin()->get(route('painel'))->assertOk();

    // Preco 324 e custo 150 por consulta, congelados na consulta.
    expect($resposta->viewData('consumoCents'))->toBe(4 * 324)
        ->and($resposta->viewData('custoCents'))->toBe(4 * 150);
});

it('abre a comissao liberada por vendedor para o financeiro nao somar a mao', function () {
    $empresa = empresaComPlano();

    app(RegistrarConsulta::class)($empresa, Servico::firstWhere('codigo', 'scpc-bvs'), 10);
    app(FecharCompetencia::class)($empresa, '2026-07');
    app(RegistrarLiquidacao::class)(Fatura::first());

    $linhas = admin()->get(route('painel'))->assertOk()->viewData('comissaoPorVendedor');

    expect($linhas)->toHaveCount(1)
        ->and($linhas[0]['nome'])->toBe($empresa->vendedor->nome)
        ->and($linhas[0]['cents'])->toBe(Fatura::first()->comissao_cents);
});

/*
|--------------------------------------------------------------------------
| Vendedor
|--------------------------------------------------------------------------
*/

it('nao mostra ao vendedor dinheiro que e da Avalia', function () {
    [$vendedor] = carteira();

    $resposta = comoVendedor($vendedor)->get(route('painel'))->assertOk();

    // "A receber" e "em atraso" sao a cobranca da Avalia, e nao ha nada que o
    // vendedor faca com o numero total. O que e dele tem nome proprio.
    $resposta->assertDontSee('A receber')->assertDontSee('Em atraso');

    // Nem chegam a existir como variavel de view: nao ha o que imprimir por
    // descuido numa alteracao futura da tela.
    $dados = $resposta->original->getData();

    expect($dados)->not->toHaveKey('aReceber')
        ->not->toHaveKey('vencido')
        ->not->toHaveKey('custoCents')
        ->toHaveKey('comissaoLiberada');
});

it('conta so a carteira do vendedor no painel dele', function () {
    [$vendedor, $empresa, $servico] = carteira();

    $alheia = empresaComPlano();
    app(RegistrarConsulta::class)($empresa, $servico, 3);
    app(RegistrarConsulta::class)($alheia, Servico::firstWhere('codigo', 'scpc-bvs'), 7);

    $resposta = comoVendedor($vendedor)->get(route('painel'))->assertOk();

    expect($resposta->viewData('clientesAtivos'))->toBe(1)
        ->and($resposta->viewData('consultas'))->toBe(3);
});

/*
|--------------------------------------------------------------------------
| A caminho da suspensao
|--------------------------------------------------------------------------
*/

it('lista a empresa com fatura vencida enquanto ainda da tempo de ligar', function () {
    $empresa = empresaComPlano();

    app(FecharCompetencia::class)($empresa, '2026-07');

    // Vencimento em 10/08. Tres dias depois a fatura esta vencida e a suspensao
    // automatica ainda nao aconteceu: e a janela em que a ligacao resolve.
    $this->travelTo('2026-08-13');
    app(AtualizarInadimplencia::class)();

    $linhas = Alertas::aCaminhoDaSuspensao(Fatura::query());

    expect($linhas)->toHaveCount(1)
        ->and($linhas[0]['dias'])->toBe(Fatura::DIAS_ATE_BLOQUEIO - 3);
});

it('tira da lista a empresa que ja foi suspensa', function () {
    $empresa = empresaComPlano();

    app(FecharCompetencia::class)($empresa, '2026-07');

    // Passado o prazo, a suspensao ja aconteceu. A lista e aviso, e nao
    // relatorio do que nao deu tempo de evitar.
    $this->travelTo('2026-08-25');
    app(AtualizarInadimplencia::class)();

    expect($empresa->fresh()->situacao)->toBe('inadimplente')
        ->and(Alertas::aCaminhoDaSuspensao(Fatura::query()))->toBeEmpty();
});

it('nao deixa o vendedor ver empresa em risco de carteira alheia', function () {
    [$vendedor] = carteira();

    $alheia = empresaComPlano();
    app(FecharCompetencia::class)($alheia, '2026-07');

    $this->travelTo('2026-08-13');
    app(AtualizarInadimplencia::class)();

    $resposta = comoVendedor($vendedor)->get(route('painel'))->assertOk();

    expect($resposta->viewData('aCaminhoDaSuspensao'))->toBeEmpty();

    // A mesma fatura aparece para a administracao, que ve a operacao inteira.
    expect(admin()->get(route('painel'))->assertOk()->viewData('aCaminhoDaSuspensao'))
        ->toHaveCount(1);
});

/*
|--------------------------------------------------------------------------
| Pararam de consultar
|--------------------------------------------------------------------------
*/

it('poe quem nunca consultou no topo da lista de abandono', function () {
    [$vendedor, $usou, $servico] = carteira();

    $nuncaUsou = empresaComPlano([
        'razao_social' => 'Empresa Que Nunca Usou LTDA',
        'vendedor_id' => $vendedor->id,
    ]);

    // Contrato assinado e nunca usado nao vira renovacao: e o caso mais urgente
    // da lista, e por isso vem antes de quem apenas esfriou.
    Consulta::factory()->em(now()->subDays(90))->create([
        'cliente_id' => $usou->id,
        'servico_id' => $servico->id,
    ]);

    $linhas = comoVendedor($vendedor)->get(route('painel'))->assertOk()->viewData('pararamDeConsultar');

    expect($linhas)->toHaveCount(2)
        ->and($linhas[0]['cliente']->id)->toBe($nuncaUsou->id)
        ->and($linhas[0]['ultima'])->toBeNull()
        ->and($linhas[1]['cliente']->id)->toBe($usou->id);
});

it('nao acusa abandono de quem consultou dentro do prazo', function () {
    [$vendedor, $empresa, $servico] = carteira();

    Consulta::factory()->em(now()->subDays(Alertas::DIAS_SEM_CONSULTAR - 1))->create([
        'cliente_id' => $empresa->id,
        'servico_id' => $servico->id,
    ]);

    expect(comoVendedor($vendedor)->get(route('painel'))->assertOk()->viewData('pararamDeConsultar'))
        ->toBeEmpty();
});
