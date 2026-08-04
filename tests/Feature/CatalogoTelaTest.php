<?php

use App\Models\Servico;
use App\Models\Staff;
use App\Models\VersaoCatalogo;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Quem entra
|--------------------------------------------------------------------------
*/

it('nao deixa vendedor abrir o catalogo', function () {
    // O vendedor e usuario legitimo do sistema; o que ele nao pode ver e custo
    // e margem, que sairiam junto com a tabela de precos.
    $this->actingAs(Staff::factory()->create(), 'staff')
        ->withSession(['versao_staff' => 1])
        ->get('/catalogo/tabela')
        ->assertForbidden();
});

it('nao deixa visitante abrir o catalogo', function () {
    $this->get('/catalogo/tabela')->assertRedirect(route('entrar'));
});

/*
|--------------------------------------------------------------------------
| A tabela
|--------------------------------------------------------------------------
*/

it('abre a tabela direto, sem lista no meio do caminho', function () {
    VersaoCatalogo::factory()->comServico('scpc-bvs', [0 => 631, 500_000 => 370])->create();

    admin()->get('/catalogo/tabela')
        ->assertOk()
        ->assertSee('scpc-bvs')
        ->assertSee('Sem mínimo')
        ->assertSee('value="6,31"', false)
        ->assertSee('value="3,70"', false);
});

it('oferece as categorias na ordem credito, veicular e todos', function () {
    VersaoCatalogo::factory()->comServico('scpc-bvs', [0 => 631])->create();

    $html = admin()->get('/catalogo/tabela')->assertOk()->getContent();

    // So o bloco das abas: "Todos" tambem aparece no select de reajuste, e
    // procurar no documento inteiro daria falso positivo.
    preg_match('/data-abas="categorias".*?<\/div>/s', $html, $bloco);
    preg_match_all('/>\s*(Crédito|Veicular|Todos)\s*</u', $bloco[0], $rotulos);

    expect($rotulos[1])->toBe(['Crédito', 'Veicular', 'Todos']);
});

it('filtra por categoria', function () {
    VersaoCatalogo::factory()
        ->comServico('scpc-bvs', [0 => 631])
        ->comServico('renajud', [0 => 1_055])
        ->create();

    Servico::where('codigo', 'renajud')->update(['categoria' => 'veicular']);

    admin()->get(route('catalogo.tabela', ['categoria' => 'veicular']))
        ->assertOk()
        ->assertSee('renajud')
        ->assertDontSee('scpc-bvs');
});

it('ignora filtro de categoria invalido em vez de quebrar', function () {
    VersaoCatalogo::factory()->comServico('scpc-bvs', [0 => 631])->create();

    admin()->get(route('catalogo.tabela', ['categoria' => 'sei-la']))
        ->assertOk()
        ->assertSee('scpc-bvs');
});

it('avisa quando nao ha catalogo nenhum', function () {
    admin()->get('/catalogo/tabela')->assertOk()->assertSee('Catalogo vazio');
});

/*
|--------------------------------------------------------------------------
| Edicao
|--------------------------------------------------------------------------
*/

it('grava os precos editados', function () {
    $catalogo = VersaoCatalogo::factory()->comServico('scpc-bvs', [0 => 631, 90_000 => 493])->create();
    $precos = $catalogo->precos()->orderBy('consumo_minimo_cents')->get();

    admin()->put(route('catalogo.precos', $catalogo), [
        'precos' => [
            $precos[0]->id => '7,00',
            $precos[1]->id => '5,50',
        ],
    ])->assertSessionHas('ok');

    expect($catalogo->precoDe('scpc-bvs', 0))->toBe(700)
        ->and($catalogo->precoDe('scpc-bvs', 90_000))->toBe(550);
});

it('aceita o dinheiro como o operador digita', function () {
    $catalogo = VersaoCatalogo::factory()->comServico('vip-car', [0 => 5_530])->create();
    $preco = $catalogo->precos()->first();

    admin()->put(route('catalogo.precos', $catalogo), [
        'precos' => [$preco->id => '1.234,56'],
    ]);

    expect($preco->fresh()->preco_cents)->toBe(123_456);
});

