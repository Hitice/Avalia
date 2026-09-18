<?php

use App\Actions\Cobranca\CriarSubcontaDoProdutor;
use App\Actions\Cobranca\EmitirCobrancaDaParcela;
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
| O dinheiro do Avalia 360
|--------------------------------------------------------------------------
|
| O provedor e falso aqui de proposito: teste que fala com o Asaas de verdade
| cria cobranca de verdade, e ninguem lembra de apagar.
|
| O que estes testes guardam: o split aponta para a carteira certa, o razao
| fecha em zero, e webhook reentregue nao paga a mesma parcela duas vezes.
|
*/

beforeEach(function () {
    config()->set('services.asaas.api_key', 'chave-de-teste');
    config()->set('services.asaas.webhook_token', 'token-de-teste');
});

function produtorPronto(): Produtor
{
    return Produtor::create([
        'nome' => 'Escola Costa',
        'documento' => '12345678909',
        'whatsapp' => '34999112233',
        'email' => 'costa@escola.com.br',
        'situacao' => 'aprovado',
        'asaas_wallet_id' => 'wallet-do-produtor',
        // 95% para quem vendeu; os 5% que sobram ficam com a casa.
        'percentual_bps' => 9500,
        'aprovado_em' => now(),
    ]);
}

function pedidoComEntrada(Produtor $produtor, int $taxaBps = 500): Pedido360
{
    $produto = Produto360::create([
        'produtor_id' => $produtor->id,
        'nome' => 'Curso de Marcenaria',
        'valor_cents' => 300000,
    ]);

    $oferta = Oferta360::create([
        'produto_360_id' => $produto->id,
        'titulo' => 'Marcenaria em 12x',
        'valor_cents' => 300000,
        'parcelas' => 12,
        'entrada_cents' => 30000,
        'entrada_em_dias' => 7,
    ]);

    $pedido = Pedido360::create([
        'oferta_360_id' => $oferta->id,
        'produtor_id' => $produtor->id,
        'cliente_nome' => 'Marina Costa',
        'cliente_documento' => '12345678909',
        'cliente_email' => 'marina@aluna.com.br',
        'cliente_telefone' => '34999887766',
        'valor_total_cents' => 300000,
        'entrada_cents' => 30000,
        'parcelas' => 12,
        'valor_parcela_cents' => $oferta->valorDaParcela(),
        'taxa_bps' => $taxaBps,
    ]);

    Parcela360::create([
        'pedido_360_id' => $pedido->id,
        'numero' => 0,
        'valor_cents' => 30000,
        'vencimento' => today()->addDays(7),
    ]);

    return $pedido->fresh();
}

/*
|--------------------------------------------------------------------------
| Subconta do produtor
|--------------------------------------------------------------------------
*/

it('abre a subconta do produtor e guarda a carteira', function () {
    Http::fake(['*/accounts' => Http::response([
        'id' => 'acc_123', 'walletId' => 'wallet_abc', 'apiKey' => '$aact_sub_xyz',
    ])]);

    $produtor = Produtor::create([
        'nome' => 'Escola Costa',
        'documento' => '12345678909',
        'whatsapp' => '34999112233',
        'email' => 'costa@escola.com.br',
    ]);

    $pronto = app(CriarSubcontaDoProdutor::class)($produtor, ['cep' => '38400192', 'logradouro' => 'Av Principal', 'numero' => '10']);

    expect($pronto->asaas_wallet_id)->toBe('wallet_abc')
        ->and($pronto->asaas_api_key)->toBe('$aact_sub_xyz')
        ->and($pronto->situacao)->toBe('aprovado')
        ->and($pronto->podeVender())->toBeTrue();

    // A chave da subconta e credencial: cifrada no banco como todas as outras.
    expect(DB::table('produtores')->sole()->asaas_api_key)->not->toContain('aact_sub_xyz');
});

it('nao abre uma segunda subconta para quem ja tem', function () {
    // Subconta duplicada divide o historico do produtor e manda o split para a
    // carteira errada, sem erro nenhum aparecer.
    Http::fake();
    $produtor = produtorPronto();

    app(CriarSubcontaDoProdutor::class)($produtor, []);

    Http::assertNothingSent();
});

/*
|--------------------------------------------------------------------------
| Cobranca com split
|--------------------------------------------------------------------------
*/

it('emite a cobranca com o split apontando para a carteira do produtor', function () {
    Http::fake([
        '*/customers' => Http::response(['id' => 'cus_1']),
        '*/payments' => Http::response(['id' => 'pay_1', 'status' => 'PENDING', 'invoiceUrl' => 'https://provedor/pay_1']),
    ]);

    $pedido = pedidoComEntrada(produtorPronto());
    $cobranca = app(EmitirCobrancaDaParcela::class)($pedido->entrada());

    expect($cobranca->asaas_charge_id)->toBe('pay_1')
        ->and($cobranca->valor_cents)->toBe(30000);

    Http::assertSent(function ($pedidoHttp) {
        if (! str_contains($pedidoHttp->url(), '/payments')) {
            return false;
        }

        $corpo = $pedidoHttp->data();

        // O split vem da linha de comissao, em percentual e nao em valor: se
        // a cobranca mudar de valor, a divisao continua certa.
        return $corpo['split'][0]['walletId'] === 'wallet-do-produtor'
            && $corpo['split'][0]['percentualValue'] === 95.0
            && $corpo['value'] === 300.0;
    });
});

