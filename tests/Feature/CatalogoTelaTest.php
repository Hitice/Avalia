<?php

use App\Models\Catalogo;
use App\Models\Servico;
use App\Models\Staff;
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
| A matriz, so de leitura
|--------------------------------------------------------------------------
*/

it('mostra o preco como texto, sem campo editavel', function () {
    // Edicao acontece na pagina do servico. A matriz serve para consultar.
    Catalogo::factory()->comServico('scpc-bvs', [0 => 631, 500_000 => 370])->create();

    admin()->get('/catalogo/tabela')
        ->assertOk()
        ->assertSee('scpc-bvs')
        ->assertSee("R$\u{00A0}6,31", false)
        ->assertSee("R$\u{00A0}3,70", false)
        ->assertDontSee('name="precos[', false);
});

it('leva ao cadastro do servico pelo botao da linha', function () {
    Catalogo::factory()->comServico('scpc-bvs', [0 => 631])->create();
    $servico = Servico::firstWhere('codigo', 'scpc-bvs');

    admin()->get('/catalogo/tabela')
        ->assertOk()
        ->assertSee(route('catalogo.servicos.editar', $servico), false)
        ->assertSee('Editar');
});

it('oferece as categorias na ordem credito, veicular e todos', function () {
    Catalogo::factory()->comServico('scpc-bvs', [0 => 631])->create();

    $html = admin()->get('/catalogo/tabela')->assertOk()->getContent();

    preg_match('/data-abas="categorias".*?<\/div>/s', $html, $bloco);
    preg_match_all('/>\s*(Crédito|Veicular|Todos)\s*</u', $bloco[0], $rotulos);

    expect($rotulos[1])->toBe(['Crédito', 'Veicular', 'Todos']);
});

it('filtra por categoria', function () {
    // Enquanto veicular estiver travado, credito e a unica categoria que abre,
    // e a matriz mostra so ela. A trava em si esta em SuprimidoTest.
    Catalogo::factory()
        ->comServico('scpc-bvs', [0 => 631])
        ->comServico('renajud', [0 => 1_055])
        ->create();

    Servico::where('codigo', 'renajud')->update(['categoria' => 'veicular']);

    admin()->get(route('catalogo.tabela', ['categoria' => 'credito']))
        ->assertOk()
        ->assertSee('scpc-bvs')
        ->assertDontSee('renajud');
});

it('ignora filtro de categoria invalido em vez de quebrar', function () {
    Catalogo::factory()->comServico('scpc-bvs', [0 => 631])->create();

    admin()->get(route('catalogo.tabela', ['categoria' => 'sei-la']))
        ->assertOk()
        ->assertSee('scpc-bvs');
});

it('mostra uma coluna de custo so, e nao uma por faixa', function () {
    // O fornecedor cobra por consulta: sete colunas iguais seriam ruido.
    $catalogo = Catalogo::factory()->comServico('scpc-bvs', [0 => 631, 90_000 => 493])->create();
    $catalogo->precos()->update(['custo_cents' => 280]);

    $html = admin()->get(route('catalogo.tabela', ['visao' => 'custo']))->assertOk()->getContent();

    expect(substr_count($html, "R$\u{00A0}2,80"))->toBe(1);
});

it('avisa quando nao ha catalogo nenhum', function () {
    // Sem tabela de precos a tela orienta em vez de mostrar uma matriz vazia.
    admin()->get('/catalogo/tabela')
        ->assertOk()
        ->assertSee('Não há tabela de preços cadastrada');
});

it('da a cada faixa o seu proprio rotulo no cabecalho', function () {
    Catalogo::factory()
        ->comServico('scpc-bvs', [0 => 631, 7_500 => 594, 90_000 => 493])
        ->create();

    $html = admin()->get('/catalogo/tabela')->assertOk()->getContent();

    expect(substr_count($html, 'Sem mínimo'))->toBe(1)
        ->and($html)->toContain("R$\u{00A0}75,00")
        ->and($html)->toContain("R$\u{00A0}900,00");
});

it('converte faixa para inteiro venha ela como for do banco', function () {
    // Driver de banco decide se bigint volta como int ou string. Em string,
    // `$faixa === 0` falha e o cabecalho troca "Sem minimo" por "R$ 0,00".
    $precos = new Illuminate\Database\Eloquent\Collection([
        new App\Models\Preco(['consumo_minimo_cents' => '90000']),
        new App\Models\Preco(['consumo_minimo_cents' => '0']),
        new App\Models\Preco(['consumo_minimo_cents' => '7500']),
        new App\Models\Preco(['consumo_minimo_cents' => '7500']),
    ]);

    expect(Catalogo::faixasDe($precos))->toBe([0, 7_500, 90_000]);
});

/*
|--------------------------------------------------------------------------
| Menu
|--------------------------------------------------------------------------
*/

it('mostra o catalogo no menu do admin e esconde do vendedor', function () {
    // Assercao no link, e nao na palavra: "Catálogo" tambem aparece no painel
    // de acompanhamento da construcao, que todo mundo ve.
    admin()->get('/')->assertOk()->assertSee('href="/catalogo"', false);

    $this->actingAs(Staff::factory()->create(), 'staff')
        ->withSession(['versao_staff' => 1])
        ->get('/')
        ->assertOk()
        ->assertDontSee('href="/catalogo"', false);
});
