<?php

use App\Models\Etiqueta;
use App\Models\LoteEtiqueta;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| O estoque que o vendedor precisa ver para vender
|--------------------------------------------------------------------------
|
| A tiragem nasce com o dono do admin que gerou o lote. Enquanto a lista era
| filtrada so por dono, o vendedor abria a tela vazia e nao tinha o que vender.
|
*/

function vendedorLogado(string $nome = 'Warley'): Staff
{
    $vendedor = Staff::factory()->create(['papel' => 'vendedor', 'nome' => $nome]);

    test()->actingAs($vendedor, 'staff')->withSession(['versao_staff' => 1]);

    return $vendedor;
}

it('mostra o estoque em branco ao vendedor', function () {
    $admin = Staff::factory()->admin()->create();
    $emBranco = Etiqueta::factory()->create(['dono_tipo' => 'staff', 'dono_id' => $admin->id]);

    vendedorLogado();

    $lista = test()->get(route('etiquetas.index'))->assertOk()->viewData('etiquetas');

    expect($lista->pluck('id')->all())->toContain($emBranco->id);
});

it('deixa o vendedor apontar uma placa do estoque', function () {
    $admin = Staff::factory()->admin()->create();
    $etiqueta = Etiqueta::factory()->create(['dono_tipo' => 'staff', 'dono_id' => $admin->id]);

    $warley = vendedorLogado();

    test()->put(route('etiquetas.apontar', $etiqueta), [
        'destino' => 'padariadoze.com.br',
        'cliente_nome' => 'Padaria do Zé',
    ])->assertRedirect();

    // E a venda fica creditada a ele, que e o que faz a comissao sair certa.
    expect($etiqueta->refresh()->vendedor_id)->toBe($warley->id);
});

it('mantem a placa visivel para quem a vendeu', function () {
    // Depois da venda ela deixa de ser estoque. Se so o estoque fosse visivel,
    // o vendedor perderia de vista o proprio cliente no instante da venda.
    $admin = Staff::factory()->admin()->create();
    $warley = vendedorLogado();

    $minha = Etiqueta::factory()->ativa()->create([
        'dono_tipo' => 'staff', 'dono_id' => $admin->id, 'vendedor_id' => $warley->id,
    ]);

    $lista = test()->get(route('etiquetas.index'))->viewData('etiquetas');

    expect($lista->pluck('id')->all())->toContain($minha->id);
    test()->get(route('etiquetas.ficha', $minha))->assertOk();
});

it('esconde do vendedor a venda de outro vendedor', function () {
    // Ele nao atende aquele cliente e a comissao nao e dele, entao o destino
    // daquela placa nao e assunto seu.
    $outro = Staff::factory()->create(['papel' => 'vendedor']);
    $doOutro = Etiqueta::factory()->ativa()->create(['vendedor_id' => $outro->id]);

    vendedorLogado();

    $lista = test()->get(route('etiquetas.index'))->viewData('etiquetas');

    expect($lista->pluck('id')->all())->not->toContain($doOutro->id);
    test()->get(route('etiquetas.ficha', $doOutro))->assertNotFound();
});

it('mostra ao vendedor a campanha que tem estoque', function () {
    // Sem a campanha ele nao acha o pacote nem filtra a lista.
    $admin = Staff::factory()->admin()->create();
    $lote = LoteEtiqueta::create([
        'codigo' => 'LT-0001', 'titulo' => 'Campanha 1', 'quantidade' => 1, 'tipo' => 'qr', 'staff_id' => $admin->id,
    ]);
    Etiqueta::factory()->create([
        'lote_id' => $lote->id, 'sequencia' => 1, 'dono_tipo' => 'staff', 'dono_id' => $admin->id,
    ]);

    vendedorLogado();

    expect(test()->get(route('etiquetas.index'))->viewData('lotes')->pluck('id')->all())
        ->toContain($lote->id);
});

it('continua escondendo tudo de cliente e produtor', function () {
    // A abertura e para a EQUIPE. Para cliente e produtor nao existe estoque da
    // casa, e placa em branco de outra pessoa nao lhes diz respeito.
    $admin = Staff::factory()->admin()->create();
    $emBranco = Etiqueta::factory()->create(['dono_tipo' => 'staff', 'dono_id' => $admin->id]);

    $cliente = App\Models\Cliente::factory()->create();

    test()->actingAs($cliente, 'empresa')->withSession(['versao_empresa' => 1]);

    $lista = test()->get(route('etiquetas.index'))->assertOk()->viewData('etiquetas');

    expect($lista->pluck('id')->all())->not->toContain($emBranco->id);
});