it('nao emite dois boletos para a mesma parcela', function () {
    Http::fake([
        '*/customers' => Http::response(['id' => 'cus_1']),
        '*/payments' => Http::response(['id' => 'pay_1', 'status' => 'PENDING']),
    ]);

    $pedido = pedidoComEntrada(produtorPronto());
    $primeira = app(EmitirCobrancaDaParcela::class)($pedido->entrada());
    $segunda = app(EmitirCobrancaDaParcela::class)($pedido->entrada()->fresh());

    expect($segunda->id)->toBe($primeira->id)
        ->and(App\Models\CobrancaAsaas::count())->toBe(1);
});

it('recusa emitir para produtor sem carteira', function () {
    $produtor = Produtor::create([
        'nome' => 'Sem Carteira', 'documento' => '12345678909',
        'whatsapp' => '34999112233', 'email' => 'sem@carteira.com.br',
    ]);

    expect(fn () => app(EmitirCobrancaDaParcela::class)(pedidoComEntrada($produtor)->entrada()))
        ->toThrow(RuntimeException::class, 'não há para onde repassar');
});

/*
|--------------------------------------------------------------------------
| Webhook e razao
|--------------------------------------------------------------------------
*/

function avisaPagamento(string $evento, Parcela360 $parcela, array $extra = []): \Illuminate\Testing\TestResponse
{
    return test()->withHeader('asaas-access-token', 'token-de-teste')
        ->postJson(route('webhooks.asaas'), [
            'id' => $extra['evento_id'] ?? 'evt_'.uniqid(),
            'event' => $evento,
            'payment' => array_merge([
                'id' => $parcela->cobranca->asaas_charge_id,
                'status' => 'RECEIVED',
                'value' => $parcela->valor_cents / 100,
                'netValue' => ($parcela->valor_cents - 349) / 100,
                'paymentDate' => '2026-09-17',
            ], $extra['pagamento'] ?? []),
        ]);
}

it('da baixa na parcela e escreve o razao fechando em zero', function () {
    Http::fake([
        '*/customers' => Http::response(['id' => 'cus_1']),
        '*/payments' => Http::response(['id' => 'pay_1', 'status' => 'PENDING']),
    ]);

    $pedido = pedidoComEntrada(produtorPronto());
    app(EmitirCobrancaDaParcela::class)($pedido->entrada());

    avisaPagamento('PAYMENT_RECEIVED', $pedido->entrada()->fresh())->assertOk();

    $entrada = $pedido->entrada()->fresh();
    expect($entrada->situacao)->toBe('paga')
        ->and($entrada->paga_em)->not->toBeNull();

    $lancamentos = Lancamento360::where('parcela_360_id', $entrada->id)->get();

    expect($lancamentos)->toHaveCount(4)
        // A invariante do razao: o pedido fecha em zero quando tudo foi
        // distribuido entre provedor, plataforma e produtor.
        ->and($lancamentos->sum('valor_cents'))->toBe(0)
        ->and($lancamentos->firstWhere('tipo', 'bruto')->valor_cents)->toBe(30000)
        ->and($lancamentos->firstWhere('tipo', 'taxa_provedor')->valor_cents)->toBe(-349)
        // 95% dos 29.651 que sobraram depois da taxa do provedor, que e como
        // o split do provedor calcula.
        ->and($lancamentos->firstWhere('tipo', 'repasse')->valor_cents)->toBe(-28168)
        ->and($lancamentos->firstWhere('tipo', 'taxa_plataforma')->valor_cents)->toBe(-1483);
});

it('nao paga a mesma parcela duas vezes quando o webhook reentrega', function () {
    // O provedor reenvia o mesmo pagamento como CONFIRMED e depois RECEIVED,
    // com ids de evento diferentes: quem protege e a parcela, nao o evento.
    Http::fake([
        '*/customers' => Http::response(['id' => 'cus_1']),
        '*/payments' => Http::response(['id' => 'pay_1', 'status' => 'PENDING']),
    ]);

    $pedido = pedidoComEntrada(produtorPronto());
    app(EmitirCobrancaDaParcela::class)($pedido->entrada());

    avisaPagamento('PAYMENT_CONFIRMED', $pedido->entrada()->fresh())->assertOk();
    avisaPagamento('PAYMENT_RECEIVED', $pedido->entrada()->fresh())->assertOk();

    expect(Lancamento360::where('tipo', 'bruto')->count())->toBe(1)
        ->and(Lancamento360::sum('valor_cents'))->toBe(0);
});

