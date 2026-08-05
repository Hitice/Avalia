<?php

use App\Models\Catalogo;
use App\Models\Servico;
use App\Models\Staff;
use App\Support\Margem;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Edita um servico pela pagina dele, que e o unico lugar onde se mexe em preco.
 *
 * @param  array<int, string>  $precos  faixa em centavos => valor digitado
 */
function editaServico(Servico $servico, ?string $custo = null, array $precos = []): Illuminate\Testing\TestResponse
{
    return admin()->put(route('catalogo.servicos.atualizar', $servico), [
        'nome' => $servico->nome,
        'categoria' => $servico->categoria->value,
        'ativo' => $servico->ativo ? '1' : '0',
        'custo' => $custo,
        'precos' => $precos,
    ]);
}

/*
|--------------------------------------------------------------------------
| Custo do fornecedor
|--------------------------------------------------------------------------
*/

it('grava o custo do fornecedor sem mexer no preco', function () {
    $catalogo = Catalogo::factory()->comServico('scpc-bvs', [0 => 631])->create();
    $servico = Servico::firstWhere('codigo', 'scpc-bvs');

    expect($catalogo->precos()->first()->custo_cents)->toBeNull();

    editaServico($servico, custo: '3,70')->assertSessionHas('ok');

    expect($catalogo->precos()->first()->custo_cents)->toBe(370)
        ->and($catalogo->precoDe('scpc-bvs', 0))->toBe(631);
});

it('apaga o custo quando o campo volta vazio', function () {
    // Campo em branco devolve a linha ao estado "custo nao cadastrado". Gravar
    // zero seria mentira: significaria fornecedor de graca.
    $catalogo = Catalogo::factory()->comServico('scpc-bvs', [0 => 631])->create();
    $servico = Servico::firstWhere('codigo', 'scpc-bvs');

    editaServico($servico, custo: '3,70');
    expect($catalogo->precos()->first()->custo_cents)->toBe(370);

    editaServico($servico, custo: '');

    expect($catalogo->precos()->first()->custo_cents)->toBeNull();
});

it('poe o custo em todas as faixas do servico', function () {
    // O fornecedor cobra por consulta, nao pelo pacote: um custo por servico.
    $catalogo = Catalogo::factory()->comServico('scpc-bvs', [0 => 631, 90_000 => 493])->create();
    $servico = Servico::firstWhere('codigo', 'scpc-bvs');

    editaServico($servico, custo: '3,70');

    expect($servico->precos()->pluck('custo_cents')->unique()->all())->toBe([370])
        ->and($catalogo->precoDe('scpc-bvs', 0))->toBe(631)
        ->and($catalogo->precoDe('scpc-bvs', 90_000))->toBe(493);
});

/*
|--------------------------------------------------------------------------
| Preco por faixa
|--------------------------------------------------------------------------
*/

it('grava o preco de cada faixa', function () {
    $catalogo = Catalogo::factory()->comServico('scpc-bvs', [0 => 631, 90_000 => 493])->create();
    $servico = Servico::firstWhere('codigo', 'scpc-bvs');

    editaServico($servico, custo: '2,80', precos: [0 => '8,39', 90_000 => '6,20'])
        ->assertSessionHas('ok');

    expect($catalogo->precoDe('scpc-bvs', 0))->toBe(839)
        ->and($catalogo->precoDe('scpc-bvs', 90_000))->toBe(620);
});

it('aceita o dinheiro como o operador digita', function () {
    $catalogo = Catalogo::factory()->comServico('scpc-bvs', [0 => 631])->create();
    $servico = Servico::firstWhere('codigo', 'scpc-bvs');

    editaServico($servico, precos: [0 => 'R$ 1.234,56']);

    expect($catalogo->precoDe('scpc-bvs', 0))->toBe(123_456);
});

it('recusa o lote inteiro quando um preco fura o piso', function () {
    // Relatar prejuizo depois do fato nao impede ninguem de vender no negativo.
    // E gravar so os precos validos deixaria o operador achando que salvou tudo.
    $catalogo = Catalogo::factory()->comServico('scpc-bvs', [0 => 631, 90_000 => 493])->create();
    $servico = Servico::firstWhere('codigo', 'scpc-bvs');
    $catalogo->precos()->update(['custo_cents' => 280]);

    editaServico($servico, custo: '2,80', precos: [0 => '9,99', 90_000 => '3,00'])
        ->assertSessionHas('erro');

    expect($catalogo->precoDe('scpc-bvs', 0))->toBe(631)
        ->and($catalogo->precoDe('scpc-bvs', 90_000))->toBe(493);
});

