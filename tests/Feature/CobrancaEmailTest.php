<?php

use App\Actions\Consumo\FecharCompetencia;
use App\Actions\Financeiro\AtualizarInadimplencia;
use App\Actions\Financeiro\AvisarVencimentoProximo;
use App\Actions\Financeiro\RegistrarLiquidacao;
use App\Mail\ConsultasBloqueadas;
use App\Mail\FaturaEmitida;
use App\Mail\FaturaVencida;
use App\Mail\ReciboDeLiquidacao;
use App\Mail\VencimentoProximo;
use App\Models\Fatura;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

/**
 * Os e-mails do ciclo de cobranca.
 *
 * Cada evento financeiro que muda a vida do cliente avisa o cliente: fatura
 * emitida, vencimento chegando, pagamento confirmado e consulta suspensa.
 * O que os testes garantem, alem do envio, e o contrario dele: nenhum evento
 * repetido pode mandar o mesmo e-mail duas vezes.
 */
function faturaPendente(string $competencia, array $extra = []): Fatura
{
    $empresa = empresaComPlano();

    return Fatura::create($extra + [
        'cliente_id' => $empresa->id,
        'vendedor_id' => $empresa->vendedor_id,
        'competencia' => $competencia,
        'mensalidade_cents' => 7_990,
        'consumo_minimo_cents' => 90_000,
        'consumo_realizado_cents' => 0,
        'consumo_faturado_cents' => 90_000,
        'total_cents' => 97_990,
        'imposto_bps' => 0,
        'imposto_cents' => 0,
        'custo_cents' => 0,
        'lucro_cents' => 97_990,
        'comissao_pct' => 0,
        'comissao_cents' => 0,
        'situacao_pagamento' => Fatura::PAGAMENTO_PENDENTE,
        'fechada_em' => now(),
    ]);
}

/*
|--------------------------------------------------------------------------
| Fatura emitida
|--------------------------------------------------------------------------
*/

it('avisa a empresa quando a fatura e emitida', function () {
    Mail::fake();
    $empresa = empresaComPlano();

    app(FecharCompetencia::class)($empresa, '2026-07');

    Mail::assertSent(FaturaEmitida::class, fn ($m) => $m->hasTo($empresa->email));
});

it('nao avisa quando o fechamento e recusado', function () {
    Mail::fake();
    $empresa = empresaComPlano();
    app(FecharCompetencia::class)($empresa, '2026-07');
    Mail::fake();

    // Segunda tentativa da mesma competencia: recusada, nada sai.
    app(FecharCompetencia::class)($empresa, '2026-07');

    Mail::assertNothingSent();
});

/*
|--------------------------------------------------------------------------
| Lembrete de vencimento
|--------------------------------------------------------------------------
*/

it('lembra o vencimento ate 3 dias antes, uma vez so', function () {
    Mail::fake();
    // Competencia 2026-07 vence em 10/08. No dia 7, faltam 3 dias.
    $fatura = faturaPendente('2026-07');

    $enviados = app(AvisarVencimentoProximo::class)(new DateTimeImmutable('2026-08-07'));

    expect($enviados)->toBe(1)
        ->and($fatura->fresh()->aviso_vencimento_em)->not->toBeNull();
    Mail::assertSent(VencimentoProximo::class, fn ($m) => $m->hasTo($fatura->cliente->email));

    // A rodada seguinte, ainda dentro da janela, nao repete o lembrete.
    expect(app(AvisarVencimentoProximo::class)(new DateTimeImmutable('2026-08-08')))->toBe(0);
});

it('nao lembra fora da janela nem fatura ja resolvida', function () {
    Mail::fake();
    // Longe do vencimento: 10/08 esta a mais de 3 dias de 01/08.
    faturaPendente('2026-07');
    expect(app(AvisarVencimentoProximo::class)(new DateTimeImmutable('2026-08-01')))->toBe(0);

    // Ja paga: nao ha o que lembrar.
    faturaPendente('2026-06', ['situacao_pagamento' => Fatura::PAGAMENTO_LIQUIDADO]);
    expect(app(AvisarVencimentoProximo::class)(new DateTimeImmutable('2026-07-08')))->toBe(0);

    // Ja vencida: lembrete de "vence em breve" depois do vencimento e ruido.
    faturaPendente('2026-05');
    expect(app(AvisarVencimentoProximo::class)(new DateTimeImmutable('2026-06-15')))->toBe(0);

    Mail::assertNothingSent();
});

/*
|--------------------------------------------------------------------------
| Recibo de pagamento
|--------------------------------------------------------------------------
*/

it('manda o recibo quando o pagamento e confirmado, uma vez so', function () {
    Mail::fake();
    $fatura = faturaPendente('2026-07');

    app(RegistrarLiquidacao::class)($fatura);

    Mail::assertSent(ReciboDeLiquidacao::class, fn ($m) => $m->hasTo($fatura->cliente->email));

    // A mesma confirmacao chegando de novo (reentrega do provedor) nao pode
    // gerar um segundo recibo.
    Mail::fake();
    app(RegistrarLiquidacao::class)($fatura->fresh());
    Mail::assertNothingSent();
});

it('avisa no recibo quando o pagamento tambem libera as consultas', function () {
    Mail::fake();
    $fatura = faturaPendente('2026-07', ['situacao_pagamento' => Fatura::PAGAMENTO_VENCIDO]);
    $fatura->cliente->update(['situacao' => 'inadimplente']);

    app(RegistrarLiquidacao::class)($fatura);

    Mail::assertSent(ReciboDeLiquidacao::class, fn ($m) => $m->acessoLiberado === true);
});

/*
|--------------------------------------------------------------------------
| Vencimento e bloqueio por atraso
|--------------------------------------------------------------------------
*/

it('avisa a empresa quando a fatura vence, uma vez so', function () {
    Mail::fake();
    // Vencimento 10/08: a rotina do dia 11 encontra a fatura vencida.
    $fatura = faturaPendente('2026-07');

    app(AtualizarInadimplencia::class)(new DateTimeImmutable('2026-08-11'));

    expect($fatura->fresh()->situacao_pagamento)->toBe(Fatura::PAGAMENTO_VENCIDO);
    Mail::assertSent(FaturaVencida::class, fn ($m) => $m->hasTo($fatura->cliente->email));

    // No dia seguinte a fatura ja nao esta pendente: nenhum segundo aviso.
    Mail::fake();
    app(AtualizarInadimplencia::class)(new DateTimeImmutable('2026-08-12'));
    Mail::assertNothingSent();
});

it('avisa a empresa quando as consultas sao suspensas, uma vez so', function () {
    Mail::fake();
    // Vencimento 10/08 + 10 dias de tolerancia: bloqueia em 20/08.
    $fatura = faturaPendente('2026-07', ['situacao_pagamento' => Fatura::PAGAMENTO_VENCIDO]);

    app(AtualizarInadimplencia::class)(new DateTimeImmutable('2026-08-20'));

    expect($fatura->cliente->fresh()->situacao)->toBe('inadimplente');
    Mail::assertSent(ConsultasBloqueadas::class, fn ($m) => $m->hasTo($fatura->cliente->email));

    // No dia seguinte o cliente ja nao esta ativo: nenhum segundo aviso.
    Mail::fake();
    app(AtualizarInadimplencia::class)(new DateTimeImmutable('2026-08-21'));
    Mail::assertNothingSent();
});
