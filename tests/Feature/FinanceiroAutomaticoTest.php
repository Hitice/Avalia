<?php

use App\Actions\Consumo\FecharCompetencia;
use App\Actions\Financeiro\AtualizarInadimplencia;
use App\Actions\Financeiro\FecharCompetenciasVencidas;
use App\Models\Auditoria;
use App\Models\Catalogo;
use App\Models\Cliente;
use App\Models\CobrancaAsaas;
use App\Models\EventoAsaas;
use App\Models\Fatura;
use App\Models\Plano;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Uma empresa com plano na faixa de R$ 900 e vendedor na carteira.
 *
 * As rotinas deste arquivo mexem em dinheiro sozinhas, sem ninguem olhando:
 * fecham competencia, marcam vencimento, bloqueiam cliente e liberam comissao.
 * E onde um erro passa despercebido por mais tempo.
 */
function empresaComPlano(array $atributos = []): Cliente
{
    $catalogo = Catalogo::factory()->comServico('scpc-bvs', [90_000 => 324])->create();
    $catalogo->precos()->update(['custo_cents' => 150]);

    $plano = Plano::factory()->consumoMinimo(900)->create([
        'catalogo_id' => $catalogo->id,
        'mensalidade_cents' => 7_990,
    ]);

    return Cliente::factory()->create($atributos + [
        'plano_id' => $plano->id,
        'vendedor_id' => Staff::factory()->create(['papel' => 'vendedor'])->id,
        'situacao' => 'ativo',
    ]);
}

/*
|--------------------------------------------------------------------------
| Fechamento automatico
|--------------------------------------------------------------------------
*/

it('fecha a competencia anterior de cada contrato ativo', function () {
    $empresa = empresaComPlano();

    $fechados = app(FecharCompetenciasVencidas::class)(new DateTimeImmutable('2026-08-15'));

    expect($fechados)->toBe(1)
        ->and($empresa->faturas()->first()->competencia)->toBe('2026-07');
});

it('nao fecha a mesma competencia duas vezes', function () {
    // A rotina roda todo dia. Sem esta garantia, o cliente receberia uma
    // fatura por dia do mes.
    $empresa = empresaComPlano();
    $quando = new DateTimeImmutable('2026-08-15');

    app(FecharCompetenciasVencidas::class)($quando);
    app(FecharCompetenciasVencidas::class)($quando);
    app(FecharCompetenciasVencidas::class)($quando);

    expect($empresa->faturas()->count())->toBe(1);
});

it('nao fecha contrato encerrado nem empresa sem plano', function () {
    empresaComPlano(['situacao' => 'inativo']);
    Cliente::factory()->create(['plano_id' => null]);

    expect(app(FecharCompetenciasVencidas::class)(new DateTimeImmutable('2026-08-15')))->toBe(0)
        ->and(Fatura::count())->toBe(0);
});