it('ignora faixa que nao existe neste catalogo', function () {
    $catalogo = Catalogo::factory()->comServico('scpc-bvs', [0 => 631])->create();
    $servico = Servico::firstWhere('codigo', 'scpc-bvs');

    editaServico($servico, precos: [0 => '7,00', 999_999 => '1,00'])->assertSessionHas('ok');

    expect($catalogo->precoDe('scpc-bvs', 0))->toBe(700)
        ->and($catalogo->precos()->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Botao de situacao
|--------------------------------------------------------------------------
*/

it('liga e desliga o servico no clique', function () {
    // Interruptor na lista: alternar disponibilidade nao merece um formulario.
    $servico = Servico::factory()->create(['ativo' => true]);

    admin()->patch(route('catalogo.servicos.alternar', $servico))->assertSessionHas('ok');
    expect($servico->fresh()->ativo)->toBeFalse();

    admin()->patch(route('catalogo.servicos.alternar', $servico));
    expect($servico->fresh()->ativo)->toBeTrue();
});

it('nao deixa vendedor alternar servico', function () {
    $servico = Servico::factory()->create(['ativo' => true]);

    $this->actingAs(Staff::factory()->create(), 'staff')
        ->withSession(['versao_staff' => 1])
        ->patch(route('catalogo.servicos.alternar', $servico))
        ->assertForbidden();

    expect($servico->fresh()->ativo)->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Parametros comerciais
|--------------------------------------------------------------------------
*/

it('comeca com os parametros comerciais do PDD', function () {
    $catalogo = Catalogo::factory()->create();

    expect($catalogo->impostoRotulo())->toBe('8,6%')
        ->and($catalogo->margemAlvoRotulo())->toBe('30%')
        ->and($catalogo->comissaoBps())->toBe(1_000);
});

it('ajusta os parametros na pagina propria', function () {
    $catalogo = Catalogo::factory()->create();

    admin()->put(route('catalogo.parametros.salvar', $catalogo), [
        'imposto' => '26.8', 'margem_alvo' => '30', 'degrau_margem' => '3',
    ])->assertSessionHas('ok');

    expect($catalogo->fresh()->imposto_bps)->toBe(2_680)
        ->and($catalogo->fresh()->impostoRotulo())->toBe('26,8%')
        ->and($catalogo->fresh()->margem_alvo_bps)->toBe(3_000);
});

it('recusa aliquota de 100% ou mais', function () {
    $catalogo = Catalogo::factory()->create();

    admin()->put(route('catalogo.parametros.salvar', $catalogo), [
        'imposto' => '100', 'margem_alvo' => '30', 'degrau_margem' => '3',
    ])->assertSessionHasErrors('imposto');

    expect($catalogo->fresh()->imposto_bps)->toBe(860);
});

it('recusa escada que estoura 100% na faixa mais baixa', function () {
    // 60% de piso mais degraus de 15% passaria de 100% com imposto e comissao.
    $catalogo = Catalogo::factory()->comServico('scpc-bvs', [0 => 631, 7_500 => 594, 500_000 => 370])->create();

    admin()->put(route('catalogo.parametros.salvar', $catalogo), [
        'imposto' => '8.6', 'margem_alvo' => '60', 'degrau_margem' => '15',
    ])->assertSessionHasErrors('degrau_margem');

    expect($catalogo->fresh()->margem_alvo_bps)->toBe(3_000);
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
        ->assertSee('text-error-600', false);
});

it('nao inventa margem sem custo cadastrado', function () {
    Catalogo::factory()->comServico('scpc-bvs', [0 => 631])->create();

    // Sem custo nao ha percentual nenhum na matriz, nem zero.
    admin()->get(route('catalogo.tabela', ['visao' => 'margem']))
        ->assertOk()
        ->assertDontSee('0,0%');
});

it('cai na visao de venda quando a visao pedida nao existe', function () {
    Catalogo::factory()->comServico('scpc-bvs', [0 => 631])->create();

    admin()->get(route('catalogo.tabela', ['visao' => 'sei-la']))
        ->assertOk()
        ->assertSee("R$\u{00A0}6,31", false);
});

it('mantem a categoria ao trocar de visao', function () {
    Catalogo::factory()->comServico('scpc-bvs', [0 => 631])->create();

    $html = admin()->get(route('catalogo.tabela', ['categoria' => 'credito']))->getContent();

    preg_match('/data-abas="visoes".*?<\/div>/s', $html, $bloco);

    expect($bloco[0])->toContain('categoria=credito');
});

it('nao deixa vendedor ver custo nem mexer em parametro', function () {
    $catalogo = Catalogo::factory()->comServico('scpc-bvs', [0 => 631])->create();

    $vendedor = fn () => test()
        ->actingAs(Staff::factory()->create(), 'staff')
        ->withSession(['versao_staff' => 1]);

    $vendedor()->get(route('catalogo.tabela', ['visao' => 'margem']))->assertForbidden();
    $vendedor()->get(route('catalogo.parametros'))->assertForbidden();
    $vendedor()->put(route('catalogo.parametros.salvar', $catalogo), [
        'imposto' => '10', 'margem_alvo' => '30', 'degrau_margem' => '3',
    ])->assertForbidden();
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

    // Preco unitario cai conforme o cliente sobe de pacote.
    expect($catalogo->precoDe('scpc-bvs', 0))->toBeGreaterThan($catalogo->precoDe('scpc-bvs', 90_000))
        ->and($catalogo->precoDe('scpc-bvs', 90_000))->toBeGreaterThan($catalogo->precoDe('scpc-bvs', 500_000));
});

it('nunca deixa nenhuma faixa abaixo da margem alvo', function () {
    $catalogo = Catalogo::factory()
        ->comServico('scpc-bvs', [0 => 631, 7_500 => 594, 90_000 => 493, 500_000 => 370])
        ->comServico('vip-car', [0 => 5_530, 7_500 => 5_364, 90_000 => 4_896, 500_000 => 4_468])
        ->create(['margem_alvo_bps' => 3_000, 'degrau_margem_bps' => 300]);

    $catalogo->precos()->update(['custo_cents' => 280]);

    admin()->post(route('catalogo.precificar', $catalogo));

    foreach ($catalogo->precos()->get() as $preco) {
        expect(Margem::atinge(
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
