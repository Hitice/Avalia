<?php

use App\Models\Catalogo;
use App\Models\Preco;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Custo do fornecedor
|--------------------------------------------------------------------------
*/

it('grava o custo do fornecedor', function () {
    $catalogo = Catalogo::factory()->comServico('scpc-bvs', [0 => 631])->create();
    $preco = $catalogo->precos()->first();

    expect($preco->custo_cents)->toBeNull();

    admin()->put(route('catalogo.custos', $catalogo), [
        'custos' => [$preco->servico_id => '3,70'],
    ])->assertSessionHas('ok');

    expect($preco->fresh()->custo_cents)->toBe(370)
        ->and($preco->fresh()->preco_cents)->toBe(631);
});

it('apaga o custo quando o campo volta vazio', function () {
    // Campo em branco devolve a linha ao estado "custo nao cadastrado". Gravar
    // zero seria mentira: significaria fornecedor de graca.
    $catalogo = Catalogo::factory()->comServico('scpc-bvs', [0 => 631])->create();
    $preco = $catalogo->precos()->first();

    admin()->put(route('catalogo.custos', $catalogo), ['custos' => [$preco->servico_id => '3,70']]);
    expect($preco->fresh()->custo_cents)->toBe(370);

    admin()->put(route('catalogo.custos', $catalogo), ['custos' => [$preco->servico_id => '']]);

    expect($preco->fresh()->custo_cents)->toBeNull();
});

it('poe o custo em todas as faixas do servico, sem mexer no preco', function () {
    // O fornecedor cobra por consulta, nao pelo pacote: um custo por servico.
    $catalogo = Catalogo::factory()->comServico('scpc-bvs', [0 => 631, 90_000 => 493])->create();
    $servico = App\Models\Servico::firstWhere('codigo', 'scpc-bvs');

    admin()->put(route('catalogo.custos', $catalogo), ['custos' => [$servico->id => '3,70']]);

    expect($catalogo->precoDe('scpc-bvs', 0))->toBe(631)
        ->and($catalogo->precoDe('scpc-bvs', 90_000))->toBe(493)
        ->and($servico->precos()->pluck('custo_cents')->unique()->all())->toBe([370]);
});

