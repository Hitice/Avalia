<?php

use App\Actions\Catalogo\LevarPrecosAoPiso;
use App\Models\Catalogo;
use App\Models\Preco;
use App\Support\Margem;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Preco que vende no prejuizo.
 *
 * A guarda da tela de edicao so pega quem edita servico a servico. Preco vindo
 * da carga inicial da tabela de referencia nunca passou por ela, e mudar
 * imposto, comissao ou custo do fornecedor move o piso do catalogo inteiro sem
 * reavaliar linha nenhuma. Era por isso que existia margem negativa em tabela
 * que ninguem tinha editado.
 *
 * A correcao nunca e automatica: subir preco de tabela e decisao comercial.
 */
function catalogoComPrejuizo(): array
{
    $catalogo = Catalogo::factory()->comServico('scpc-bvs', [0 => 300, 90_000 => 100])->create();
    $catalogo->precos()->update(['custo_cents' => 150]);

    return [$catalogo->fresh(), Margem::pisoCents(150, $catalogo->imposto_bps, $catalogo->comissaoBps())];
}

it('encontra o preco que nao cobre custo, imposto e comissao', function () {
    [$catalogo, $piso] = catalogoComPrejuizo();

    $furados = LevarPrecosAoPiso::abaixoDoPiso($catalogo);

    // R$ 3,00 cobre; R$ 1,00 com custo de R$ 1,50 nao cobre de jeito nenhum.
    expect($furados)->toHaveCount(1)
        ->and($furados->first()['preco']->preco_cents)->toBe(100)
        ->and($furados->first()['piso'])->toBe($piso)
        ->and($piso)->toBeGreaterThan(150);
});

it('nao inventa prejuizo onde o custo nao esta cadastrado', function () {
    $catalogo = Catalogo::factory()->comServico('scpc-bvs', [0 => 100])->create();
    $catalogo->precos()->update(['custo_cents' => null]);

    expect(LevarPrecosAoPiso::abaixoDoPiso($catalogo->fresh()))->toHaveCount(0);
});

it('sobe ao piso so o que estava furado, e registra na trilha', function () {
    [$catalogo, $piso] = catalogoComPrejuizo();

    $corrigidos = app(LevarPrecosAoPiso::class)($catalogo);

    expect($corrigidos)->toBe(1)
        ->and(Preco::where('consumo_minimo_cents', 90_000)->value('preco_cents'))->toBe($piso)
        // O preco que estava saudavel nao se mexe.
        ->and(Preco::where('consumo_minimo_cents', 0)->value('preco_cents'))->toBe(300)
        ->and(LevarPrecosAoPiso::abaixoDoPiso($catalogo->fresh()))->toHaveCount(0);

    $trilha = App\Models\Auditoria::where('acao', 'preco.piso')->sole();

    expect($trilha->dados['de_cents'])->toBe(100)
        ->and($trilha->dados['para_cents'])->toBe($piso)
        ->and(App\Support\Rotulos::acao('preco.piso'))->not->toBe('preco.piso');
});

it('avisa na tela e oferece a correcao, sem aplicar sozinha', function () {
    [$catalogo] = catalogoComPrejuizo();

    admin()->get(route('catalogo.tabela'))
        ->assertOk()
        ->assertSee('abaixo do custo com imposto e comissão', false)
        ->assertSee(route('catalogo.precos.piso'), false);

    // Só de abrir a tela, nada mudou: a decisao e de quem clica.
    expect(Preco::where('consumo_minimo_cents', 90_000)->value('preco_cents'))->toBe(100);
});

it('deixa o administrador aplicar a correcao pela tela', function () {
    [$catalogo, $piso] = catalogoComPrejuizo();

    admin()->post(route('catalogo.precos.piso'))
        ->assertRedirect()
        ->assertSessionHas('ok');

    expect(Preco::where('consumo_minimo_cents', 90_000)->value('preco_cents'))->toBe($piso);
});

it('nao deixa vendedor mexer no preco de tabela', function () {
    catalogoComPrejuizo();

    comoVendedor(App\Models\Staff::factory()->create(['papel' => 'vendedor']))
        ->post(route('catalogo.precos.piso'))
        ->assertForbidden();
});
