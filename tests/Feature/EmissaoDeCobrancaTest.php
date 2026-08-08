<?php

use App\Actions\Consumo\FecharCompetencia;
use App\Models\CobrancaAsaas;
use App\Models\Consulta;
use App\Models\Servico;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * A emissao da cobranca no provedor.
 *
 * Estes testes nascem de um defeito real em producao: o id do cliente no
 * provedor era gravado com update(), o campo nao estava em fillable, e o
 * Eloquent o descartava em silencio. A cobranca saia sem cliente, o provedor
 * recusava com 400, e cada tentativa criava mais um cadastro orfao la dentro.
 * O log so dizia "status code 400", porque a resposta era descartada.
 *
 * Entao e isso que se protege: o id volta gravado, a cobranca sai com cliente,
 * a recusa do provedor chega em portugues, e reemitir nao vira segunda cobranca.
 */
function asaasFalso(array $respostas = []): void
{
    config()->set('services.asaas.api_key', 'chave-de-teste');
    config()->set('services.asaas.base_url', 'https://api-sandbox.asaas.com/v3');

    // Sem isto, um padrao que nao casa vira requisicao de verdade e o teste
    // passa a depender da internet. Foi assim que este arquivo quebrou antes.
    Http::preventStrayRequests();

    Http::fake($respostas + [
        '*/customers' => Http::response(['id' => 'cus_000123']),
        '*/payments' => Http::response([
            'id' => 'pay_000456',
            'status' => 'PENDING',
            'invoiceUrl' => 'https://sandbox.asaas.com/i/000456',
            'bankSlipUrl' => 'https://sandbox.asaas.com/b/pdf/000456',
        ]),
    ]);
}

/** Fatura fechada pelo caminho de producao, que ja tenta emitir a cobranca. */
function faturaParaEmitir(): App\Models\Fatura
{
    $empresa = empresaComPlano();
    app(App\Actions\Consumo\RegistrarConsulta::class)($empresa, Servico::firstWhere('codigo', 'scpc-bvs'), 8);

    return app(FecharCompetencia::class)($empresa, Consulta::competenciaDe())['fatura'];
}

it('grava o cliente do provedor e manda a cobranca com ele', function () {
    asaasFalso();
    $fatura = faturaParaEmitir();

    // O id do provedor tem que sobreviver ao save. Era exatamente aqui que o
    // defeito morava, e nenhum teste chegava a olhar.
    expect($fatura->cliente->fresh()->asaas_customer_id)->toBe('cus_000123');

    $cobranca = CobrancaAsaas::where('fatura_id', $fatura->id)->sole();

    expect($cobranca->asaas_charge_id)->toBe('pay_000456')
        ->and($cobranca->invoice_url)->toBe('https://sandbox.asaas.com/i/000456')
        ->and($cobranca->bank_slip_url)->toBe('https://sandbox.asaas.com/b/pdf/000456');

    // E a cobranca precisa sair COM o cliente: sem isso o provedor recusa.
    Http::assertSent(fn ($r) => ! str_ends_with($r->url(), '/payments')
        || $r['customer'] === 'cus_000123');
});

it('traduz a recusa do provedor em vez de engolir o motivo', function () {
    asaasFalso([
        '*/payments' => Http::response([
            'errors' => [['code' => 'invalid_customer', 'description' => 'O cliente informado não existe.']],
        ], 400),
    ]);

    // O fechamento engole a falha de proposito. O botao do financeiro e que
    // precisa dizer o que houve.
    $fatura = faturaParaEmitir();

    admin()->post(route('financeiro.cobranca', $fatura))
        ->assertRedirect()
        ->assertSessionHas('erro', fn ($erro) => str_contains($erro, 'O cliente informado não existe.'));
});

it('reemitir renova os enderecos e nao cria segunda cobranca', function () {
    asaasFalso([
        '*/payments/pay_000456' => Http::response([
            'id' => 'pay_000456',
            'status' => 'PENDING',
            'invoiceUrl' => 'https://sandbox.asaas.com/i/renovado',
            'bankSlipUrl' => 'https://sandbox.asaas.com/b/pdf/renovado',
        ]),
    ]);

    $fatura = faturaParaEmitir();
    $cobranca = app(App\Actions\Financeiro\CriarCobrancaAsaas::class)($fatura->fresh());

    // Cobrar duas vezes a mesma competencia e o pior erro possivel aqui.
    expect($cobranca->asaas_charge_id)->toBe('pay_000456')
        ->and($cobranca->invoice_url)->toBe('https://sandbox.asaas.com/i/renovado')
        ->and(CobrancaAsaas::where('fatura_id', $fatura->id)->count())->toBe(1);
});

it('deixa a empresa pedir a via de pagamento sozinha', function () {
    asaasFalso([
        '*/payments/pay_000456' => Http::response([
            'id' => 'pay_000456',
            'status' => 'PENDING',
            'invoiceUrl' => 'https://sandbox.asaas.com/i/000456',
        ]),
    ]);

    $fatura = faturaParaEmitir();

    comoEmpresa($fatura->cliente)->post(route('empresa.faturas.boleto', $fatura))
        ->assertRedirect('https://sandbox.asaas.com/i/000456');
});

it('nao deixa uma empresa pedir a via de outra', function () {
    asaasFalso();
    $fatura = faturaParaEmitir();
    $outra = empresaComPlano(['cnpj' => '11222333000181', 'email' => 'outra@teste.com.br']);

    comoEmpresa($outra)->post(route('empresa.faturas.boleto', $fatura))->assertForbidden();
});

it('oferece a via de pagamento na tela de faturas do cliente', function () {
    asaasFalso();
    $fatura = faturaParaEmitir();

    comoEmpresa($fatura->cliente)->get(route('empresa.faturas'))
        ->assertOk()
        ->assertSee(route('empresa.faturas.boleto', $fatura), false)
        ->assertSee('Boleto em PDF')
        // A frase que mandava o cliente ligar para o atendimento sai de cena.
        ->assertDontSee('Solicite a segunda via ao atendimento');
});
