<?php

use App\Actions\Consumo\FecharCompetencia;
use App\Actions\Consumo\RegistrarConsulta;
use App\Actions\Financeiro\AtualizarInadimplencia;
use App\Actions\Financeiro\RegistrarLiquidacao;
use App\Models\Auditoria;
use App\Models\Catalogo;
use App\Models\Cliente;
use App\Models\Consulta;
use App\Models\Plano;
use App\Models\Servico;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Monta empresa com plano na faixa de R$ 900, servico a R$ 3,24 e custo R$ 1,50.
 *
 * @return array{0: Cliente, 1: Servico, 2: Catalogo}
 */
function contrato(): array
{
    $catalogo = Catalogo::factory()->comServico('scpc-bvs', [90_000 => 324])->create();
    $catalogo->precos()->update(['custo_cents' => 150]);

    $plano = Plano::factory()->consumoMinimo(900)->create([
        'catalogo_id' => $catalogo->id,
        'mensalidade_cents' => 7_990,
    ]);

    $cliente = Cliente::factory()->create([
        'plano_id' => $plano->id,
        'vendedor_id' => Staff::factory()->create()->id,
        'situacao' => 'ativo',
    ]);

    return [$cliente, Servico::firstWhere('codigo', 'scpc-bvs'), $catalogo];
}

/*
|--------------------------------------------------------------------------
| Consulta
|--------------------------------------------------------------------------
*/

it('congela preco e custo no momento da consulta', function () {
    // E esta copia, e nao travar o catalogo, que impede um reajuste de hoje de
    // reescrever a cobranca de ontem.
    [$cliente, $servico, $catalogo] = contrato();

    app(RegistrarConsulta::class)($cliente, $servico, 3);

    $catalogo->precos()->update(['preco_cents' => 999, 'custo_cents' => 900]);

    expect(Consulta::count())->toBe(3)
        ->and(Consulta::pluck('preco_cents')->unique()->all())->toBe([324])
        ->and(Consulta::pluck('custo_cents')->unique()->all())->toBe([150]);
});

it('cobra o preco da faixa contratada, e nao o de outra', function () {
    $catalogo = Catalogo::factory()->comServico('scpc-bvs', [0 => 450, 90_000 => 324])->create();
    $plano = Plano::factory()->consumoMinimo(900)->create(['catalogo_id' => $catalogo->id]);
    $cliente = Cliente::factory()->create(['plano_id' => $plano->id]);

    app(RegistrarConsulta::class)($cliente, Servico::firstWhere('codigo', 'scpc-bvs'));

    expect(Consulta::first()->preco_cents)->toBe(324);
});

it('nao deixa empresa inadimplente consultar, mas explica o motivo', function () {
    // Ela continua entrando no sistema para ver a fatura; o que fecha e a
    // consulta.
    [$cliente, $servico] = contrato();
    $cliente->update(['situacao' => 'inadimplente']);

    $resultado = app(RegistrarConsulta::class)($cliente, $servico);

    expect($resultado['erro'])->toContain('fatura em aberto')
        ->and(Consulta::count())->toBe(0);
});

it('nao consulta servico que aguarda liberacao juridica', function () {
    [$cliente, $servico] = contrato();
    $servico->update(['exige_liberacao' => true]);

    expect(app(RegistrarConsulta::class)($cliente, $servico)['erro'])
        ->toContain('nao esta liberado');

    expect(Consulta::count())->toBe(0);
});

it('recusa consulta em competencia ja fechada', function () {
    // A fatura ja virou cobranca: aceitar mais uma linha mudaria um numero que
    // o cliente ja recebeu.
    [$cliente, $servico] = contrato();
    app(FecharCompetencia::class)($cliente, Consulta::competenciaDe());

    expect(app(RegistrarConsulta::class)($cliente, $servico)['erro'])
        ->toContain('ja foi fechada');
});

/*
|--------------------------------------------------------------------------
| Fechamento
|--------------------------------------------------------------------------
*/

it('monta a cascata inteira na fatura', function () {
    [$cliente, $servico] = contrato();
    $mes = Consulta::competenciaDe();

    // 300 consultas a R$ 3,24 = R$ 972,00 de consumo, acima do minimo de R$ 900.
    app(RegistrarConsulta::class)($cliente, $servico, 300);

    $fatura = app(FecharCompetencia::class)($cliente, $mes)['fatura'];

    expect($fatura->consumo_realizado_cents)->toBe(97_200)
        ->and($fatura->consumo_faturado_cents)->toBe(97_200)
        ->and($fatura->total_cents)->toBe(105_190)          // + mensalidade
        ->and($fatura->imposto_cents)->toBe(14_201)         // 13,5%
        ->and($fatura->custo_cents)->toBe(45_000)           // 300 x R$ 1,50
        ->and($fatura->comissao_cents)->toBe(4_599)         // 10% do lucro
        ->and($fatura->lucro_cents)->toBe(41_390);

    // A cascata fecha: nada se perde entre o total e o lucro.
    expect($fatura->total_cents - $fatura->imposto_cents - $fatura->custo_cents
        - $fatura->comissao_cents)->toBe($fatura->lucro_cents);
});