it('marca vencida e deixa o pedido inadimplente', function () {
    Http::fake([
        '*/customers' => Http::response(['id' => 'cus_1']),
        '*/payments' => Http::response(['id' => 'pay_1', 'status' => 'PENDING']),
    ]);

    $pedido = pedidoComEntrada(produtorPronto());
    app(EmitirCobrancaDaParcela::class)($pedido->entrada());

    avisaPagamento('PAYMENT_OVERDUE', $pedido->entrada()->fresh())->assertOk();

    expect($pedido->entrada()->fresh()->situacao)->toBe('vencida')
        ->and($pedido->fresh()->situacao_financeira)->toBe('inadimplente');
});

/*
|--------------------------------------------------------------------------
| A porta que separa venda de carne
|--------------------------------------------------------------------------
*/

it('so parcela depois de contrato assinado e entrada paga', function () {
    $pedido = pedidoComEntrada(produtorPronto());

    expect($pedido->podeParcelar())->toBeFalse();

    $pedido->update(['contrato_assinado_em' => now()]);
    expect($pedido->fresh()->podeParcelar())->toBeFalse();

    $pedido->entrada()->update(['situacao' => 'paga', 'paga_em' => now()]);
    expect($pedido->fresh()->podeParcelar())->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| A rede
|--------------------------------------------------------------------------
|
| Uma venda paga quem vendeu, quem o trouxe e o topo, na mesma cobranca. O
| provedor divide no momento do pagamento, entao o dinheiro nunca passa pela
| conta da casa.
|
*/

it('divide a venda entre a linha inteira, do vendedor ao topo', function () {
    Http::fake([
        '*/customers' => Http::response(['id' => 'cus_1']),
        '*/payments' => Http::response(['id' => 'pay_1', 'status' => 'PENDING']),
    ]);

    $topo = Produtor::create([
        'nome' => 'Topo', 'email' => 'topo@rede.com.br', 'situacao' => 'aprovado',
        'asaas_wallet_id' => 'w-topo', 'percentual_bps' => 5000,
    ]);

    $gerente = Produtor::create([
        'pai_id' => $topo->id, 'nome' => 'Gerente', 'email' => 'gerente@rede.com.br',
        'situacao' => 'aprovado', 'asaas_wallet_id' => 'w-gerente', 'percentual_bps' => 1000,
    ]);

    $vendedor = Produtor::create([
        'pai_id' => $gerente->id, 'nome' => 'Vendedor', 'email' => 'vendedor@rede.com.br',
        'situacao' => 'aprovado', 'asaas_wallet_id' => 'w-vendedor', 'percentual_bps' => 3000,
    ]);

    $pedido = pedidoComEntrada($vendedor);
    app(EmitirCobrancaDaParcela::class)($pedido->entrada());

    // A cobranca sai com os tres beneficiarios, na ordem em que a linha sobe.
    Http::assertSent(function ($r) {
        if (! str_contains($r->url(), '/payments')) {
            return false;
        }

        return $r->data()['split'] === [
            ['walletId' => 'w-vendedor', 'percentualValue' => 30.0],
            ['walletId' => 'w-gerente', 'percentualValue' => 10.0],
            ['walletId' => 'w-topo', 'percentualValue' => 50.0],
        ];
    });

    avisaPagamento('PAYMENT_RECEIVED', $pedido->entrada()->fresh())->assertOk();

    $repasses = Lancamento360::where('tipo', 'repasse')->get();

    // Um lancamento por parceiro: extrato agregado ninguem consegue conferir.
    expect($repasses)->toHaveCount(3)
        ->and($repasses->firstWhere('beneficiario_id', $vendedor->id)->valor_cents)->toBe(-8895)
        ->and($repasses->firstWhere('beneficiario_id', $gerente->id)->valor_cents)->toBe(-2965)
        ->and($repasses->firstWhere('beneficiario_id', $topo->id)->valor_cents)->toBe(-14825)
        // E o razao continua fechando em zero com a rede inteira dentro.
        ->and(Lancamento360::sum('valor_cents'))->toBe(0);
});

it('recusa a venda quando a linha de comissao passa de cem por cento', function () {
    // O provedor recusaria a cobranca inteira. Falhar aqui, com o nome do
    // problema, poupa a investigacao de um erro generico dele.
    //
    // O fake registra qualquer chamada: o teste tambem prova que NENHUMA
    // acontece, porque a conta e conferida antes de falar com o provedor.
    Http::fake();
    $topo = Produtor::create([
        'nome' => 'Topo', 'email' => 'topo@rede.com.br', 'situacao' => 'aprovado',
        'asaas_wallet_id' => 'w-topo', 'percentual_bps' => 6000,
    ]);

    $vendedor = Produtor::create([
        'pai_id' => $topo->id, 'nome' => 'Vendedor', 'email' => 'vendedor@rede.com.br',
        'situacao' => 'aprovado', 'asaas_wallet_id' => 'w-vendedor', 'percentual_bps' => 5000,
    ]);

    expect(fn () => app(EmitirCobrancaDaParcela::class)(pedidoComEntrada($vendedor)->entrada()))
        ->toThrow(RuntimeException::class, 'acima de 100%');

    // Nenhum cliente orfao criado la por causa de um erro de cadastro daqui.
    Http::assertNothingSent();
});
