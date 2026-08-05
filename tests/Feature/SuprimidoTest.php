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
| numeros sao estimativa, e estimativa exibida sem aviso vira proposta.
|
*/

it('sabe qual familia esta suprimida', function () {
    expect(Categoria::Veicular->suprimida())->toBeTrue()
        ->and(Categoria::Credito->suprimida())->toBeFalse();
});

it('esconde os precos de veicular na matriz e poe o cadeado', function () {
    Catalogo::factory()
        ->comServico('scpc-bvs', [0 => 631])
        ->comServico('vip-car', [0 => 5_530])
        ->create();

    Servico::where('codigo', 'vip-car')->update(['categoria' => 'veicular']);

    $html = admin()->get(route('catalogo.tabela'))->assertOk()->getContent();

    // A linha continua visivel: a administracao precisa saber que existe.
    expect($html)->toContain('vip-car')
        ->toContain('cadeado')
        ->toContain('suprimido')
        // Credito segue mostrando numero; veicular nao.
        ->toContain("R$\u{00A0}6,31")
        ->not->toContain("R$\u{00A0}55,30");
});

it('esconde tambem o custo e a margem de veicular', function () {
    $catalogo = Catalogo::factory()->comServico('vip-car', [0 => 5_530])->create();
    Servico::where('codigo', 'vip-car')->update(['categoria' => 'veicular']);
    $catalogo->precos()->update(['custo_cents' => 2_800]);

    foreach (['custo', 'margem'] as $visao) {
        $html = admin()->get(route('catalogo.tabela', ['visao' => $visao]))->assertOk()->getContent();

        expect($html)->not->toContain("R$\u{00A0}28,00")
            ->and($html)->toContain('suprimido');
    }
});

it('poe o cadeado na lista de servicos', function () {
    Servico::factory()->create(['nome' => 'Consulta veicular', 'categoria' => 'veicular']);
    Servico::factory()->create(['nome' => 'Relatorio de credito', 'categoria' => 'credito']);

    $html = admin()->get(route('catalogo.servicos.index'))->assertOk()->getContent();

    expect(substr_count($html, 'class="cadeado"'))->toBe(1);
});

it('mantem o valor editavel na pagina do servico, com aviso', function () {
    // Suprimir na vitrine nao pode impedir de manter a estimativa.
    Catalogo::factory()->comServico('vip-car', [0 => 5_530])->create();
    $servico = Servico::firstWhere('codigo', 'vip-car');
    $servico->update(['categoria' => 'veicular']);

    admin()->get(route('catalogo.servicos.editar', $servico))
        ->assertOk()
        ->assertSee('55,30')
        ->assertSee('Preco, custo e margem sao estimativa', false);
});