it('cobra o minimo de quem consumiu menos, sem custear o que nao usou', function () {
    [$cliente, $servico] = contrato();
    $mes = Consulta::competenciaDe();

    app(RegistrarConsulta::class)($cliente, $servico, 100);   // R$ 324,00

    $fatura = app(FecharCompetencia::class)($cliente, $mes)['fatura'];

    expect($fatura->consumo_realizado_cents)->toBe(32_400)
        ->and($fatura->consumo_faturado_cents)->toBe(90_000)   // o piso
        ->and($fatura->pagouSemUsarCents())->toBe(57_600)
        ->and($fatura->custo_cents)->toBe(15_000);            // so o que consultou
});

it('guarda a aliquota usada, para a fatura antiga continuar explicavel', function () {
    [$cliente, $servico, $catalogo] = contrato();
    $fatura = app(FecharCompetencia::class)($cliente, Consulta::competenciaDe())['fatura'];

    $catalogo->update(['imposto_bps' => 2_000]);

    expect($fatura->fresh()->imposto_bps)->toBe(1_350)
        ->and($fatura->fresh()->comissao_pct)->toBe(10);
});

it('nao fecha a mesma competencia duas vezes', function () {
    [$cliente] = contrato();
    $mes = Consulta::competenciaDe();

    app(FecharCompetencia::class)($cliente, $mes);

    expect(app(FecharCompetencia::class)($cliente, $mes)['erro'])->toContain('ja esta fechada');
    expect($cliente->faturas()->count())->toBe(1);
});

it('fatura o minimo mesmo sem consulta nenhuma', function () {
    // Mensalidade e consumo minimo sao cobrados consumindo ou nao, e este e o
    // cliente mais lucrativo: paga o piso sem gerar custo.
    [$cliente] = contrato();

    $fatura = app(FecharCompetencia::class)($cliente, Consulta::competenciaDe())['fatura'];

    expect($fatura->total_cents)->toBe(97_990)
        ->and($fatura->custo_cents)->toBe(0)
        ->and($fatura->lucro_cents)->toBeGreaterThan(76_000);
});

it('vence sempre no dia 10 do mes seguinte', function () {
    [$cliente] = contrato();
    $fatura = app(FecharCompetencia::class)($cliente, '2026-08')['fatura'];

    expect($fatura->vencimento()->format('Y-m-d'))->toBe('2026-09-10');
});

it('leva o vendedor da carteira para a fatura', function () {
    // O repasse le a fatura, e nao o cadastro de hoje: trocar o dono da
    // carteira nao pode reescrever comissao ja apurada.
    [$cliente] = contrato();
    $vendedor = $cliente->vendedor_id;

    $fatura = app(FecharCompetencia::class)($cliente, Consulta::competenciaDe())['fatura'];
    $cliente->update(['vendedor_id' => Staff::factory()->create()->id]);

    expect($fatura->fresh()->vendedor_id)->toBe($vendedor);
});

it('aplica a franquia por serviço antes de calcular o excedente', function () {
    [$cliente, $servico] = contrato();
    $cliente->plano->franquias()->create(['servico_id' => $servico->id, 'quantidade' => 10]);

    app(RegistrarConsulta::class)($cliente, $servico, 12);
    $fatura = app(FecharCompetencia::class)($cliente, Consulta::competenciaDe())['fatura'];
    $item = $fatura->itens()->first();

    expect($fatura->consumo_bruto_cents)->toBe(3_888)
        ->and($fatura->franquia_cents)->toBe(3_240)
        ->and($fatura->consumo_excedente_cents)->toBe(648)
        ->and($fatura->consumo_faturado_cents)->toBe(90_000)
        ->and($item->quantidade_franquia)->toBe(10)
        ->and($item->quantidade_excedente)->toBe(2);
});

it('liquidação libera a comissão uma vez e restaura cliente inadimplente', function () {
    [$cliente] = contrato();
    $fatura = app(FecharCompetencia::class)($cliente, Consulta::competenciaDe())['fatura'];
    $cliente->update(['situacao' => 'inadimplente']);

    app(RegistrarLiquidacao::class)($fatura);
    app(RegistrarLiquidacao::class)($fatura->fresh());

    expect($fatura->fresh()->estaLiquidada())->toBeTrue()
        ->and($fatura->fresh()->comissao_liberada_em)->not->toBeNull()
        ->and($cliente->fresh()->situacao)->toBe('ativo')
        ->and(Auditoria::where('acao', 'fatura.liquidada')->count())->toBe(1);
});

it('marca a fatura vencida e bloqueia consultas no vigésimo dia', function () {
    [$cliente] = contrato();
    app(FecharCompetencia::class)($cliente, '2026-07');

    app(AtualizarInadimplencia::class)(new DateTimeImmutable('2026-08-11 00:00:00'));
    expect($cliente->faturas()->first()->fresh()->situacao_pagamento)->toBe('vencido')
        ->and($cliente->fresh()->situacao)->toBe('ativo');

    app(AtualizarInadimplencia::class)(new DateTimeImmutable('2026-08-20 00:00:00'));
    expect($cliente->fresh()->situacao)->toBe('inadimplente')
        ->and(Auditoria::where('acao', 'cliente.inadimplente')->count())->toBe(1);
});
