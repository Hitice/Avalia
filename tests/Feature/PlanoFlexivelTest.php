<?php

use App\Actions\Consumo\FecharCompetencia;
use App\Actions\Consumo\RegistrarConsulta;
use App\Models\Catalogo;
use App\Models\Plano;
use App\Models\Servico;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Plano flexivel: o minimo e piso de cobranca com valor livre, e a tabela de
 * precos pode ser negociada separada (assume 500 de minimo, leva a tabela de
 * 1.000). E a bonificacao que fecha venda; o que nao pode acontecer e uma
 * consulta sem coluna de preco, e e isso que a validacao trava.
 */
function catalogoComDuasFaixas(): Catalogo
{
    $catalogo = Catalogo::factory()
        ->comServico('scpc-bvs', [50_000 => 410, 90_000 => 324])
        ->create();
    $catalogo->precos()->update(['custo_cents' => 150]);

    return $catalogo;
}

it('cria plano com minimo livre lendo a tabela de outra faixa', function () {
    catalogoComDuasFaixas();

    admin()->post(route('catalogo.planos.salvar'), [
        'nome' => 'Sob medida 1.350',
        'mensalidade' => '79,90',
        'consumo_minimo' => '1.350,00',
        'faixa_preco' => '900,00',
        'ativo' => '1',
    ])->assertSessionDoesntHaveErrors();

    $plano = Plano::firstWhere('nome', 'Sob medida 1.350');

    expect($plano->consumo_minimo_cents)->toBe(135_000)
        ->and($plano->faixaDePrecoCents())->toBe(90_000)
        ->and($plano->faixaValida())->toBeTrue();
});

it('recusa minimo fora de faixa quando nao ha tabela negociada', function () {
    catalogoComDuasFaixas();

    admin()->post(route('catalogo.planos.salvar'), [
        'nome' => 'Sem coluna',
        'mensalidade' => '79,90',
        'consumo_minimo' => '1.350,00',
        'ativo' => '1',
    ])->assertSessionHasErrors('consumo_minimo');

    expect(Plano::count())->toBe(0);
});

it('consulta pelo preco da tabela negociada e fatura pelo minimo livre', function () {
    // Minimo de R$ 500 com a tabela de R$ 900: a consulta sai a R$ 3,24 (preco
    // da faixa maior) e o mes fatura no piso de R$ 500.
    $catalogo = catalogoComDuasFaixas();
    $servico = Servico::firstWhere('codigo', 'scpc-bvs');

    $plano = Plano::factory()->consumoMinimo(500)->create([
        'catalogo_id' => $catalogo->id,
        'faixa_preco_cents' => 90_000,
        'mensalidade_cents' => 7_990,
    ]);

    $empresa = App\Models\Cliente::factory()->create([
        'plano_id' => $plano->id,
        'situacao' => 'ativo',
    ]);

    app(RegistrarConsulta::class)($empresa, $servico, 2);

    expect((int) $empresa->consultas()->sum('preco_cents'))->toBe(648);

    $fatura = app(FecharCompetencia::class)($empresa, App\Models\Consulta::competenciaDe())['fatura'];

    expect($fatura->consumo_minimo_cents)->toBe(50_000)
        ->and($fatura->consumo_faturado_cents)->toBe(50_000)
        ->and($fatura->total_cents)->toBe(57_990);
});