it('ignora preco que nao pertence a este catalogo', function () {
    // Defesa contra id chutado no formulario.
    $alvo = VersaoCatalogo::factory()->comServico('scpc-bvs', [0 => 631])->create();
    $outro = VersaoCatalogo::factory()->comServico('renajud', [0 => 1_055])->create();

    $alheio = $outro->precos()->first();

    admin()->put(route('catalogo.precos', $alvo), [
        'precos' => [$alheio->id => '1,00'],
    ]);

    expect($alheio->fresh()->preco_cents)->toBe(1_055);
});

/*
|--------------------------------------------------------------------------
| Reajuste
|--------------------------------------------------------------------------
*/

it('aplica percentual em todos os precos', function () {
    $catalogo = VersaoCatalogo::factory()->comServico('scpc-bvs', [0 => 1_000, 90_000 => 500])->create();

    admin()->post(route('catalogo.reajustar', $catalogo), ['percentual' => 10])
        ->assertSessionHas('ok');

    expect($catalogo->precoDe('scpc-bvs', 0))->toBe(1_100)
        ->and($catalogo->precoDe('scpc-bvs', 90_000))->toBe(550);
});

it('arredonda o reajuste para o centavo mais proximo', function () {
    // 631 + 5% = 662,55 centavos. Centavo fracionado nao existe em fatura.
    $catalogo = VersaoCatalogo::factory()->comServico('scpc-bvs', [0 => 631])->create();

    admin()->post(route('catalogo.reajustar', $catalogo), ['percentual' => 5]);

    expect($catalogo->precoDe('scpc-bvs', 0))->toBe(663);
});

it('aceita reajuste negativo', function () {
    $catalogo = VersaoCatalogo::factory()->comServico('scpc-bvs', [0 => 1_000])->create();

    admin()->post(route('catalogo.reajustar', $catalogo), ['percentual' => -10]);

    expect($catalogo->precoDe('scpc-bvs', 0))->toBe(900);
});

it('reajusta so a categoria escolhida', function () {
    $catalogo = VersaoCatalogo::factory()
        ->comServico('scpc-bvs', [0 => 1_000])
        ->comServico('renajud', [0 => 1_000])
        ->create();

    Servico::where('codigo', 'renajud')->update(['categoria' => 'veicular']);

    admin()->post(route('catalogo.reajustar', $catalogo), [
        'percentual' => 10,
        'categoria' => 'veicular',
    ]);

    expect($catalogo->precoDe('renajud', 0))->toBe(1_100)
        ->and($catalogo->precoDe('scpc-bvs', 0))->toBe(1_000);
});

it('recusa percentual fora da faixa aceitavel', function () {
    $catalogo = VersaoCatalogo::factory()->comServico('scpc-bvs', [0 => 1_000])->create();

    admin()->post(route('catalogo.reajustar', $catalogo), ['percentual' => -100])
        ->assertSessionHasErrors('percentual');

    expect($catalogo->precoDe('scpc-bvs', 0))->toBe(1_000);
});

it('nao deixa vendedor editar nem reajustar', function () {
    $catalogo = VersaoCatalogo::factory()->comServico('scpc-bvs', [0 => 1_000])->create();

    $vendedor = fn () => test()
        ->actingAs(Staff::factory()->create(), 'staff')
        ->withSession(['versao_staff' => 1]);

    $vendedor()->put(route('catalogo.precos', $catalogo), [])->assertForbidden();
    $vendedor()->post(route('catalogo.reajustar', $catalogo), ['percentual' => 10])->assertForbidden();

    expect($catalogo->precoDe('scpc-bvs', 0))->toBe(1_000);
});

/*
|--------------------------------------------------------------------------
| Menu
|--------------------------------------------------------------------------
*/

it('mostra o catalogo no menu do admin e esconde do vendedor', function () {
    admin()->get('/')->assertOk()->assertSee('Catálogo');

    $this->actingAs(Staff::factory()->create(), 'staff')
        ->withSession(['versao_staff' => 1])
        ->get('/')
        ->assertOk()
        ->assertDontSee('Catálogo');
});
