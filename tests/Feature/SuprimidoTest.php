<?php

use App\Enums\Categoria;
use App\Models\Catalogo;
use App\Models\Servico;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Veicular sob cadeado
|--------------------------------------------------------------------------
|
| Veicular esta precificado mas o contrato com o fornecedor nao fechou. Os
| numeros sao estimativa, e estimativa exibida sem aviso vira proposta. Fora
| do catalogo, entao, ate a liberacao.
|
*/

it('sabe qual familia esta suprimida', function () {
    expect(Categoria::Veicular->suprimida())->toBeTrue()
        ->and(Categoria::Credito->suprimida())->toBeFalse();
});

it('trava a aba de veicular e a de todos', function () {
    Catalogo::factory()->comServico('scpc-bvs', [0 => 631])->create();

    $html = admin()->get(route('catalogo.tabela'))->assertOk()->getContent();

    preg_match('/data-abas="categorias".*?<\/div>\s*<\/div>/s', $html, $bloco);

    // Credito navega; as outras duas viram texto com cadeado.
    expect(substr_count($bloco[0], 'segmento-travado'))->toBe(2)
        ->and(substr_count($bloco[0], 'class="cadeado"'))->toBe(2)
        ->and($bloco[0])->toContain('categoria=credito');
});

it('nao abre veicular nem digitando na URL', function () {
    // Aba travada que ainda responde por endereco nao e trava, e desenho.
    Catalogo::factory()
        ->comServico('scpc-bvs', [0 => 631])
        ->comServico('vip-car', [0 => 5_530])
        ->create();

    Servico::where('codigo', 'vip-car')->update(['categoria' => 'veicular']);

    admin()->get(route('catalogo.tabela', ['categoria' => 'veicular']))
        ->assertOk()
        ->assertSee('scpc-bvs')
        ->assertDontSee('vip-car')
        ->assertDontSee("R$\u{00A0}55,30", false);
});

it('nao deixa veicular vazar pela visao sem filtro', function () {
    Catalogo::factory()
        ->comServico('scpc-bvs', [0 => 631])
        ->comServico('vip-car', [0 => 5_530])
        ->create();

    Servico::where('codigo', 'vip-car')->update(['categoria' => 'veicular']);
    Catalogo::vigente()->precos()->update(['custo_cents' => 2_800]);

    foreach (['venda', 'custo', 'margem'] as $visao) {
        admin()->get(route('catalogo.tabela', ['visao' => $visao]))
            ->assertOk()
            ->assertDontSee('vip-car');
    }
});

it('mostra o servico veicular na lista, com cadeado', function () {
    // A administracao precisa saber que existe e o que falta liberar. O que
    // nao aparece la e numero: a lista nao tem preco nenhum.
    Servico::factory()->create(['nome' => 'Consulta veicular', 'categoria' => 'veicular']);
    Servico::factory()->create(['nome' => 'Relatorio de credito', 'categoria' => 'credito']);

    $html = admin()->get(route('catalogo.servicos.index'))->assertOk()->getContent();

    expect($html)->toContain('Consulta veicular')
        ->and(substr_count($html, 'class="cadeado"'))->toBe(1);
});

it('mantem o valor editavel na pagina do servico, com aviso', function () {
    // Suprimir na vitrine nao pode impedir de manter a estimativa.
    Catalogo::factory()->comServico('vip-car', [0 => 5_530])->create();
    $servico = Servico::firstWhere('codigo', 'vip-car');
    $servico->update(['categoria' => 'veicular']);

    admin()->get(route('catalogo.servicos.editar', $servico))
        ->assertOk()
        ->assertSee('55,30')
        ->assertSee('Preço, custo e margem são estimativa', false);
});
