<?php

use App\Actions\Cobranca\AssinarServico;
use App\Models\Lancamento360;
use App\Models\Oferta360;
use App\Models\Parcela360;
use App\Models\Pedido360;
use App\Models\Produto360;
use App\Models\Produtor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Servico cobrado todo mes
|--------------------------------------------------------------------------
|
| O caso real: o cliente tem R$ 900 descontados do INSS por um banco, e
| contrata por R$ 300 ao mes a defesa que barra o desconto e entra com o
| processo. Nao existe total contratado nem carne: existe uma mensalidade que
| dura enquanto o servico durar.
|
| Dessa venda o dono do negocio leva uma parte e o vendedor leva outra, e as
| duas saem em TODA mensalidade, e nao so na primeira. E isso que o split da
| assinatura resolve: o provedor aplica a mesma divisao em cada cobranca que
| gera.
|
*/

beforeEach(function () {
    config()->set('services.asaas.api_key', 'chave-de-teste');
    config()->set('services.asaas.webhook_token', 'token-de-teste');
});

/** @return array{0: Pedido360, 1: Produtor, 2: Produtor} */
function contratoMensal(int $mensalidadeCents = 30000): array
{
    $dono = Produtor::create([
        'nome' => 'Escritório Dono', 'email' => 'dono@escritorio.com.br',
        'situacao' => 'aprovado', 'asaas_wallet_id' => 'w-dono', 'percentual_bps' => 5000,
    ]);

    $vendedor = Produtor::create([
        'pai_id' => $dono->id, 'nome' => 'Vendedor', 'email' => 'vendedor@escritorio.com.br',
        'situacao' => 'aprovado', 'asaas_wallet_id' => 'w-vendedor', 'percentual_bps' => 2000,
    ]);

    $produto = Produto360::create([
        'produtor_id' => $vendedor->id,
        'nome' => 'Defesa de desconto indevido no INSS',
        'valor_cents' => $mensalidadeCents,
    ]);

    $oferta = Oferta360::create([
        'produto_360_id' => $produto->id,
        'titulo' => 'Defesa de desconto indevido',
        'tipo' => 'mensal',
        'valor_cents' => $mensalidadeCents,
        'parcelas' => 0,
        'entrada_cents' => 0,
        'slug' => 'defesa-inss',
    ]);

    $pedido = Pedido360::create([
        'oferta_360_id' => $oferta->id,
        'produtor_id' => $vendedor->id,
        'cliente_nome' => 'José Aposentado',
        'cliente_documento' => '12345678909',
        'cliente_email' => 'jose@email.com.br',
        'cliente_telefone' => '34999887766',
        'valor_total_cents' => $mensalidadeCents,
        'entrada_cents' => 0,
        'parcelas' => 0,
        'valor_parcela_cents' => $mensalidadeCents,
        'taxa_bps' => 0,
        'melhor_dia' => 10,
        'contrato_assinado_em' => now(),
    ]);

    return [$pedido, $dono, $vendedor];
}

it('assina o serviço mensal com o split da rede inteira', function () {
    Http::fake([
        '*/customers' => Http::response(['id' => 'cus_1']),
        '*/subscriptions' => Http::response(['id' => 'sub_1', 'status' => 'ACTIVE']),
    ]);

    [$pedido] = contratoMensal();
    $assinado = app(AssinarServico::class)($pedido);

    expect($assinado->asaas_subscription_id)->toBe('sub_1')
        ->and($assinado->situacao)->toBe('efetivado');

    Http::assertSent(function ($r) {
        if (! str_contains($r->url(), '/subscriptions')) {
            return false;
        }

        $corpo = $r->data();

        return $corpo['cycle'] === 'MONTHLY'
            && $corpo['value'] === 300.0
            // A divisao vai na assinatura, entao vale em toda cobranca que ela
            // gerar, e nao so na primeira.
            && $corpo['split'] === [
                ['walletId' => 'w-vendedor', 'percentualValue' => 20.0],
                ['walletId' => 'w-dono', 'percentualValue' => 50.0],
            ];
    });
});

it('nao cria uma segunda assinatura para o mesmo contrato', function () {
    Http::fake([
        '*/customers' => Http::response(['id' => 'cus_1']),
        '*/subscriptions' => Http::response(['id' => 'sub_1']),
    ]);

    [$pedido] = contratoMensal();
    app(AssinarServico::class)($pedido);
    app(AssinarServico::class)($pedido->fresh());

    Http::assertSentCount(2);
});

it('recusa carne em oferta mensal', function () {
    // Sao caminhos diferentes: mensalidade nao tem entrada nem total a dividir.
    [$pedido] = contratoMensal();
    $pedido->oferta->update(['tipo' => 'parcelada']);

    expect(fn () => app(AssinarServico::class)($pedido->fresh()))
        ->toThrow(RuntimeException::class, 'oferta é parcelada');
});

it('registra a mensalidade que o provedor gerou sozinho e paga a rede', function () {
    // A cobranca de cada mes nasce no provedor quando o mes vira. A primeira
    // noticia que temos dela e o webhook: sem registrar, o pagamento seria
    // ignorado e o painel diria que nada foi pago.
    Http::fake([
        '*/customers' => Http::response(['id' => 'cus_1']),
        '*/subscriptions' => Http::response(['id' => 'sub_1']),
    ]);

    [$pedido, $dono, $vendedor] = contratoMensal();
    app(AssinarServico::class)($pedido);

    $this->withHeader('asaas-access-token', 'token-de-teste')
        ->postJson(route('webhooks.asaas'), [
            'id' => 'evt_marco',
            'event' => 'PAYMENT_RECEIVED',
            'payment' => [
                'id' => 'pay_marco',
                'subscription' => 'sub_1',
                'status' => 'RECEIVED',
                'dueDate' => '2026-03-10',
                'value' => 300.00,
                'netValue' => 296.51,
                'paymentDate' => '2026-03-09',
            ],
        ])->assertOk();

    $marco = Parcela360::where('competencia', '2026-03')->sole();

    expect($marco->situacao)->toBe('paga')
        ->and($marco->valor_cents)->toBe(30000);

    $repasses = Lancamento360::where('tipo', 'repasse')->get();

    // 20% e 50% dos 29.651 que sobraram depois da taxa do provedor.
    expect($repasses)->toHaveCount(2)
        ->and($repasses->firstWhere('beneficiario_id', $vendedor->id)->valor_cents)->toBe(-5930)
        ->and($repasses->firstWhere('beneficiario_id', $dono->id)->valor_cents)->toBe(-14825)
        ->and(Lancamento360::sum('valor_cents'))->toBe(0);
});

it('nao duplica a mensalidade quando o webhook reentrega', function () {
    Http::fake([
        '*/customers' => Http::response(['id' => 'cus_1']),
        '*/subscriptions' => Http::response(['id' => 'sub_1']),
    ]);

    [$pedido] = contratoMensal();
    app(AssinarServico::class)($pedido);

    foreach (['evt_1', 'evt_2'] as $id) {
        $this->withHeader('asaas-access-token', 'token-de-teste')
            ->postJson(route('webhooks.asaas'), [
                'id' => $id,
                'event' => 'PAYMENT_RECEIVED',
                'payment' => [
                    'id' => 'pay_marco', 'subscription' => 'sub_1', 'status' => 'RECEIVED',
                    'dueDate' => '2026-03-10', 'value' => 300.00, 'netValue' => 296.51,
                ],
            ])->assertOk();
    }

    expect(Parcela360::where('competencia', '2026-03')->count())->toBe(1)
        ->and(Lancamento360::where('tipo', 'bruto')->count())->toBe(1);
});
