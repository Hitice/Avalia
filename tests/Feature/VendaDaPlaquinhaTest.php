<?php

use App\Enums\SituacaoEtiqueta;
use App\Models\Auditoria;
use App\Models\Etiqueta;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Corrigir a quem a venda pertence
|--------------------------------------------------------------------------
|
| O sistema descobre o vendedor pelo que aconteceu, e nao pelo que foi
| combinado. Quem vende passa a placa para outro cadastrar, e o credito sai
| errado sem ninguem errar nada.
|
*/

it('credita a venda a quem vendeu de verdade', function () {
    $quemCadastrou = Staff::factory()->admin()->create(['nome' => 'Pedro']);
    $quemVendeu = Staff::factory()->create(['papel' => 'vendedor', 'nome' => 'Warley']);

    $etiqueta = Etiqueta::factory()->ativa()->create(['vendedor_id' => $quemCadastrou->id]);

    admin()->put(route('plaquinhas.vendedor', $etiqueta), ['vendedor_id' => $quemVendeu->id])
        ->assertRedirect();

    expect($etiqueta->refresh()->vendedor_id)->toBe($quemVendeu->id);

    // O nome entra na trilha junto com o id: a conta pode ser removida depois, e
    // "vendedor_id 7" nao explica nada a quem le a auditoria um ano adiante.
    $trilha = Auditoria::where('acao', 'etiquetas.vendedor.trocado')->latest('id')->first();

    expect($trilha)->not->toBeNull()
        ->and($trilha->dados['para_nome'])->toBe('Warley');
});

it('aceita deixar a venda sem vendedor identificado', function () {
    // Venda de quem ja saiu da equipe existe, e "nao identificado" e mais
    // honesto que creditar a quem estiver por perto.
    $etiqueta = Etiqueta::factory()->ativa()->create(['vendedor_id' => Staff::factory()->create()->id]);

    admin()->put(route('plaquinhas.vendedor', $etiqueta), ['vendedor_id' => null])->assertRedirect();

    expect($etiqueta->refresh()->vendedor_id)->toBeNull();
});

it('recusa definir vendedor em plaquinha que nao foi vendida', function () {
    $emBranco = Etiqueta::factory()->create();

    admin()->put(route('plaquinhas.vendedor', $emBranco), ['vendedor_id' => Staff::factory()->create()->id]);

    expect($emBranco->refresh()->vendedor_id)->toBeNull();
});

it('nao deixa o vendedor escolher a propria comissao', function () {
    // Vendedor que reatribuisse venda escolheria o proprio repasse, e a
    // conferencia do mes deixaria de ter valor.
    $vendedor = Staff::factory()->create(['papel' => 'vendedor']);
    $etiqueta = Etiqueta::factory()->ativa()->create();

    test()->actingAs($vendedor, 'staff')->withSession(['versao_staff' => 1])
        ->put(route('plaquinhas.vendedor', $etiqueta), ['vendedor_id' => $vendedor->id])
        ->assertForbidden();

    expect($etiqueta->refresh()->vendedor_id)->not->toBe($vendedor->id);
});

/*
|--------------------------------------------------------------------------
| Cancelar a venda sem tirar a placa de campo
|--------------------------------------------------------------------------
*/

it('tira a venda da apuracao e deixa a plaquinha no ar', function () {
    // A placa da propria Avalia aponta para a avaliacao dela no Google e precisa
    // continuar funcionando. Ela so nao e receita de ninguem.
    $etiqueta = Etiqueta::factory()->ativa('https://g.page/r/exemplo/review')->create([
        'vendedor_id' => Staff::factory()->create()->id,
    ]);

    admin()->delete(route('plaquinhas.cancelar-venda', $etiqueta))->assertRedirect();

    $etiqueta->refresh();

    expect($etiqueta->vendida_em)->toBeNull()
        ->and($etiqueta->vendedor_id)->toBeNull()
        ->and($etiqueta->valor_cents)->toBeNull()
        ->and($etiqueta->custo_cents)->toBeNull()

        // O que importa: a placa continua em campo e redirecionando.
        ->and($etiqueta->situacao)->toBe(SituacaoEtiqueta::Ativa)
        ->and($etiqueta->destino)->toBe('https://g.page/r/exemplo/review')
        ->and($etiqueta->redireciona())->toBeTrue();
});

