<?php

use App\Models\Plano;
use App\Models\Preco;
use App\Models\Servico;
use App\Models\Staff;
use App\Models\VersaoCatalogo;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Duplicar
|--------------------------------------------------------------------------
*/

it('duplica a versao em vigor para um rascunho editavel', function () {
    $vigente = VersaoCatalogo::factory()
        ->comServico('scpc-bvs', [0 => 631, 90_000 => 493])
        ->ativa()
        ->create();

    admin()->post(route('catalogo.versoes.duplicar', $vigente), ['rotulo' => 'Catálogo 05/2026'])
        ->assertRedirect();

    $nova = VersaoCatalogo::firstWhere('rotulo', 'Catálogo 05/2026');

    expect($nova->situacao)->toBe('rascunho')
        ->and($nova->precoDe('scpc-bvs', 0))->toBe(631)
        ->and($nova->precos()->count())->toBe(2)
        // A original nao pode ter sido tocada: fatura ja emitida aponta para ela.
        ->and($vigente->fresh()->situacao)->toBe('ativa')
        ->and($vigente->precoDe('scpc-bvs', 0))->toBe(631);
});

it('recusa duplicar com nome ja usado', function () {
    $versao = VersaoCatalogo::factory()->comServico('scpc-bvs')->ativa()->create();
    VersaoCatalogo::factory()->create(['rotulo' => 'Catálogo 05/2026']);

    admin()->post(route('catalogo.versoes.duplicar', $versao), ['rotulo' => 'Catálogo 05/2026'])
        ->assertSessionHasErrors('rotulo');

    expect(VersaoCatalogo::count())->toBe(2);
});

it('oferece duplicar na versao congelada e reajuste no rascunho', function () {
    $rascunho = VersaoCatalogo::factory()->comServico('scpc-bvs', [0 => 631])->create();

    admin()->get(route('catalogo.versoes.mostrar', $rascunho))
        ->assertSee('Aplicar')
        ->assertSee('Salvar precos')
        ->assertDontSee('Duplicar para reajustar');

    $ativa = VersaoCatalogo::factory()->comServico('renajud', [0 => 1_055])->ativa()->create();

    admin()->get(route('catalogo.versoes.mostrar', $ativa))
        ->assertSee('Duplicar para reajustar')
        ->assertDontSee('Salvar precos');
});

/*
|--------------------------------------------------------------------------
| Editar preco do rascunho
|--------------------------------------------------------------------------
*/

it('grava os precos editados do rascunho', function () {
    $versao = VersaoCatalogo::factory()->comServico('scpc-bvs', [0 => 631, 90_000 => 493])->create();
    $precos = $versao->precos()->orderBy('consumo_minimo_cents')->get();

    admin()->put(route('catalogo.versoes.precos', $versao), [
        'precos' => [
            $precos[0]->id => '7,00',
            $precos[1]->id => '5,50',
        ],
    ])->assertSessionHas('ok');

    expect($versao->precoDe('scpc-bvs', 0))->toBe(700)
        ->and($versao->precoDe('scpc-bvs', 90_000))->toBe(550);
});

it('nao aceita editar preco de versao em vigor', function () {
    $versao = VersaoCatalogo::factory()->comServico('scpc-bvs', [0 => 631])->ativa()->create();
    $preco = $versao->precos()->first();

    admin()->put(route('catalogo.versoes.precos', $versao), [
        'precos' => [$preco->id => '1,00'],
    ])->assertSessionHas('erro');

    expect($versao->precoDe('scpc-bvs', 0))->toBe(631);
});

it('ignora preco de outra versao enfiado no formulario', function () {
    // Defesa contra id chutado: o rascunho nao pode reprecificar a tabela
    // congelada que esta faturando.
    $congelada = VersaoCatalogo::factory()->comServico('scpc-bvs', [0 => 631])->ativa()->create();
    $rascunho = VersaoCatalogo::factory()->comServico('renajud', [0 => 1_055])->create();

    $alheio = $congelada->precos()->first();

    admin()->put(route('catalogo.versoes.precos', $rascunho), [
        'precos' => [$alheio->id => '1,00'],
    ]);

    expect($alheio->fresh()->preco_cents)->toBe(631);
});

it('aceita o dinheiro como o operador digita', function () {
    $versao = VersaoCatalogo::factory()->comServico('vip-car', [0 => 5_530])->create();
    $preco = $versao->precos()->first();

    admin()->put(route('catalogo.versoes.precos', $versao), [
        'precos' => [$preco->id => '1.234,56'],
    ]);

    expect($preco->fresh()->preco_cents)->toBe(123_456);
});

/*
|--------------------------------------------------------------------------
| Reajuste percentual
|--------------------------------------------------------------------------
*/

