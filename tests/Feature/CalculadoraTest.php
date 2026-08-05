<?php

use App\Models\Catalogo;
use App\Models\Staff;
use App\Support\Simulacao;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| A conta
|--------------------------------------------------------------------------
|
| Imposto 8,60% sobre a fatura inteira, comissao 10% sobre o consumo
| realizado liquido de imposto, custo so sobre o que foi consultado.
|
*/

it('soma mensalidade e consumo, e desconta os tres custos', function () {
    // Consumo R$ 900, minimo R$ 900, mensalidade R$ 79,90, custo 51,3%.
    $mes = Simulacao::mensal(
        consumoCents: 90_000,
        consumoMinimoCents: 90_000,
        mensalidadeCents: 7_990,
        custoSobreVendaBps: 5_130,
        impostoBps: 860,
        comissaoPct: 10,
    );

    expect($mes['fatura_cents'])->toBe(97_990)
        ->and($mes['imposto_cents'])->toBe(8_427)      // 8,60% de 979,90
        ->and($mes['custo_cents'])->toBe(46_170)       // 51,3% de 900,00
        ->and($mes['comissao_cents'])->toBe(8_226)     // 10% de (900 - 8,60%)
        ->and($mes['lucro_cents'])->toBe(35_167)
        ->and($mes['margem_pct'])->toBe(35.9);
});

it('cobra o minimo mas so custeia o que foi consultado', function () {
    // O piso da fatura protege a Avalia; consulta que nao aconteceu nao gera
    // custo de fornecedor nem comissao.
    $mes = Simulacao::mensal(
        consumoCents: 30_000,
        consumoMinimoCents: 90_000,
        mensalidadeCents: 7_990,
        custoSobreVendaBps: 5_130,
        impostoBps: 860,
        comissaoPct: 10,
    );

    expect($mes['consumo_faturado_cents'])->toBe(90_000)
        ->and($mes['pagou_sem_usar_cents'])->toBe(60_000)
        ->and($mes['custo_cents'])->toBe(15_390)     // 51,3% dos 300 usados
        ->and($mes['comissao_cents'])->toBe(2_742);  // 10% de (300 - 8,60%)
});

it('mostra que o cliente que paga sem usar e o mais lucrativo', function () {
    $conta = fn (int $consumo) => Simulacao::mensal(
        consumoCents: $consumo,
        consumoMinimoCents: 90_000,
        mensalidadeCents: 7_990,
        custoSobreVendaBps: 5_130,
        impostoBps: 860,
        comissaoPct: 10,
    )['lucro_cents'];

    expect($conta(0))->toBeGreaterThan($conta(45_000))
        ->and($conta(45_000))->toBeGreaterThan($conta(90_000));
});

it('sobe a comissao do mes inteiro quando houve excedente', function () {
    $normal = Simulacao::mensal(90_000, 90_000, 7_990, 5_130, 860, 10);
    $comExcedente = Simulacao::mensal(90_000, 90_000, 7_990, 5_130, 860, 20);

    expect($comExcedente['comissao_cents'])->toBe($normal['comissao_cents'] * 2)
        ->and($comExcedente['lucro_cents'])->toBeLessThan($normal['lucro_cents']);
});

it('acusa prejuizo em vez de esconder', function () {
    $mes = Simulacao::mensal(90_000, 90_000, 0, 9_500, 860, 20);

    expect($mes['lucro_cents'])->toBeLessThan(0);
});

it('parcela a adesao e reparte meio a meio', function () {
    $adesao = Simulacao::adesaoDoMes(1_200_000, 12);

    expect($adesao['parcela_cents'])->toBe(100_000)
        ->and($adesao['avalia_cents'])->toBe(50_000)
        ->and($adesao['vendedor_cents'])->toBe(50_000);
});

it('trata adesao isenta como ausencia de cobranca', function () {
    // Isentar nao e desconto na parte da Avalia: ninguem recebe.
    $adesao = Simulacao::adesaoDoMes(0, 1);

    expect($adesao['parcela_cents'])->toBe(0)
        ->and($adesao['avalia_cents'])->toBe(0)
        ->and($adesao['vendedor_cents'])->toBe(0);
});

/*
|--------------------------------------------------------------------------
| A tela
|--------------------------------------------------------------------------
*/

it('nao deixa vendedor abrir a calculadora', function () {
    // Ela mostra custo do fornecedor e margem, que sao internos.
    $this->actingAs(Staff::factory()->create(), 'staff')
        ->withSession(['versao_staff' => 1])
        ->get(route('catalogo.calculadora'))
        ->assertForbidden();
});

it('abre com um cenario plausivel sem pedir nada', function () {
    Catalogo::factory()->comServico('scpc-bvs', [0 => 631, 90_000 => 493, 500_000 => 370])->create();

    admin()->get(route('catalogo.calculadora'))
        ->assertOk()
        ->assertSee('Sobra para a Avalia')
        // Faixa do meio como padrao, nem o extremo bom nem o ruim.
        ->assertSee("R$\u{00A0}900,00", false);
});

it('carrega o cenario inteiro pelo endereco', function () {
    // A simulacao precisa virar link, senao vira captura de tela no chat.
    $catalogo = Catalogo::factory()->comServico('scpc-bvs', [0 => 631, 90_000 => 493])->create();
    $catalogo->precos()->update(['custo_cents' => 280]);

    $resposta = admin()->get(route('catalogo.calculadora', [
        'faixa' => '900,00',
        'consumo' => '1.500,00',
        'mensalidade' => '79,90',
        'custo_pct' => '50',
        'adesao' => '1.200,00',
        'parcelas' => 12,
    ]))->assertOk();

    $mes = $resposta->viewData('mes');

    expect($mes['fatura_cents'])->toBe(157_990)     // 79,90 + 1.500 consumidos
        ->and($mes['custo_cents'])->toBe(75_000)    // 50% de 1.500
        ->and($resposta->viewData('adesao')['parcela_cents'])->toBe(10_000);
});

it('sugere o custo medio do catalogo na faixa escolhida', function () {
    // 2,80 sobre 4,00 e 70%; 1,00 sobre 4,00 e 25%. Media 47,5%.
    $catalogo = Catalogo::factory()
        ->comServico('scpc-bvs', [90_000 => 400])
        ->comServico('renajud', [90_000 => 400])
        ->create();

    $catalogo->precos()->where('servico_id', 1)->update(['custo_cents' => 280]);
    $catalogo->precos()->where('servico_id', 2)->update(['custo_cents' => 100]);

    expect($catalogo->custoSobreVendaBps(90_000))->toBe(4_750);
});

it('nao inventa custo medio onde nao ha custo cadastrado', function () {
    $catalogo = Catalogo::factory()->comServico('scpc-bvs', [90_000 => 400])->create();

    expect($catalogo->custoSobreVendaBps(90_000))->toBeNull();
});

it('avisa em vez de calcular quando nao ha catalogo', function () {
    admin()->get(route('catalogo.calculadora'))
        ->assertOk()
        ->assertSee('Sem catálogo não há faixa nem custo para simular');
});
