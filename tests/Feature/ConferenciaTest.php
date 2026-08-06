<?php

use App\Actions\Consumo\FecharCompetencia;
use App\Models\Auditoria;
use App\Models\CobrancaAsaas;
use App\Models\EventoAsaas;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Conferencia diaria
|--------------------------------------------------------------------------
|
| Cada divergencia aqui e silenciosa: nada quebra, nenhuma tela mostra erro,
| e o problema aparece semanas depois no extrato de alguem.
|
*/

it('passa quando os numeros batem', function () {
    $empresa = empresaComPlano();
    app(FecharCompetencia::class)($empresa, '2026-07');

    $this->artisan('avalia:conferir')->assertSuccessful();
});

it('acusa fatura cujas partes nao somam o total', function () {
    $empresa = empresaComPlano();
    $fatura = app(FecharCompetencia::class)($empresa, '2026-07')['fatura'];

    // Alguem mexeu no lucro sem mexer no resto.
    $fatura->update(['lucro_cents' => $fatura->lucro_cents + 100]);

    $this->artisan('avalia:conferir')->assertFailed();
});

it('acusa consumo da fatura diferente da soma das consultas', function () {
    $empresa = empresaComPlano();
    $fatura = app(FecharCompetencia::class)($empresa, '2026-07')['fatura'];

    $fatura->update(['consumo_bruto_cents' => 9_999]);

    $this->artisan('avalia:conferir')->assertFailed();
});

it('acusa fatura em aberto sem cobranca', function () {
    $empresa = empresaComPlano();
    $fatura = app(FecharCompetencia::class)($empresa, '2026-07')['fatura'];

    CobrancaAsaas::where('fatura_id', $fatura->id)->delete();

    $this->artisan('avalia:conferir')->assertFailed();
});

it('acusa cobranca que nunca chegou ao provedor', function () {
    // Sem identificador externo, o cliente nao recebeu boleto nenhum. So vale
    // como divergencia quando o provedor esta configurado: sem ele, cobranca
    // sem identificador e o esperado.
    config()->set('services.asaas.api_key', 'chave-de-teste');

    $empresa = empresaComPlano();
    app(FecharCompetencia::class)($empresa, '2026-07');

    expect(CobrancaAsaas::whereNull('asaas_charge_id')->count())->toBe(1);

    $this->artisan('avalia:conferir')->assertFailed();
});

it('nao acusa cobranca sem identificador quando nao ha provedor configurado', function () {
    // Acusar isso todo dia ensinaria a ignorar o relatorio justamente antes de
    // ele passar a dizer algo.
    config()->set('services.asaas.api_key', '');

    $empresa = empresaComPlano();
    app(FecharCompetencia::class)($empresa, '2026-07');

    $this->artisan('avalia:conferir')->assertSuccessful();
});

it('acusa evento recebido sem cobranca correspondente', function () {
    // Alguem pagou algo que o sistema nao sabe reconhecer.
    EventoAsaas::create([
        'evento_externo' => 'evt_orfao',
        'tipo' => 'PAYMENT_RECEIVED',
        'payload' => [],
        'recebido_em' => now(),
    ]);

    $this->artisan('avalia:conferir')->assertFailed();
});

it('acusa trilha adulterada', function () {
    $empresa = empresaComPlano();
    app(FecharCompetencia::class)($empresa, '2026-07');

    Auditoria::first()->update(['dados' => ['trocado' => 'depois']]);

    $this->artisan('avalia:conferir')->assertFailed();
});