it('nao deixa o custo de um catalogo vazar para o outro', function () {
    $alvo = Catalogo::factory()->comServico('scpc-bvs', [0 => 631])->create();
    $outro = Catalogo::factory()->comServico('renajud', [0 => 1_055])->create();
    $alheio = $outro->precos()->first();

    admin()->put(route('catalogo.custos', $alvo), ['custos' => [$alheio->servico_id => '9,99']]);

    expect($alheio->fresh()->custo_cents)->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Imposto
|--------------------------------------------------------------------------
*/

it('comeca com os parametros comerciais do PDD', function () {
    $catalogo = Catalogo::factory()->create();

    expect($catalogo->impostoRotulo())->toBe('8,6%')
        ->and($catalogo->margemAlvoRotulo())->toBe('30%')
        ->and($catalogo->comissaoBps())->toBe(1_000);
});

it('ajusta a aliquota de imposto', function () {
    $catalogo = Catalogo::factory()->create();

    admin()->put(route('catalogo.parametros', $catalogo), ['imposto' => '26.8', 'margem_alvo' => '30', 'degrau_margem' => '3'])
        ->assertSessionHas('ok');

    expect($catalogo->fresh()->imposto_bps)->toBe(2_680)
        ->and($catalogo->fresh()->impostoRotulo())->toBe('26,8%')
        ->and($catalogo->fresh()->margem_alvo_bps)->toBe(3_000);
});

it('recusa aliquota de 100% ou mais', function () {
    $catalogo = Catalogo::factory()->create();

    admin()->put(route('catalogo.parametros', $catalogo), ['imposto' => '100', 'margem_alvo' => '30', 'degrau_margem' => '3'])
        ->assertSessionHasErrors('imposto');

    expect($catalogo->fresh()->imposto_bps)->toBe(860);
});

/*
|--------------------------------------------------------------------------
| Visao de margem
|--------------------------------------------------------------------------
*/

it('mostra a margem quando o custo esta cadastrado', function () {
    $catalogo = Catalogo::factory()->comServico('scpc-bvs', [0 => 1_690])->create();
    $catalogo->precos()->first()->update(['custo_cents' => 1_090]);

    // 16,90 − 10,90 − 8,6% de imposto − 10% de comissao = 2,86, ou 16,9%.
    admin()->get(route('catalogo.tabela', ['visao' => 'margem']))
        ->assertOk()
        ->assertSee('16,9%');
});

it('acusa venda no prejuizo', function () {
    $catalogo = Catalogo::factory()->comServico('scpc-bvs', [0 => 1_200])->create();
    $catalogo->precos()->first()->update(['custo_cents' => 1_090]);

    admin()->get(route('catalogo.tabela', ['visao' => 'margem']))
        ->assertOk()
        ->assertSee('-9,4%')
        ->assertSee('bg-error-50', false);
});

it('nao inventa margem sem custo cadastrado', function () {
    $catalogo = Catalogo::factory()->comServico('scpc-bvs', [0 => 631])->create();

    admin()->get(route('catalogo.tabela', ['visao' => 'margem']))
        ->assertOk()
        ->assertSee('Custo do fornecedor ainda nao cadastrado')
        // Sem custo nao ha percentual nenhum na matriz, nem zero.
        ->assertDontSee('0,0%');
});

it('nao deixa editar preco na visao de margem', function () {
    $catalogo = Catalogo::factory()->comServico('scpc-bvs', [0 => 631])->create();

    admin()->get(route('catalogo.tabela', ['visao' => 'margem']))
        ->assertOk()
        ->assertDontSee('name="precos[', false)
        ->assertDontSee('Salvar precos');
});

it('mostra campo de custo editavel na visao de custo', function () {
    $catalogo = Catalogo::factory()->comServico('scpc-bvs', [0 => 631])->create();
    $preco = $catalogo->precos()->first();

    admin()->get(route('catalogo.tabela', ['visao' => 'custo']))
        ->assertOk()
        ->assertSee('name="custos['.$preco->servico_id.']"', false)
        ->assertSee('Salvar custos');
});

it('cai na visao de venda quando a visao pedida nao existe', function () {
    Catalogo::factory()->comServico('scpc-bvs', [0 => 631])->create();

    admin()->get(route('catalogo.tabela', ['visao' => 'sei-la']))
        ->assertOk()
        ->assertSee('name="precos[', false);
});

it('mantem a categoria ao trocar de visao', function () {
    Catalogo::factory()->comServico('scpc-bvs', [0 => 631])->create();

    $html = admin()->get(route('catalogo.tabela', ['categoria' => 'credito']))->getContent();

    preg_match('/data-abas="visoes".*?<\/div>/s', $html, $bloco);

    expect($bloco[0])->toContain('categoria=credito');
});

it('nao deixa vendedor ver custo nem margem', function () {
    $catalogo = Catalogo::factory()->comServico('scpc-bvs', [0 => 631])->create();

    $vendedor = fn () => test()
        ->actingAs(Staff::factory()->create(), 'staff')
        ->withSession(['versao_staff' => 1]);

    $vendedor()->get(route('catalogo.tabela', ['visao' => 'margem']))->assertForbidden();
    $vendedor()->put(route('catalogo.custos', $catalogo), [])->assertForbidden();
    $vendedor()->put(route('catalogo.parametros', $catalogo), ['imposto' => '10', 'margem_alvo' => '30', 'degrau_margem' => '3'])->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| Escada de margem
|--------------------------------------------------------------------------
*/

it('da mais margem a cada degrau abaixo do topo', function () {
    // A margem alvo e o piso e vale para a MAIOR faixa.
    $catalogo = Catalogo::factory()->create(['margem_alvo_bps' => 3_000, 'degrau_margem_bps' => 300]);

    $margens = $catalogo->margemPorFaixa([0, 7_500, 90_000, 500_000]);

    expect($margens)->toBe([0 => 3_900, 7_500 => 3_600, 90_000 => 3_300, 500_000 => 3_000]);
});

it('reprecifica de forma que o pacote maior sai mais barato por consulta', function () {
    $catalogo = Catalogo::factory()
        ->comServico('scpc-bvs', [0 => 631, 90_000 => 493, 500_000 => 370])
        ->create(['margem_alvo_bps' => 3_000, 'degrau_margem_bps' => 300]);

    $catalogo->precos()->update(['custo_cents' => 280]);

    admin()->post(route('catalogo.precificar', $catalogo))->assertSessionHas('ok');

    $semMinimo = $catalogo->precoDe('scpc-bvs', 0);
    $meio = $catalogo->precoDe('scpc-bvs', 90_000);
    $topo = $catalogo->precoDe('scpc-bvs', 500_000);

    // Preco unitario cai conforme o cliente sobe de pacote.
    expect($semMinimo)->toBeGreaterThan($meio)
        ->and($meio)->toBeGreaterThan($topo);
});

it('nunca deixa nenhuma faixa abaixo da margem alvo', function () {
    $catalogo = Catalogo::factory()
        ->comServico('scpc-bvs', [0 => 631, 7_500 => 594, 90_000 => 493, 500_000 => 370])
        ->comServico('vip-car', [0 => 5_530, 7_500 => 5_364, 90_000 => 4_896, 500_000 => 4_468])
        ->create(['margem_alvo_bps' => 3_000, 'degrau_margem_bps' => 300]);

    $catalogo->precos()->update(['custo_cents' => 280]);

    admin()->post(route('catalogo.precificar', $catalogo));

    foreach ($catalogo->precos()->get() as $preco) {
        expect(App\Support\Margem::atinge(
            $preco->preco_cents, $preco->custo_cents,
            $catalogo->imposto_bps, $catalogo->comissaoBps(), $catalogo->margem_alvo_bps,
        ))->toBeTrue();
    }
});

it('deixa de fora o servico sem custo', function () {
    $catalogo = Catalogo::factory()->comServico('scpc-bvs', [0 => 631])->create();

    admin()->post(route('catalogo.precificar', $catalogo));

    expect($catalogo->precoDe('scpc-bvs', 0))->toBe(631);
});

it('recusa escada que estoura 100% na faixa mais baixa', function () {
    // 30% de piso mais 6 degraus de 15% passaria de 100% com imposto e comissao.
    $catalogo = Catalogo::factory()->comServico('scpc-bvs', [0 => 631, 7_500 => 594, 500_000 => 370])->create();

    admin()->put(route('catalogo.parametros', $catalogo), [
        'imposto' => '8.6', 'margem_alvo' => '60', 'degrau_margem' => '15',
    ])->assertSessionHasErrors('degrau_margem');

    expect($catalogo->fresh()->margem_alvo_bps)->toBe(3_000);
});

it('bloqueia preco abaixo do piso na gravacao manual', function () {
    // Relatar prejuizo depois do fato nao impede ninguem de vender no negativo.
    $catalogo = Catalogo::factory()->comServico('scpc-bvs', [0 => 631])->create();
    $preco = $catalogo->precos()->first();
    $preco->update(['custo_cents' => 280]);

    admin()->put(route('catalogo.precos', $catalogo), ['precos' => [$preco->id => '3,00']])
        ->assertSessionHas('erro');

    expect($preco->fresh()->preco_cents)->toBe(631);
});
