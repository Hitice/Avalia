<?php

use App\Models\Etiqueta;
use App\Models\Negocio;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function vender(Staff $quem, Etiqueta $placa, array $dados = []): void
{
    test()->actingAs($quem, 'staff')->withSession(['versao_staff' => 1]);

    app(App\Actions\Etiquetas\VenderEtiqueta::class)($placa, array_merge([
        'destino' => 'https://exemplo.com.br', 'titulo' => null,
        'cliente_nome' => 'Padaria do Zé', 'cliente_contato' => '(34) 99999-0001', 'valor_cents' => null,
    ], $dados));
}

it('cadastra o cliente na base ao vender, e aponta a placa para ele', function () {
    // A venda E o cadastro. O link de indicacao por vendedor fingia fazer isso e
    // nem lia o codigo que mandava na URL.
    $warley = Staff::factory()->create(['papel' => 'vendedor']);
    $placa = Etiqueta::factory()->create(['vendida_em' => null]);

    vender($warley, $placa);

    $negocio = Negocio::sole();

    expect($negocio->nome)->toBe('Padaria do Zé')
        ->and($negocio->whatsapp)->toBe('34999990001')
        ->and($negocio->vendedor_id)->toBe($warley->id)
        ->and($negocio->origem)->toBe('venda')
        ->and($placa->fresh()->negocio_id)->toBe($negocio->id);
});

it('nao duplica o cliente que compra a segunda placa', function () {
    // Pelo telefone, e nao pelo nome: nome de loja repete, telefone nao.
    $warley = Staff::factory()->create(['papel' => 'vendedor']);
    [$a, $b] = Etiqueta::factory()->count(2)->create(['vendida_em' => null]);

    vender($warley, $a);
    vender($warley, $b, ['cliente_nome' => 'PADARIA DO ZE']);

    expect(Negocio::count())->toBe(1)
        ->and(Negocio::sole()->etiquetas()->count())->toBe(2);
});

it('acha pelo nome quando nao ha telefone, e nao cadastra sem nome', function () {
    $warley = Staff::factory()->create(['papel' => 'vendedor']);
    [$a, $b, $c] = Etiqueta::factory()->count(3)->create(['vendida_em' => null]);

    vender($warley, $a, ['cliente_contato' => '@padaria']);
    vender($warley, $b, ['cliente_contato' => null]);
    vender($warley, $c, ['cliente_nome' => null, 'cliente_contato' => null]);

    expect(Negocio::count())->toBe(1)
        ->and($c->fresh()->negocio_id)->toBeNull();
});

it('acha a placa pelo telefone do cliente na busca', function () {
    $warley = Staff::factory()->create(['papel' => 'vendedor']);
    $placa = Etiqueta::factory()->create(['vendida_em' => null, 'codigo' => 'BUSCA1']);
    vender($warley, $placa);

    $html = admin()->get(route('etiquetas.index', ['busca' => '99999-0001']))->assertOk()->getContent();
    expect($html)->toContain('BUSCA1');

    // E por um numero que nao e de ninguem, nada.
    $nada = admin()->get(route('etiquetas.index', ['busca' => '00000000']))->assertOk()->getContent();
    expect($nada)->not->toContain('BUSCA1');
});

it('tirou da tela do estoque o link de cadastro por vendedor', function () {
    $html = admin()->get(route('sales.estoque'))->assertOk()->getContent();

    expect($html)->not->toContain('Link para mandar ao cliente')
        ->and($html)->not->toContain('Meu link de cadastro');
});
