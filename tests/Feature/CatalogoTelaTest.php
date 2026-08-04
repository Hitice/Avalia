<?php

use App\Models\Plano;
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
    // e margem — que sairiam junto com a tabela de precos.
    $this->actingAs(Staff::factory()->create(), 'staff')
        ->withSession(['versao_staff' => 1])
        ->get('/catalogo/versoes')
        ->assertForbidden();
});

it('nao deixa visitante nem empresa abrirem o catalogo', function () {
    $this->get('/catalogo/versoes')->assertRedirect(route('entrar'));
});

it('deixa admin e super abrirem o catalogo', function () {
    VersaoCatalogo::factory()->create(['rotulo' => 'Catálogo 04/2026']);

    $this->actingAs(Staff::factory()->admin()->create(), 'staff')
        ->withSession(['versao_staff' => 1])
        ->get('/catalogo/versoes')
        ->assertOk()
        ->assertSee('Catálogo 04/2026');

    $this->actingAs(Staff::factory()->super()->create(), 'staff')
        ->withSession(['versao_staff' => 1])
        ->get('/catalogo/versoes')
        ->assertOk();
});

/*
|--------------------------------------------------------------------------
| Listagem
|--------------------------------------------------------------------------
*/

it('avisa quando nao ha versao em vigor', function () {
    admin()->get('/catalogo/versoes')->assertSee('Nenhuma versao em vigor');
});

it('mostra a tabela de precos da versao com uma coluna por faixa', function () {
    $versao = VersaoCatalogo::factory()
        ->comServico('scpc-bvs', [0 => 631, 7_500 => 594, 500_000 => 370])
        ->create();

    // Rascunho: cada preco vira campo editavel com o valor preenchido.
    admin()->get(route('catalogo.versoes.mostrar', $versao))
        ->assertOk()
        ->assertSee('scpc-bvs')
        ->assertSee('Sem mínimo')
        ->assertSee('value="6,31"', false)
        ->assertSee('value="3,70"', false);
});

it('mostra o preco como texto depois que a versao entra em vigor', function () {
    $versao = VersaoCatalogo::factory()
        ->comServico('scpc-bvs', [0 => 631, 500_000 => 370])
        ->ativa()
        ->create();

    admin()->get(route('catalogo.versoes.mostrar', $versao))
        ->assertOk()
        ->assertSee("R$\u{00A0}6,31", false)
        ->assertSee("R$\u{00A0}3,70", false)
        ->assertDontSee('value="6,31"', false);
});

it('filtra a tabela por categoria', function () {
    $versao = VersaoCatalogo::factory()
        ->comServico('scpc-bvs', [0 => 631])
        ->comServico('renajud', [0 => 1_055])
        ->create();

    Servico::where('codigo', 'renajud')->update(['categoria' => 'veicular']);

    admin()->get(route('catalogo.versoes.mostrar', ['versao' => $versao, 'categoria' => 'veicular']))
        ->assertOk()
        ->assertSee('renajud')
        ->assertDontSee('scpc-bvs');
});

it('ignora filtro de categoria invalido em vez de quebrar', function () {
    $versao = VersaoCatalogo::factory()->comServico('scpc-bvs', [0 => 631])->create();

    admin()->get(route('catalogo.versoes.mostrar', ['versao' => $versao, 'categoria' => 'sei-la']))
        ->assertOk()
        ->assertSee('scpc-bvs');
});

/*
|--------------------------------------------------------------------------
| Ativacao
|--------------------------------------------------------------------------
*/

it('ativa a versao e congela os precos', function () {
    $versao = VersaoCatalogo::factory()->comServico('scpc-bvs', [0 => 631])->create();

    admin()->post(route('catalogo.versoes.ativar', $versao))
        ->assertRedirect()
        ->assertSessionHas('ok');

    expect($versao->fresh()->situacao)->toBe('ativa')
        ->and($versao->fresh()->podeEditar())->toBeFalse();
});

it('recusa ativar versao que ja esta em vigor', function () {
    $versao = VersaoCatalogo::factory()->comServico('scpc-bvs', [0 => 631])->ativa()->create();

    admin()->post(route('catalogo.versoes.ativar', $versao))->assertSessionHas('erro');

    expect(VersaoCatalogo::ativa()->count())->toBe(1);
});

it('recusa ativar versao sem preco nenhum', function () {
    // Ativar uma versao vazia deixaria toda consulta sem preco e a fatura sem
    // como fechar.
    $versao = VersaoCatalogo::factory()->create();

    admin()->post(route('catalogo.versoes.ativar', $versao))->assertSessionHas('erro');

    expect($versao->fresh()->situacao)->toBe('rascunho');
});

it('nao deixa vendedor ativar versao', function () {
    $versao = VersaoCatalogo::factory()->comServico('scpc-bvs', [0 => 631])->create();

    $this->actingAs(Staff::factory()->create(), 'staff')
        ->withSession(['versao_staff' => 1])
        ->post(route('catalogo.versoes.ativar', $versao))
        ->assertForbidden();

    expect($versao->fresh()->situacao)->toBe('rascunho');
});

/*
|--------------------------------------------------------------------------
| Menu
|--------------------------------------------------------------------------
*/

it('mostra o catalogo no menu do admin e esconde do vendedor', function () {
    admin()->get('/')->assertOk()->assertSee('Catálogo e planos');

    $this->actingAs(Staff::factory()->create(), 'staff')
        ->withSession(['versao_staff' => 1])
        ->get('/')
        ->assertOk()
        ->assertDontSee('Catálogo e planos');
});

it('conta os planos de cada versao na listagem', function () {
    $versao = VersaoCatalogo::factory()->comServico('scpc-bvs', [7_500 => 594])->create();
    Plano::factory()->count(2)->create(['versao_id' => $versao->id]);

    $resposta = admin()->get('/catalogo/versoes')->assertOk();

    expect($resposta->viewData('versoes')->first()->planos_count)->toBe(2);
});
