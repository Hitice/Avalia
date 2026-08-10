<?php

use App\Actions\Consumo\FecharCompetencia;
use App\Mail\FaturaEmitida;
use App\Models\Consulta;
use App\Models\Fatura;
use App\Models\Servico;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

/**
 * As acoes da tela do financeiro: por linha e em lote.
 *
 * O que se protege aqui, acima de tudo, e o que NAO existe em lote. A baixa de
 * pagamento exige justificativa por fatura e libera comissao do vendedor na
 * hora; em lote ela seria a porta mais larga do sistema para dinheiro dado como
 * recebido sem ter entrado.
 */
function faturaAberta(array $empresa = []): Fatura
{
    $cliente = empresaComPlano($empresa);
    app(App\Actions\Consumo\RegistrarConsulta::class)($cliente, Servico::firstWhere('codigo', 'scpc-bvs'), 5);

    return app(FecharCompetencia::class)($cliente, Consulta::competenciaDe())['fatura'];
}

it('oferece as acoes de cada linha na tela', function () {
    $fatura = faturaAberta();

    $html = admin()->get(route('financeiro.index'))->assertOk()->getContent();

    expect($html)->toContain(route('financeiro.pdf', $fatura))
        ->toContain(route('financeiro.reenviar', $fatura))
        ->toContain(route('financeiro.liquidar', $fatura))
        ->toContain(route('financeiro.lote'))
        // Cada icone precisa dizer o que faz para quem passa o mouse.
        ->toContain('title="Reenviar a cobrança por e-mail"')
        ->toContain('title="Confirmar pagamento recebido"');
});

it('reenvia a cobranca para o e-mail do cliente, e registra', function () {
    $fatura = faturaAberta();
    Mail::fake();

    admin()->post(route('financeiro.reenviar', $fatura))
        ->assertRedirect()
        ->assertSessionHas('ok');

    Mail::assertSent(FaturaEmitida::class);

    expect(App\Models\Auditoria::where('acao', 'fatura.reenviada')->exists())->toBeTrue()
        ->and(App\Support\Rotulos::acao('fatura.reenviada'))->toBe('Cobrança reenviada por e-mail');
});

it('nao reenvia cobranca de fatura ja paga', function () {
    $fatura = faturaAberta();
    app(App\Actions\Financeiro\RegistrarLiquidacao::class)($fatura);

    // O fake entra depois do preparo: fechar competencia e dar baixa mandam
    // e-mail proprios, e conta-los aqui mediria o preparo, nao a acao.
    Mail::fake();

    admin()->post(route('financeiro.reenviar', $fatura->fresh()))
        ->assertSessionHas('erro');

    Mail::assertNothingSent();
});

it('reenvia em lote e pula quem nao deve receber', function () {
    $aberta = faturaAberta();
    $paga = faturaAberta(['cnpj' => '11222333000181', 'email' => 'outra@teste.com.br']);
    app(App\Actions\Financeiro\RegistrarLiquidacao::class)($paga);

    Mail::fake();

    admin()->post(route('financeiro.lote'), [
        'acao' => 'reenviar',
        'faturas' => [$aberta->id, $paga->id],
    ])->assertRedirect()->assertSessionHas('ok');

    // Fatura paga nao recebe cobranca: seria pedir dinheiro que ja entrou.
    Mail::assertSent(FaturaEmitida::class, 1);
});

it('exporta em lote so o que foi selecionado', function () {
    $uma = faturaAberta();
    faturaAberta(['cnpj' => '11222333000181', 'email' => 'outra@teste.com.br']);

    $resposta = admin()->post(route('financeiro.lote'), [
        'acao' => 'exportar',
        'faturas' => [$uma->id],
    ])->assertOk();

    expect($resposta->headers->get('content-type'))->toContain('spreadsheetml')
        ->and($resposta->headers->get('content-disposition'))->toContain('interno');
});

it('exige selecao e recusa acao desconhecida', function () {
    faturaAberta();

    admin()->post(route('financeiro.lote'), ['acao' => 'reenviar'])
        ->assertSessionHasErrors('faturas');

    admin()->post(route('financeiro.lote'), ['acao' => 'liquidar', 'faturas' => [1]])
        ->assertSessionHasErrors('acao');
});

it('nao existe baixa de pagamento em lote', function () {
    // A regra mais importante deste arquivo. Se um dia alguem acrescentar
    // 'liquidar' as acoes aceitas, este teste cai antes de o dinheiro sumir.
    $fatura = faturaAberta();

    admin()->post(route('financeiro.lote'), ['acao' => 'liquidar', 'faturas' => [$fatura->id]]);

    expect($fatura->fresh()->estaLiquidada())->toBeFalse();
});

it('nao deixa vendedor operar o financeiro', function () {
    $fatura = faturaAberta();
    $vendedor = Staff::factory()->create(['papel' => 'vendedor']);

    comoVendedor($vendedor)->post(route('financeiro.reenviar', $fatura))->assertForbidden();
    comoVendedor($vendedor)->post(route('financeiro.lote'), [
        'acao' => 'exportar', 'faturas' => [$fatura->id],
    ])->assertForbidden();
});
