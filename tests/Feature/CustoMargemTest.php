<?php

use App\Models\Preco;
use App\Models\Staff;
use App\Models\VersaoCatalogo;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Custo do fornecedor
|--------------------------------------------------------------------------
*/

it('grava o custo do fornecedor', function () {
    $catalogo = VersaoCatalogo::factory()->comServico('scpc-bvs', [0 => 631])->create();
    $preco = $catalogo->precos()->first();

    expect($preco->custo_cents)->toBeNull();

    admin()->put(route('catalogo.custos', $catalogo), [
        'custos' => [$preco->id => '3,70'],
    ])->assertSessionHas('ok');

    expect($preco->fresh()->custo_cents)->toBe(370)
        ->and($preco->fresh()->preco_cents)->toBe(631);
});

it('apaga o custo quando o campo volta vazio', function () {
    // Campo em branco devolve a linha ao estado "custo nao cadastrado". Gravar
    // zero seria mentira: significaria fornecedor de graca.
    $catalogo = VersaoCatalogo::factory()->comServico('scpc-bvs', [0 => 631])->create();
    $preco = $catalogo->precos()->first();

    admin()->put(route('catalogo.custos', $catalogo), ['custos' => [$preco->id => '3,70']]);
    expect($preco->fresh()->custo_cents)->toBe(370);

    admin()->put(route('catalogo.custos', $catalogo), ['custos' => [$preco->id => '']]);

    expect($preco->fresh()->custo_cents)->toBeNull();
});

it('nao deixa o custo mexer no preco de venda', function () {
    $catalogo = VersaoCatalogo::factory()->comServico('scpc-bvs', [0 => 631, 90_000 => 493])->create();
    $precos = $catalogo->precos()->orderBy('consumo_minimo_cents')->get();

    admin()->put(route('catalogo.custos', $catalogo), [
        'custos' => [$precos[0]->id => '3,70', $precos[1]->id => '3,10'],
    ]);

    expect($catalogo->precoDe('scpc-bvs', 0))->toBe(631)
        ->and($catalogo->precoDe('scpc-bvs', 90_000))->toBe(493)
        ->and(Preco::sum('custo_cents'))->toBe(680);
});

it('ignora custo de preco que nao pertence a este catalogo', function () {
    $alvo = VersaoCatalogo::factory()->comServico('scpc-bvs', [0 => 631])->create();
    $outro = VersaoCatalogo::factory()->comServico('renajud', [0 => 1_055])->create();
    $alheio = $outro->precos()->first();

    admin()->put(route('catalogo.custos', $alvo), ['custos' => [$alheio->id => '9,99']]);

    expect($alheio->fresh()->custo_cents)->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Imposto
|--------------------------------------------------------------------------
*/

it('comeca com os 27% registrados no PDD', function () {
    $catalogo = VersaoCatalogo::factory()->create();

    expect($catalogo->imposto_bps)->toBe(2_700)
        ->and($catalogo->impostoRotulo())->toBe('27%');
});

it('ajusta a aliquota de imposto', function () {
    $catalogo = VersaoCatalogo::factory()->create();

    admin()->put(route('catalogo.imposto', $catalogo), ['imposto' => '26.8'])
        ->assertSessionHas('ok');

    expect($catalogo->fresh()->imposto_bps)->toBe(2_680)
        ->and($catalogo->fresh()->impostoRotulo())->toBe('26,8%');
});

it('recusa aliquota de 100% ou mais', function () {
    $catalogo = VersaoCatalogo::factory()->create();

    admin()->put(route('catalogo.imposto', $catalogo), ['imposto' => '100'])
        ->assertSessionHasErrors('imposto');

    expect($catalogo->fresh()->imposto_bps)->toBe(2_700);
});

/*
|--------------------------------------------------------------------------
| Visao de margem
|--------------------------------------------------------------------------
*/

it('mostra a margem quando o custo esta cadastrado', function () {
    $catalogo = VersaoCatalogo::factory()->comServico('scpc-bvs', [0 => 1_690])->create();
    $catalogo->precos()->first()->update(['custo_cents' => 1_090]);

    // 16,90 − 10,90 − 27% de 16,90 = 1,44, ou 8,5% da venda.
    admin()->get(route('catalogo.tabela', ['visao' => 'margem']))
        ->assertOk()
        ->assertSee('8,5%');
});

it('acusa venda no prejuizo', function () {
    $catalogo = VersaoCatalogo::factory()->comServico('scpc-bvs', [0 => 1_200])->create();
    $catalogo->precos()->first()->update(['custo_cents' => 1_090]);

    admin()->get(route('catalogo.tabela', ['visao' => 'margem']))
        ->assertOk()
        ->assertSee('-17,8%')
        ->assertSee('bg-error-50', false);
});

it('nao inventa margem sem custo cadastrado', function () {
    $catalogo = VersaoCatalogo::factory()->comServico('scpc-bvs', [0 => 631])->create();

    admin()->get(route('catalogo.tabela', ['visao' => 'margem']))
        ->assertOk()
        ->assertSee('Custo do fornecedor ainda nao cadastrado')
        // Sem custo nao ha percentual nenhum na matriz, nem zero.
        ->assertDontSee('0,0%');
});

it('nao deixa editar preco na visao de margem', function () {
    $catalogo = VersaoCatalogo::factory()->comServico('scpc-bvs', [0 => 631])->create();

    admin()->get(route('catalogo.tabela', ['visao' => 'margem']))
        ->assertOk()
        ->assertDontSee('name="precos[', false)
        ->assertDontSee('Salvar precos');
});

it('mostra campo de custo editavel na visao de custo', function () {
    $catalogo = VersaoCatalogo::factory()->comServico('scpc-bvs', [0 => 631])->create();
    $preco = $catalogo->precos()->first();

    admin()->get(route('catalogo.tabela', ['visao' => 'custo']))
        ->assertOk()
        ->assertSee('name="custos['.$preco->id.']"', false)
        ->assertSee('Salvar custos');
});

it('cai na visao de venda quando a visao pedida nao existe', function () {
    VersaoCatalogo::factory()->comServico('scpc-bvs', [0 => 631])->create();

    admin()->get(route('catalogo.tabela', ['visao' => 'sei-la']))
        ->assertOk()
        ->assertSee('name="precos[', false);
});

it('mantem a categoria ao trocar de visao', function () {
    VersaoCatalogo::factory()->comServico('scpc-bvs', [0 => 631])->create();

    $html = admin()->get(route('catalogo.tabela', ['categoria' => 'credito']))->getContent();

    preg_match('/data-abas="visoes".*?<\/div>/s', $html, $bloco);

    expect($bloco[0])->toContain('categoria=credito');
});

it('nao deixa vendedor ver custo nem margem', function () {
    $catalogo = VersaoCatalogo::factory()->comServico('scpc-bvs', [0 => 631])->create();

    $vendedor = fn () => test()
        ->actingAs(Staff::factory()->create(), 'staff')
        ->withSession(['versao_staff' => 1]);

    $vendedor()->get(route('catalogo.tabela', ['visao' => 'margem']))->assertForbidden();
    $vendedor()->put(route('catalogo.custos', $catalogo), [])->assertForbidden();
    $vendedor()->put(route('catalogo.imposto', $catalogo), ['imposto' => '10'])->assertForbidden();
});
