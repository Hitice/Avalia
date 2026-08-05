<?php

use App\Models\Catalogo;
use App\Models\Plano;
use App\Models\Preco;
use App\Models\Servico;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Acesso
|--------------------------------------------------------------------------
*/

it('nao deixa vendedor mexer em servico', function () {
    $servico = Servico::factory()->create();

    $vendedor = fn () => test()
        ->actingAs(Staff::factory()->create(), 'staff')
        ->withSession(['versao_staff' => 1]);

    $vendedor()->get(route('catalogo.servicos.index'))->assertForbidden();
    $vendedor()->post(route('catalogo.servicos.salvar'), [])->assertForbidden();
    $vendedor()->put(route('catalogo.servicos.atualizar', $servico), [])->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| Criacao
|--------------------------------------------------------------------------
*/

it('cria o servico ja precificado em todas as faixas', function () {
    // Servico sem preco nao apareceria na matriz, que e o unico lugar onde se
    // edita preco: nasceria invisivel e inalcancavel.
    Catalogo::factory()->comServico('scpc-bvs', [0 => 631, 7_500 => 594, 90_000 => 493])->create();

    admin()->post(route('catalogo.servicos.salvar'), [
        'codigo' => 'score-positivo',
        'nome' => 'Relatório Score Positivo',
        'categoria' => 'credito',
        'preco_base' => '9,93',
        'ativo' => '1',
    ])->assertRedirect(route('catalogo.servicos.index'));

    $servico = Servico::firstWhere('codigo', 'score-positivo');

    expect($servico->nome)->toBe('Relatório Score Positivo')
        ->and($servico->ativo)->toBeTrue()
        ->and($servico->exige_liberacao)->toBeFalse()
        ->and($servico->precos()->count())->toBe(3)
        ->and($servico->precos()->pluck('preco_cents')->unique()->all())->toBe([993]);
});

it('normaliza o codigo digitado', function () {
    Catalogo::factory()->comServico('scpc-bvs', [0 => 631])->create();

    admin()->post(route('catalogo.servicos.salvar'), [
        'codigo' => 'Consulta Veicular Completa',
        'nome' => 'Consulta veicular',
        'categoria' => 'veicular',
        'preco_base' => '21,56',
    ]);

    expect(Servico::firstWhere('codigo', 'consulta-veicular-completa'))->not->toBeNull();
});

it('recusa codigo repetido', function () {
    Catalogo::factory()->comServico('scpc-bvs', [0 => 631])->create();

    admin()->post(route('catalogo.servicos.salvar'), [
        'codigo' => 'scpc-bvs',
        'nome' => 'Outro qualquer',
        'categoria' => 'credito',
        'preco_base' => '1,00',
    ])->assertSessionHasErrors('codigo');

    expect(Servico::count())->toBe(1);
});

it('recusa criar servico sem preco inicial', function () {
    Catalogo::factory()->comServico('scpc-bvs', [0 => 631])->create();

    admin()->post(route('catalogo.servicos.salvar'), [
        'codigo' => 'sem-preco',
        'nome' => 'Sem preco',
        'categoria' => 'credito',
    ])->assertSessionHasErrors('preco_base_cents');

    expect(Servico::count())->toBe(1);
});

it('nao cria servico quando o catalogo nao tem faixa nenhuma', function () {
    Catalogo::factory()->create();

    admin()->post(route('catalogo.servicos.salvar'), [
        'codigo' => 'orfao',
        'nome' => 'Orfao',
        'categoria' => 'credito',
        'preco_base' => '1,00',
    ])->assertSessionHas('erro');

    expect(Servico::count())->toBe(0)
        ->and(Preco::count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Edicao
|--------------------------------------------------------------------------
*/

it('renomeia e troca a categoria', function () {
    $servico = Servico::factory()->create(['codigo' => 'renajud', 'categoria' => 'credito']);

    admin()->put(route('catalogo.servicos.atualizar', $servico), [
        'nome' => 'RenaJud',
        'categoria' => 'veicular',
        'ativo' => '1',
    ])->assertRedirect();

    expect($servico->fresh()->nome)->toBe('RenaJud')
        ->and($servico->fresh()->categoria)->toBe(App\Enums\Categoria::Veicular);
});

it('nao deixa trocar o codigo depois de criado', function () {
    // Franquia de plano, relatorio e integracao acham o servico pelo codigo.
    $servico = Servico::factory()->create(['codigo' => 'scpc-bvs']);

    admin()->put(route('catalogo.servicos.atualizar', $servico), [
        'codigo' => 'codigo-forjado',
        'nome' => 'SCPC BVS',
        'categoria' => 'credito',
        'ativo' => '1',
    ]);

    expect($servico->fresh()->codigo)->toBe('scpc-bvs');
});

it('desativa o servico sem apagar nada', function () {
    $catalogo = Catalogo::factory()->comServico('scpc-bvs', [90_000 => 493])->create();
    $servico = Servico::firstWhere('codigo', 'scpc-bvs');
    $plano = Plano::factory()->consumoMinimo(900)->create(['catalogo_id' => $catalogo->id]);
    $plano->franquias()->create(['servico_id' => $servico->id, 'quantidade' => 50]);

    admin()->put(route('catalogo.servicos.atualizar', $servico), [
        'nome' => $servico->nome,
        'categoria' => 'credito',
        'ativo' => '0',
    ]);

    // Preco e franquia continuam: consulta e fatura antigas precisam deles.
    expect($servico->fresh()->ativo)->toBeFalse()
        ->and($servico->precos()->count())->toBe(1)
        ->and($plano->franquiaDe('scpc-bvs'))->toBe(50)
        // Mas o servico sai da lista vendavel do plano.
        ->and($plano->servicosDisponiveis()->pluck('codigo')->all())->toBe([]);
});

it('marca e desmarca a liberacao juridica', function () {
    $servico = Servico::factory()->create();

    admin()->put(route('catalogo.servicos.atualizar', $servico), [
        'nome' => $servico->nome,
        'categoria' => 'credito',
        'ativo' => '1',
        'exige_liberacao' => '1',
    ]);

    expect($servico->fresh()->exige_liberacao)->toBeTrue()
        ->and($servico->fresh()->disponivel())->toBeFalse();

    admin()->put(route('catalogo.servicos.atualizar', $servico), [
        'nome' => $servico->nome,
        'categoria' => 'credito',
        'ativo' => '1',
    ]);

    expect($servico->fresh()->exige_liberacao)->toBeFalse()
        ->and($servico->fresh()->disponivel())->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Listagem
|--------------------------------------------------------------------------
*/

it('mostra a situacao de cada servico no interruptor', function () {
    $ativo = Servico::factory()->create(['nome' => 'Servico normal']);
    $pausado = Servico::factory()->inativo()->create(['nome' => 'Servico desligado']);
    Servico::factory()->aguardandoLiberacao()->create(['nome' => 'Servico travado']);

    $html = admin()->get(route('catalogo.servicos.index'))->assertOk()->getContent();

    expect($html)->toContain('Servico normal')
        ->toContain('aguarda liberação')
        // Verde de um lado, vermelho do outro, e cada linha grava no proprio clique.
        ->toContain(route('catalogo.servicos.alternar', $ativo))
        ->toContain(route('catalogo.servicos.alternar', $pausado));

    expect(substr_count($html, 'interruptor-ligado'))->toBe(2)
        ->and(substr_count($html, 'interruptor-desligado'))->toBe(1);
});

it('leva ao cadastro pelo botao de lapis, com o verbo no cabecalho', function () {
    // Icone na linha e verbo na coluna: repetir "Editar" em cada linha so
    // alarga a tabela, e icone sem rotulo em lugar nenhum nao se entende.
    Catalogo::factory()->comServico('scpc-bvs', [0 => 631])->create();
    $servico = Servico::firstWhere('codigo', 'scpc-bvs');

    $html = admin()->get(route('catalogo.servicos.index'))->assertOk()->getContent();

    expect($html)->toContain('<th class="px-5 py-3 text-right font-medium">Editar</th>')
        ->toContain('botao-icone')
        ->toContain(route('catalogo.servicos.editar', $servico));
});

it('marca no catalogo o servico inativo', function () {
    Catalogo::factory()->comServico('scpc-bvs', [0 => 631])->create();
    Servico::where('codigo', 'scpc-bvs')->update(['ativo' => false]);

    admin()->get(route('catalogo.tabela'))->assertOk()->assertSee('pausado');
});
