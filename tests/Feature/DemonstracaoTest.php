<?php

use App\Models\Consulta;
use App\Models\Servico;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * A consulta de demonstracao do vendedor.
 *
 * O que se protege: ninguem e cobrado (preco zero), o custo real fica
 * congelado e sai da comissao do vendedor (regra do negocio), o teto diario e
 * proprio, e o resultado so sai da tela em PDF, nunca em URL.
 */
const DOC_DEMO_OK = '12345678901';

it('consulta para demonstrar, com preco zero e custo congelado', function () {
    [$vendedor] = carteira();
    $servico = Servico::firstWhere('codigo', 'scpc-bvs');

    comoVendedor($vendedor)->post(route('carteira.consultar.executar'), [
        'servico_id' => $servico->id,
        'documento' => DOC_DEMO_OK,
    ])->assertRedirect();

    $consulta = Consulta::sole();

    expect($consulta->vendedor_id)->toBe($vendedor->id)
        ->and($consulta->cliente_id)->toBeNull()
        ->and($consulta->preco_cents)->toBe(0)
        ->and($consulta->custo_cents)->toBe(150)
        ->and($consulta->solicitante)->toBe($vendedor->nome);
});

it('desconta o custo das demonstracoes da comissao a receber', function () {
    [$vendedor, $empresa] = carteira();
    $servico = Servico::firstWhere('codigo', 'scpc-bvs');

    // Uma fatura liquidada libera comissao; duas demonstracoes descontam
    // 2 x R$ 1,50 dela.
    app(App\Actions\Consumo\RegistrarConsulta::class)($empresa, $servico, 10);
    $fatura = app(App\Actions\Consumo\FecharCompetencia::class)($empresa, Consulta::competenciaDe())['fatura'];
    app(App\Actions\Financeiro\RegistrarLiquidacao::class)($fatura);

    comoVendedor($vendedor)->post(route('carteira.consultar.executar'), [
        'servico_id' => $servico->id, 'documento' => '11111111111',
    ]);
    comoVendedor($vendedor)->post(route('carteira.consultar.executar'), [
        'servico_id' => $servico->id, 'documento' => '22222222222',
    ]);

    $resposta = comoVendedor($vendedor)->get(route('carteira'))->assertOk();

    expect($resposta->viewData('demonstracoes'))->toBe(300)
        ->and($resposta->viewData('aReceber'))->toBe($fatura->comissao_cents - 300);
});

it('mostra a mesma comissao no painel do admin e na carteira do vendedor', function () {
    // Duas telas com numeros diferentes para o mesmo repasse e erro de
    // pagamento esperando acontecer: o painel mostrava o bruto e a carteira
    // ja mostrava o liquido das demonstracoes.
    [$vendedor, $empresa] = carteira();
    $servico = Servico::firstWhere('codigo', 'scpc-bvs');

    app(App\Actions\Consumo\RegistrarConsulta::class)($empresa, $servico, 10);
    $fatura = app(App\Actions\Consumo\FecharCompetencia::class)($empresa, Consulta::competenciaDe())['fatura'];
    app(App\Actions\Financeiro\RegistrarLiquidacao::class)($fatura);

    comoVendedor($vendedor)->post(route('carteira.consultar.executar'), [
        'servico_id' => $servico->id, 'documento' => DOC_DEMO_OK,
    ]);

    $naCarteira = comoVendedor($vendedor)->get(route('carteira'))->assertOk()->viewData('aReceber');
    $noPainel = admin()->get(route('painel'))->assertOk()->viewData('comissaoPorVendedor')
        ->firstWhere('nome', $vendedor->nome);

    expect($noPainel['cents'])->toBe($naCarteira)
        ->and($noPainel['demonstracoes'])->toBe(150)
        ->and($naCarteira)->toBe($fatura->comissao_cents - 150);
});

