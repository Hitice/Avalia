<?php

use App\Models\Lancamento360;
use App\Models\Oferta360;
use App\Models\Parcela360;
use App\Models\Pedido360;
use App\Models\Produto360;
use App\Models\Produtor;
use App\Support\AnaliseDeCredito;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Do link de checkout ate o carne
|--------------------------------------------------------------------------
|
| O que estes testes guardam: proposta recusada nao gera boleto, boleto nao sai
| antes do aceite, e o carne so nasce com entrada paga. Sao as tres portas que,
| abertas na ordem errada, produzem cobranca sem contrato.
|
*/

beforeEach(function () {
    config()->set('services.asaas.api_key', 'chave-de-teste');
    config()->set('services.asaas.webhook_token', 'token-de-teste');

    Http::fake([
        '*/customers' => Http::response(['id' => 'cus_1']),
        '*/payments' => Http::response(['id' => 'pay_1', 'status' => 'PENDING', 'invoiceUrl' => 'https://provedor/pay_1']),
    ]);
});

function ofertaPublicada(array $ajustes = []): Oferta360
{
    $produtor = Produtor::create([
        'nome' => 'Escola Costa',
        'documento' => '12345678909',
        'whatsapp' => '34999112233',
        'email' => 'costa@escola.com.br',
        'situacao' => 'aprovado',
        'asaas_wallet_id' => 'wallet-do-produtor',
    ]);

    $produto = Produto360::create([
        'produtor_id' => $produtor->id,
        'nome' => 'Curso de Marcenaria',
        'valor_cents' => $ajustes['valor_cents'] ?? 300000,
    ]);

    return Oferta360::create(array_merge([
        'produto_360_id' => $produto->id,
        'titulo' => 'Marcenaria em 12x',
        'valor_cents' => 300000,
        'parcelas' => 12,
        'entrada_cents' => 30000,
        'entrada_em_dias' => 7,
        'slug' => 'marcenaria',
    ], $ajustes));
}

function compra(array $ajustes = []): array
{
    return array_merge([
        'nome' => 'Marina Costa',
        'documento' => '123.456.789-09',
        'email' => 'marina@aluna.com.br',
        'telefone' => '34999887766',
        'nascimento' => '1995-04-10',
        'cep' => '38400-192',
        'logradouro' => 'Av Principal',
        'numero' => '100',
        'bairro' => 'Centro',
        'cidade' => 'Uberlândia',
        'uf' => 'MG',
        'melhor_dia' => 10,
        'aceite' => '1',
    ], $ajustes);
}

it('mostra a oferta no link publico', function () {
    $oferta = ofertaPublicada();

    $this->get(route('checkout', $oferta->slug))->assertOk()
        ->assertSee('Marcenaria em 12x')
        // Pelo helper, e nao pela string escrita a mao: `Dinheiro::brl` usa
        // espaco nao separavel para o valor nunca quebrar de linha, e um
        // espaco comum aqui reprova codigo que esta certo.
        ->assertSee(App\Support\Dinheiro::brl(300000))
        ->assertSee('Escola Costa');
});

it('esconde oferta de produtor que ainda nao pode receber', function () {
    // Sem carteira no provedor nao ha para onde repassar: deixar o checkout
    // aberto so produziria venda com dinheiro parado na conta errada.
    $oferta = ofertaPublicada();
    $oferta->produto->produtor->update(['asaas_wallet_id' => null]);

    $this->get(route('checkout', $oferta->slug))->assertNotFound();
});

it('fecha a compra, aceita o contrato e emite a entrada', function () {
    $oferta = ofertaPublicada();

    $this->post(route('checkout.fechar', $oferta->slug), compra())
        ->assertRedirect(route('checkout.resultado', Pedido360::sole()->id));

    $pedido = Pedido360::sole();

    expect($pedido->situacao)->toBe('aguardando_entrada')
        ->and($pedido->contrato_assinado_em)->not->toBeNull()
        ->and($pedido->analise_versao)->toBe(config('cobranca.analise.versao'))
        ->and($pedido->taxa_bps)->toBe(500)
        ->and($pedido->melhor_dia)->toBe(10);

    // A entrada existe como parcela zero, com boleto emitido.
    $entrada = $pedido->entrada();
    expect($entrada->valor_cents)->toBe(30000)
        ->and($entrada->cobranca->invoice_url)->toBe('https://provedor/pay_1');

    // E o carne ainda nao existe: entrada nao foi paga.
    expect($pedido->parcelas()->where('numero', '>', 0)->count())->toBe(0);
});