it('deixa a placa da casa sem vencimento', function () {
    // Sem cliente a cobrar nem renovacao a vender, ela nao pode entrar no aviso
    // de vencimento: seria a gente cobrando a gente de renovar a propria placa.
    $etiqueta = Etiqueta::factory()->ativa()->create(['avisada_em' => now()]);

    admin()->delete(route('plaquinhas.cancelar-venda', $etiqueta));

    $etiqueta->refresh();

    expect($etiqueta->vence_em)->toBeNull()
        ->and($etiqueta->avisada_em)->toBeNull()
        ->and($etiqueta->estado())->toBe('ativa')
        ->and(Etiqueta::avisarEm(now()->addDays(15))->count())->toBe(0);
});

it('guarda na trilha o que a venda cancelada valia', function () {
    // Sem isso, um repasse antigo conferido contra o painel de hoje nao teria
    // como ser explicado.
    $vendedor = Staff::factory()->create(['papel' => 'vendedor']);
    $etiqueta = Etiqueta::factory()->ativa()->create([
        'vendedor_id' => $vendedor->id,
        'valor_cents' => 8_990,
        'custo_cents' => 500,
    ]);

    admin()->delete(route('plaquinhas.cancelar-venda', $etiqueta));

    $trilha = Auditoria::where('acao', 'etiquetas.venda.cancelada')->latest('id')->first();

    expect($trilha)->not->toBeNull()
        ->and($trilha->dados['valor_cents'])->toBe(8_990)
        ->and($trilha->dados['custo_cents'])->toBe(500)
        ->and($trilha->dados['vendedor_id'])->toBe($vendedor->id);
});

it('some da apuracao de vendas depois de cancelada', function () {
    Staff::factory()->admin()->create(['email' => config('etiquetas.socios')[0]]);
    Staff::factory()->admin()->create(['email' => config('etiquetas.socios')[1]]);

    $vendedor = Staff::factory()->create(['papel' => 'vendedor']);
    $fica = Etiqueta::factory()->ativa()->create(['vendedor_id' => $vendedor->id]);
    $sai = Etiqueta::factory()->ativa()->create(['vendedor_id' => $vendedor->id]);

    expect(admin()->get(route('plaquinhas.vendas'))->viewData('placas'))->toBe(2);

    admin()->delete(route('plaquinhas.cancelar-venda', $sai));

    $tela = admin()->get(route('plaquinhas.vendas'));

    expect($tela->viewData('placas'))->toBe(1)
        ->and($tela->viewData('totais')['bruto'])->toBe((int) $fica->valor_cents);
});

it('nao deixa o vendedor cancelar venda', function () {
    $vendedor = Staff::factory()->create(['papel' => 'vendedor']);
    $etiqueta = Etiqueta::factory()->ativa()->create();

    test()->actingAs($vendedor, 'staff')->withSession(['versao_staff' => 1])
        ->delete(route('plaquinhas.cancelar-venda', $etiqueta))
        ->assertForbidden();

    expect($etiqueta->refresh()->vendida_em)->not->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Os campos que faltavam na ficha
|--------------------------------------------------------------------------
*/

it('grava cliente, contato e valor pela ficha', function () {
    // O controller aceitava os tres e a tela nunca os desenhou: dava para vender
    // a placa sem jeito de dizer para quem, e o valor caia sempre na tabela.
    $etiqueta = Etiqueta::factory()->create();

    admin()->put(route('etiquetas.apontar', $etiqueta), ['vendedor_id' => App\Models\Staff::factory()->create(['papel' => 'vendedor'])->id, 
        'destino' => 'padariadoze.com.br',
        'cliente_nome' => 'Padaria do Zé',
        'cliente_contato' => '31999998888',
        'valor' => '129,90',
    ])->assertRedirect();

    $etiqueta->refresh();

    expect($etiqueta->cliente_nome)->toBe('Padaria do Zé')
        ->and($etiqueta->cliente_contato)->toBe('31999998888')
        ->and($etiqueta->valor_cents)->toBe(12_990);
});

it('oferece um botao Editar por linha na lista', function () {
    // Padrao da casa: um botao Editar por linha. Link em texto nao se anuncia
    // como a acao da linha.
    $etiqueta = Etiqueta::factory()->ativa()->create();

    admin()->get(route('etiquetas.index'))
        ->assertOk()
        ->assertSee(route('etiquetas.ficha', $etiqueta), false)
        ->assertSee('>Editar</a>', false);
});