it('fecha a competencia de quem esta inadimplente', function () {
    // Parar de faturar quem deve seria perdoar a divida em silencio.
    empresaComPlano(['situacao' => 'inadimplente']);

    expect(app(FecharCompetenciasVencidas::class)(new DateTimeImmutable('2026-08-15')))->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Vencimento e bloqueio
|--------------------------------------------------------------------------
*/

it('marca a fatura como vencida depois do dia 10', function () {
    $empresa = empresaComPlano();
    $fatura = app(FecharCompetencia::class)($empresa, '2026-07')['fatura'];

    // Competencia 07 vence em 10/08. No dia 09 ainda esta em dia.
    app(AtualizarInadimplencia::class)(new DateTimeImmutable('2026-08-09'));
    expect($fatura->fresh()->situacao_pagamento)->toBe(Fatura::PAGAMENTO_PENDENTE);

    app(AtualizarInadimplencia::class)(new DateTimeImmutable('2026-08-11'));
    expect($fatura->fresh()->situacao_pagamento)->toBe(Fatura::PAGAMENTO_VENCIDO);
});

it('bloqueia as consultas dez dias depois do vencimento, e nao antes', function () {
    // A linha do tempo do PDD: vence dia 10, bloqueia dia 20. Entre os dois o
    // cliente e avisado e continua consultando.
    $empresa = empresaComPlano();
    app(FecharCompetencia::class)($empresa, '2026-07');

    app(AtualizarInadimplencia::class)(new DateTimeImmutable('2026-08-19'));
    expect($empresa->fresh()->situacao)->toBe('ativo');

    app(AtualizarInadimplencia::class)(new DateTimeImmutable('2026-08-20'));
    expect($empresa->fresh()->situacao)->toBe('inadimplente');
});

it('nao mexe em quem a administracao bloqueou nem em contrato encerrado', function () {
    // Bloqueio administrativo e decisao de gente: a rotina nao rebaixa para
    // inadimplente, que e um estado mais brando.
    foreach (['bloqueado', 'inativo'] as $situacao) {
        $empresa = empresaComPlano(['situacao' => $situacao]);
        app(FecharCompetencia::class)($empresa, '2026-07');

        app(AtualizarInadimplencia::class)(new DateTimeImmutable('2026-08-30'));

        expect($empresa->fresh()->situacao)->toBe($situacao);
    }
});

it('nao vence fatura ja paga', function () {
    $empresa = empresaComPlano();
    $fatura = app(FecharCompetencia::class)($empresa, '2026-07')['fatura'];
    $fatura->update(['situacao_pagamento' => Fatura::PAGAMENTO_LIQUIDADO]);

    app(AtualizarInadimplencia::class)(new DateTimeImmutable('2026-12-31'));

    expect($fatura->fresh()->situacao_pagamento)->toBe(Fatura::PAGAMENTO_LIQUIDADO)
        ->and($empresa->fresh()->situacao)->toBe('ativo');
});

it('registra o bloqueio na trilha, uma vez so', function () {
    $empresa = empresaComPlano();
    app(FecharCompetencia::class)($empresa, '2026-07');

    app(AtualizarInadimplencia::class)(new DateTimeImmutable('2026-08-25'));
    app(AtualizarInadimplencia::class)(new DateTimeImmutable('2026-08-26'));

    expect(Auditoria::where('acao', 'cliente.inadimplente')->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Webhook de pagamento
|--------------------------------------------------------------------------
*/

/** Cria fatura e a cobranca correspondente, como o fluxo real faria. */
function faturaComCobranca(string $chargeId = 'pay_123'): array
{
    $empresa = empresaComPlano();
    $fatura = app(FecharCompetencia::class)($empresa, '2026-07')['fatura'];

    // O fechamento ja cria a cobranca. Aqui so entra o id que o provedor
    // devolveria, que e por onde o webhook encontra a fatura.
    $cobranca = CobrancaAsaas::where('fatura_id', $fatura->id)->firstOrFail();
    $cobranca->update(['asaas_charge_id' => $chargeId]);

    return [$fatura, $cobranca, $empresa];
}

/** @return array<string, string> */
function comToken(): array
{
    config()->set('services.asaas.webhook_token', 'token-de-teste');

    return ['asaas-access-token' => 'token-de-teste'];
}

it('recusa webhook sem token e com token errado', function () {
    // Sem CSRF, o token do provedor e a unica coisa entre a internet e a
    // liberacao de comissao.
    comToken();

    $this->postJson(route('webhooks.asaas'), ['event' => 'PAYMENT_RECEIVED'])
        ->assertForbidden();

    $this->withHeaders(['asaas-access-token' => 'errado'])
        ->postJson(route('webhooks.asaas'), ['event' => 'PAYMENT_RECEIVED'])
        ->assertForbidden();
});

it('recusa webhook quando nao ha token configurado', function () {
    // Configuracao vazia nao pode virar porta aberta.
    config()->set('services.asaas.webhook_token', '');

    $this->withHeaders(['asaas-access-token' => ''])
        ->postJson(route('webhooks.asaas'), ['event' => 'PAYMENT_RECEIVED'])
        ->assertForbidden();
});

it('liquida a fatura quando o pagamento e confirmado', function () {
    [$fatura] = faturaComCobranca();

    $this->withHeaders(comToken())->postJson(route('webhooks.asaas'), [
        'id' => 'evt_1',
        'event' => 'PAYMENT_RECEIVED',
        'payment' => ['id' => 'pay_123', 'status' => 'RECEIVED', 'paymentDate' => '2026-08-08'],
    ])->assertOk();

    $fatura->refresh();

    expect($fatura->estaLiquidada())->toBeTrue()
        ->and($fatura->liquidada_em->format('Y-m-d'))->toBe('2026-08-08')
        ->and($fatura->comissao_liberada_em)->not->toBeNull();
});

it('nao libera comissao duas vezes com o mesmo evento', function () {
    // O provedor reentrega evento quando nao recebe confirmacao. Sem
    // idempotencia, cada reentrega pagaria o vendedor de novo.
    [$fatura] = faturaComCobranca();

    $corpo = [
        'id' => 'evt_1',
        'event' => 'PAYMENT_RECEIVED',
        'payment' => ['id' => 'pay_123', 'status' => 'RECEIVED'],
    ];

    $this->withHeaders(comToken())->postJson(route('webhooks.asaas'), $corpo)->assertOk();
    $liberadaEm = $fatura->fresh()->comissao_liberada_em;

    $this->withHeaders(comToken())->postJson(route('webhooks.asaas'), $corpo)->assertOk();

    expect(EventoAsaas::count())->toBe(1)
        ->and($fatura->fresh()->comissao_liberada_em->timestamp)->toBe($liberadaEm->timestamp);
});

it('guarda o evento mesmo sem cobranca correspondente', function () {
    // Evento de uma cobranca que nao e nossa nao pode derrubar o webhook: o
    // provedor trataria o erro como falha e reentregaria para sempre.
    comToken();

    $this->withHeaders(comToken())->postJson(route('webhooks.asaas'), [
        'id' => 'evt_orfao',
        'event' => 'PAYMENT_RECEIVED',
        'payment' => ['id' => 'pay_de_outro'],
    ])->assertOk();

    expect(EventoAsaas::where('evento_externo', 'evt_orfao')->exists())->toBeTrue();
});

it('nao liquida com evento que nao e de pagamento', function () {
    [$fatura] = faturaComCobranca();

    $this->withHeaders(comToken())->postJson(route('webhooks.asaas'), [
        'id' => 'evt_2',
        'event' => 'PAYMENT_UPDATED',
        'payment' => ['id' => 'pay_123', 'status' => 'PENDING'],
    ])->assertOk();

    expect($fatura->fresh()->estaLiquidada())->toBeFalse();
});

it('devolve a empresa a ativo quando a ultima fatura aberta e paga', function () {
    [$fatura, , $empresa] = faturaComCobranca();
    $empresa->update(['situacao' => 'inadimplente']);

    $this->withHeaders(comToken())->postJson(route('webhooks.asaas'), [
        'id' => 'evt_3',
        'event' => 'PAYMENT_CONFIRMED',
        'payment' => ['id' => 'pay_123', 'status' => 'CONFIRMED'],
    ])->assertOk();

    expect($empresa->fresh()->situacao)->toBe('ativo');
});