it('aplica percentual em todos os precos do rascunho', function () {
    $versao = VersaoCatalogo::factory()->comServico('scpc-bvs', [0 => 1_000, 90_000 => 500])->create();

    admin()->post(route('catalogo.versoes.reajustar', $versao), ['percentual' => 10])
        ->assertSessionHas('ok');

    expect($versao->precoDe('scpc-bvs', 0))->toBe(1_100)
        ->and($versao->precoDe('scpc-bvs', 90_000))->toBe(550);
});

it('arredonda o reajuste para o centavo mais proximo', function () {
    // 631 + 5% = 662,55 centavos. Centavo fracionado nao existe em fatura.
    $versao = VersaoCatalogo::factory()->comServico('scpc-bvs', [0 => 631])->create();

    admin()->post(route('catalogo.versoes.reajustar', $versao), ['percentual' => 5]);

    expect($versao->precoDe('scpc-bvs', 0))->toBe(663);
});

it('aceita reajuste negativo', function () {
    $versao = VersaoCatalogo::factory()->comServico('scpc-bvs', [0 => 1_000])->create();

    admin()->post(route('catalogo.versoes.reajustar', $versao), ['percentual' => -10]);

    expect($versao->precoDe('scpc-bvs', 0))->toBe(900);
});

it('reajusta so a categoria escolhida', function () {
    $versao = VersaoCatalogo::factory()
        ->comServico('scpc-bvs', [0 => 1_000])
        ->comServico('renajud', [0 => 1_000])
        ->create();

    Servico::where('codigo', 'renajud')->update(['categoria' => 'veicular']);

    admin()->post(route('catalogo.versoes.reajustar', $versao), [
        'percentual' => 10,
        'categoria' => 'veicular',
    ]);

    expect($versao->precoDe('renajud', 0))->toBe(1_100)
        ->and($versao->precoDe('scpc-bvs', 0))->toBe(1_000);
});

it('nao reajusta versao em vigor', function () {
    $versao = VersaoCatalogo::factory()->comServico('scpc-bvs', [0 => 1_000])->ativa()->create();

    admin()->post(route('catalogo.versoes.reajustar', $versao), ['percentual' => 10])
        ->assertSessionHas('erro');

    expect($versao->precoDe('scpc-bvs', 0))->toBe(1_000);
});

it('recusa percentual fora da faixa aceitavel', function () {
    $versao = VersaoCatalogo::factory()->comServico('scpc-bvs', [0 => 1_000])->create();

    admin()->post(route('catalogo.versoes.reajustar', $versao), ['percentual' => -100])
        ->assertSessionHasErrors('percentual');

    expect($versao->precoDe('scpc-bvs', 0))->toBe(1_000);
});

it('nao deixa vendedor duplicar, editar nem reajustar', function () {
    $versao = VersaoCatalogo::factory()->comServico('scpc-bvs', [0 => 1_000])->create();

    $vendedor = fn () => test()
        ->actingAs(Staff::factory()->create(), 'staff')
        ->withSession(['versao_staff' => 1]);

    $vendedor()->post(route('catalogo.versoes.duplicar', $versao), ['rotulo' => 'x'])->assertForbidden();
    $vendedor()->put(route('catalogo.versoes.precos', $versao), [])->assertForbidden();
    $vendedor()->post(route('catalogo.versoes.reajustar', $versao), ['percentual' => 10])->assertForbidden();

    expect($versao->precoDe('scpc-bvs', 0))->toBe(1_000);
});

/*
|--------------------------------------------------------------------------
| O motivo de tudo isso
|--------------------------------------------------------------------------
*/

it('preserva o preco da versao antiga depois do reajuste na nova', function () {
    // E a razao de existir versao: o plano contratado em abril continua
    // apontando para o preco de abril depois do reajuste de maio.
    $abril = VersaoCatalogo::factory()->comServico('scpc-bvs', [90_000 => 493])->ativa()->create();
    $plano = Plano::factory()->consumoMinimo(900)->create(['versao_id' => $abril->id]);

    expect($plano->precoDe('scpc-bvs'))->toBe(493);

    admin()->post(route('catalogo.versoes.duplicar', $abril), ['rotulo' => 'Catálogo 05/2026']);
    $maio = VersaoCatalogo::firstWhere('rotulo', 'Catálogo 05/2026');
    admin()->post(route('catalogo.versoes.reajustar', $maio), ['percentual' => 20]);
    admin()->post(route('catalogo.versoes.ativar', $maio));

    expect($maio->fresh()->precoDe('scpc-bvs', 90_000))->toBe(592)
        ->and($plano->fresh()->precoDe('scpc-bvs'))->toBe(493)
        ->and(Preco::count())->toBe(2);
});