it('avisa no painel quando as rotinas automaticas nao rodam', function () {
    // O cron falha calado. Sem batida, o painel diz que nunca rodou.
    $batida = storage_path('logs/cron-batida.txt');
    @unlink($batida);

    admin()->get(route('painel'))->assertOk()
        ->assertSee('ainda não rodaram nesta instalação');

    // Batida velha e pior que ausencia: rodava e parou.
    file_put_contents($batida, 'x');
    touch($batida, now()->subHour()->getTimestamp());

    admin()->get(route('painel'))->assertOk()
        ->assertSee('não rodam há mais de 10 minutos');

    // Batida fresca, nenhum aviso.
    touch($batida, now()->getTimestamp());

    admin()->get(route('painel'))->assertOk()
        ->assertDontSee('rotinas automáticas não rodam');

    @unlink($batida);
});

it('mostra a margem do periodo no painel', function () {
    // Consumo e custo estavam os dois na tela e a subtracao ficava na cabeca.
    $empresa = empresaComPlano();
    $servico = Servico::firstWhere('codigo', 'scpc-bvs');

    app(App\Actions\Consumo\RegistrarConsulta::class)($empresa, $servico, 4);

    // 4 x (R$ 3,24 de preco - R$ 1,50 de custo) = R$ 6,96.
    expect(admin()->get(route('painel'))->assertOk()->viewData('margemCents'))->toBe(696);
});

it('respeita o teto diario de demonstracoes', function () {
    [$vendedor] = carteira();
    $servico = Servico::firstWhere('codigo', 'scpc-bvs');

    foreach (range(1, Consulta::LIMITE_DIARIO_DEMONSTRACAO) as $i) {
        Consulta::factory()->create([
            'cliente_id' => null,
            'vendedor_id' => $vendedor->id,
            'servico_id' => $servico->id,
        ]);
    }

    comoVendedor($vendedor)->from(route('carteira.consultar'))
        ->post(route('carteira.consultar.executar'), [
            'servico_id' => $servico->id, 'documento' => '33333333333',
        ])->assertSessionHas('erro');

    expect(Consulta::count())->toBe(Consulta::LIMITE_DIARIO_DEMONSTRACAO);
});

it('entrega o resultado em PDF so para o dono da demonstracao', function () {
    [$vendedor] = carteira();
    $servico = Servico::firstWhere('codigo', 'scpc-bvs');

    comoVendedor($vendedor)->post(route('carteira.consultar.executar'), [
        'servico_id' => $servico->id, 'documento' => DOC_DEMO_OK,
    ]);
    $consulta = Consulta::sole();

    $bytes = comoVendedor($vendedor)->get(route('carteira.demonstracoes.pdf', $consulta))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->getContent();

    expect(str_starts_with($bytes, '%PDF-1.4'))->toBeTrue();

    // Outro vendedor nao abre nem o resultado nem o PDF.
    $outro = App\Models\Staff::factory()->create(['papel' => 'vendedor']);
    comoVendedor($outro)->get(route('carteira.demonstracoes.ver', $consulta))->assertForbidden();
    comoVendedor($outro)->get(route('carteira.demonstracoes.pdf', $consulta))->assertForbidden();
});

it('nao mistura demonstracao com o consumo das empresas', function () {
    // A demonstracao nao pode virar cobranca de ninguem: fechamento de
    // competencia de qualquer empresa ignora consultas sem cliente.
    [$vendedor, $empresa] = carteira();
    $servico = Servico::firstWhere('codigo', 'scpc-bvs');

    comoVendedor($vendedor)->post(route('carteira.consultar.executar'), [
        'servico_id' => $servico->id, 'documento' => DOC_DEMO_OK,
    ]);

    $fatura = app(App\Actions\Consumo\FecharCompetencia::class)($empresa, Consulta::competenciaDe())['fatura'];

    expect($fatura->consumo_bruto_cents)->toBe(0)
        ->and($fatura->itens()->count())->toBe(0);
});