it('nao emite boleto para proposta recusada', function () {
    // Menor de idade nao assina contrato de credito, e contrato assinado por
    // menor e contrato que nao se cobra.
    $oferta = ofertaPublicada();

    $this->post(route('checkout.fechar', $oferta->slug), compra(['nascimento' => today()->subYears(15)->format('Y-m-d')]));

    $pedido = Pedido360::sole();

    expect($pedido->situacao)->toBe('reprovado')
        ->and($pedido->analise_motivo)->toContain('Idade mínima')
        ->and($pedido->parcelas()->count())->toBe(0);

    Http::assertNotSent(fn ($r) => str_contains($r->url(), '/payments'));
});

it('recusa segunda compra com o mesmo documento em aberto', function () {
    $oferta = ofertaPublicada();

    $this->post(route('checkout.fechar', $oferta->slug), compra());
    $this->post(route('checkout.fechar', $oferta->slug), compra(['email' => 'outro@email.com.br']));

    $segundo = Pedido360::orderByDesc('id')->first();

    expect($segundo->situacao)->toBe('reprovado')
        ->and($segundo->analise_motivo)->toContain('em aberto');
});

it('exige o aceite para fechar', function () {
    $oferta = ofertaPublicada();
    $dados = compra();
    unset($dados['aceite']);

    $this->post(route('checkout.fechar', $oferta->slug), $dados)->assertSessionHasErrors('aceite');

    expect(Pedido360::count())->toBe(0);
});

it('reduz o parcelamento quando a parcela fica abaixo do piso', function () {
    // R$ 600 em 12x dariam parcelas de R$ 47,50, que custam mais em cobranca
    // do que trazem. O teto cai em vez de a venda ser recusada.
    $oferta = ofertaPublicada(['valor_cents' => 60000, 'entrada_cents' => 3000, 'slug' => 'curto']);

    $this->post(route('checkout.fechar', $oferta->slug), compra());
    $pedido = Pedido360::sole();

    expect($pedido->situacao)->toBe('aguardando_entrada')
        ->and($pedido->parcelas)->toBe(5)
        ->and($pedido->valor_parcela_cents)->toBe(11400);
});

it('gera o carne quando a entrada e paga, com a sobra na primeira parcela', function () {
    $oferta = ofertaPublicada(['valor_cents' => 100000, 'entrada_cents' => 10000, 'parcelas' => 7, 'slug' => 'sobra']);

    $this->post(route('checkout.fechar', $oferta->slug), compra());
    $pedido = Pedido360::sole();

    $this->withHeader('asaas-access-token', 'token-de-teste')
        ->postJson(route('webhooks.asaas'), [
            'id' => 'evt_1',
            'event' => 'PAYMENT_RECEIVED',
            'payment' => [
                'id' => 'pay_1', 'status' => 'RECEIVED',
                'value' => 100.00, 'netValue' => 96.51, 'paymentDate' => '2026-09-17',
            ],
        ])->assertOk();

    $pedido = $pedido->fresh();
    $carne = $pedido->parcelas()->where('numero', '>', 0)->orderBy('numero')->get();

    expect($pedido->situacao)->toBe('efetivado')
        ->and($carne)->toHaveCount(7)
        // 90.000 em 7 nao e inteiro: 12.857 cada, e a sobra de 1 vai na
        // primeira, que e a que o cliente confere.
        ->and($carne->first()->valor_cents)->toBe(12858)
        ->and($carne->last()->valor_cents)->toBe(12857)
        ->and($carne->sum('valor_cents'))->toBe(90000)
        // Todas caem no melhor dia escolhido.
        ->and($carne->pluck('vencimento')->every(fn ($d) => $d->day === 10))->toBeTrue();

    // E o razao da entrada fecha em zero.
    expect(Lancamento360::sum('valor_cents'))->toBe(0);
});

it('nao gera o carne duas vezes quando o webhook reentrega', function () {
    $oferta = ofertaPublicada();
    $this->post(route('checkout.fechar', $oferta->slug), compra());

    foreach (['evt_1', 'evt_2'] as $id) {
        $this->withHeader('asaas-access-token', 'token-de-teste')
            ->postJson(route('webhooks.asaas'), [
                'id' => $id,
                'event' => 'PAYMENT_RECEIVED',
                'payment' => ['id' => 'pay_1', 'status' => 'RECEIVED', 'value' => 300.00, 'netValue' => 296.51],
            ])->assertOk();
    }

    expect(Parcela360::where('numero', '>', 0)->count())->toBe(12);
});

it('calcula a entrada minima pela regra configurada', function () {
    expect(AnaliseDeCredito::entradaMinima(300000))->toBe(30000)
        ->and(AnaliseDeCredito::entradaMinima(99))->toBe(9);
});
