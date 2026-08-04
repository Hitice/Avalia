<?php

use App\Models\FranquiaPlano;
use App\Models\Plano;
use App\Models\Servico;
use App\Models\Staff;
use App\Models\VersaoCatalogo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/** Versao com dois servicos precificados em duas faixas. */
function catalogo(): VersaoCatalogo
{
    return VersaoCatalogo::factory()
        ->comServico('scpc-bvs', [0 => 631, 90_000 => 493])
        ->comServico('renajud', [0 => 1_055, 90_000 => 896])
        ->create();
}

/*
|--------------------------------------------------------------------------
| Acesso
|--------------------------------------------------------------------------
*/

it('nao deixa vendedor mexer em plano', function () {
    $plano = Plano::factory()->create();

    $vendedor = fn () => test()
        ->actingAs(Staff::factory()->create(), 'staff')
        ->withSession(['versao_staff' => 1]);

    $vendedor()->get('/catalogo')->assertForbidden();
    $vendedor()->get(route('catalogo.planos.criar'))->assertForbidden();
    $vendedor()->post(route('catalogo.planos.salvar'), [])->assertForbidden();
    $vendedor()->put(route('catalogo.planos.atualizar', $plano), [])->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| Cadastro
|--------------------------------------------------------------------------
*/

it('cria plano convertendo o dinheiro digitado em centavos', function () {
    $versao = catalogo();

    admin()->post(route('catalogo.planos.salvar'), [
        'nome' => 'Plano 900',
        'versao_id' => $versao->id,
        'mensalidade' => '79,90',
        'consumo_minimo' => '900,00',
        'ativo' => '1',
    ])->assertRedirect();

    $plano = Plano::firstWhere('nome', 'Plano 900');

    expect($plano->mensalidade_cents)->toBe(7_990)
        ->and($plano->consumo_minimo_cents)->toBe(90_000)
        ->and($plano->ativo)->toBeTrue()
        ->and($plano->pctComissao())->toBe(20);
});

it('aceita valor com separador de milhar', function () {
    $versao = VersaoCatalogo::factory()->comServico('scpc-bvs', [500_000 => 370])->create();

    admin()->post(route('catalogo.planos.salvar'), [
        'nome' => 'Plano 5000',
        'versao_id' => $versao->id,
        'mensalidade' => '1.234,56',
        'consumo_minimo' => '5.000,00',
    ])->assertRedirect();

    $plano = Plano::firstWhere('nome', 'Plano 5000');

    expect($plano->mensalidade_cents)->toBe(123_456)
        ->and($plano->consumo_minimo_cents)->toBe(500_000)
        ->and($plano->pctComissao())->toBe(15);
});

it('recusa consumo minimo que nao e faixa da versao', function () {
    // R$ 300 nao existe no catalogo: o plano ficaria sem coluna de preco e o
    // erro so apareceria no fechamento da fatura.
    $versao = catalogo();

    admin()->post(route('catalogo.planos.salvar'), [
        'nome' => 'Plano torto',
        'versao_id' => $versao->id,
        'mensalidade' => '79,90',
        'consumo_minimo' => '300,00',
    ])->assertSessionHasErrors('consumo_minimo');

    expect(Plano::count())->toBe(0);
});

it('recusa versao sem preco nenhum', function () {
    $versao = VersaoCatalogo::factory()->create();

    admin()->post(route('catalogo.planos.salvar'), [
        'nome' => 'Plano orfao',
        'versao_id' => $versao->id,
        'mensalidade' => '79,90',
        'consumo_minimo' => '0,00',
    ])->assertSessionHasErrors('versao_id');
});

it('recusa nome repetido, mas deixa salvar o proprio nome na edicao', function () {
    $versao = catalogo();
    Plano::factory()->create(['nome' => 'Plano 900', 'versao_id' => $versao->id]);
    $outro = Plano::factory()->create(['nome' => 'Plano 75', 'versao_id' => $versao->id]);

    admin()->post(route('catalogo.planos.salvar'), [
        'nome' => 'Plano 900',
        'versao_id' => $versao->id,
        'mensalidade' => '79,90',
        'consumo_minimo' => '0,00',
    ])->assertSessionHasErrors('nome');

    admin()->put(route('catalogo.planos.atualizar', $outro), [
        'nome' => 'Plano 75',
        'versao_id' => $versao->id,
        'mensalidade' => '89,90',
        'consumo_minimo' => '0,00',
    ])->assertSessionHasNoErrors();

    expect($outro->fresh()->mensalidade_cents)->toBe(8_990);
});

it('lista os planos em ordem crescente de valor, nao alfabetica', function () {
    // Por nome, "Consumo minimo R$ 1.500,00" viria antes de "R$ 200,00".
    $versao = VersaoCatalogo::factory()
        ->comServico('scpc-bvs', [0 => 631, 20_000 => 558, 150_000 => 463])
        ->create();

    foreach ([150_000, 0, 20_000] as $faixa) {
        Plano::factory()->create([
            'nome' => 'Consumo mínimo R$ '.App\Support\Dinheiro::numero($faixa),
            'versao_id' => $versao->id,
            'consumo_minimo_cents' => $faixa,
        ]);
    }

    $listados = admin()->get('/catalogo')->assertOk()->viewData('planos');

    expect($listados->pluck('consumo_minimo_cents')->all())->toBe([0, 20_000, 150_000]);
});

it('nao consulta o banco uma vez por plano para validar a faixa', function () {
    // A 447 ms por ida e volta ao banco remoto, um SELECT por linha da lista
    // custava segundos de tela.
    $versao = VersaoCatalogo::factory()
        ->comServico('scpc-bvs', [0 => 631, 20_000 => 558, 150_000 => 463])
        ->create();

    Plano::factory()->count(6)->create(['versao_id' => $versao->id, 'consumo_minimo_cents' => 0]);

    DB::enableQueryLog();
    admin()->get('/catalogo')->assertOk();
    $consultas = count(DB::getQueryLog());
    DB::disableQueryLog();

    // Sessao, conta e as duas consultas da tela. O numero nao pode crescer
    // junto com a quantidade de planos.
    expect($consultas)->toBeLessThan(8);
});

it('marca na listagem o plano cuja faixa saiu do catalogo', function () {
    $versao = catalogo();
    Plano::factory()->consumoMinimo(300)->create(['nome' => 'Plano torto', 'versao_id' => $versao->id]);

    admin()->get('/catalogo')
        ->assertOk()
        ->assertSee('faixa fora do catalogo');
});

/*
|--------------------------------------------------------------------------
| Franquia
|--------------------------------------------------------------------------
*/

it('mostra na edicao so os servicos precificados na faixa do plano', function () {
    $versao = VersaoCatalogo::factory()
        ->comServico('scpc-bvs', [0 => 631, 90_000 => 493])
        ->comServico('renajud', [0 => 1_055])
        ->create();

    $plano = Plano::factory()->consumoMinimo(900)->create(['versao_id' => $versao->id]);

    // renajud so tem preco sem minimo, entao nao entra neste plano.
    admin()->get(route('catalogo.planos.editar', $plano))
        ->assertOk()
        ->assertSee('scpc-bvs')
        ->assertDontSee('renajud')
        ->assertSee("R$\u{00A0}4,93", false);
});

it('grava a franquia de cada servico', function () {
    $plano = Plano::factory()->consumoMinimo(900)->create(['versao_id' => catalogo()->id]);
    $scpc = Servico::firstWhere('codigo', 'scpc-bvs');

    admin()->put(route('catalogo.planos.franquias', $plano), [
        'franquias' => [$scpc->id => 50],
    ])->assertSessionHas('ok');

    expect($plano->franquiaDe('scpc-bvs'))->toBe(50);
});

it('apaga a linha quando a quantidade volta a zero', function () {
    // Ausencia e zero significam a mesma coisa no faturamento; guardar as duas
    // representacoes so criaria duvida na apuracao.
    $plano = Plano::factory()->consumoMinimo(900)->create(['versao_id' => catalogo()->id]);
    $scpc = Servico::firstWhere('codigo', 'scpc-bvs');

    admin()->put(route('catalogo.planos.franquias', $plano), ['franquias' => [$scpc->id => 20]]);
    expect(FranquiaPlano::count())->toBe(1);

    admin()->put(route('catalogo.planos.franquias', $plano), ['franquias' => [$scpc->id => 0]]);

    expect(FranquiaPlano::count())->toBe(0)
        ->and($plano->franquiaDe('scpc-bvs'))->toBe(0);
});

it('ignora franquia de servico que nao esta na faixa do plano', function () {
    // Defesa contra id chutado no formulario: so servico realmente disponivel
    // no plano pode receber franquia.
    $versao = VersaoCatalogo::factory()
        ->comServico('scpc-bvs', [90_000 => 493])
        ->comServico('renajud', [0 => 1_055])
        ->create();

    $plano = Plano::factory()->consumoMinimo(900)->create(['versao_id' => $versao->id]);
    $renajud = Servico::firstWhere('codigo', 'renajud');

    admin()->put(route('catalogo.planos.franquias', $plano), [
        'franquias' => [$renajud->id => 999],
    ]);

    expect(FranquiaPlano::count())->toBe(0);
});

it('recusa franquia negativa', function () {
    $plano = Plano::factory()->consumoMinimo(900)->create(['versao_id' => catalogo()->id]);
    $scpc = Servico::firstWhere('codigo', 'scpc-bvs');

    admin()->put(route('catalogo.planos.franquias', $plano), [
        'franquias' => [$scpc->id => -5],
    ])->assertSessionHasErrors('franquias.'.$scpc->id);
});
