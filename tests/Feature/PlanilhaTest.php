<?php

use App\Models\Catalogo;
use App\Models\Plano;
use App\Models\Servico;
use App\Models\Staff;
use App\Support\Planilha;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

/** Grava um xlsx temporario e devolve o caminho. */
function planilhaTemporaria(array $abas): string
{
    $caminho = tempnam(sys_get_temp_dir(), 'teste').'.xlsx';
    file_put_contents($caminho, Planilha::xlsx($abas));

    return $caminho;
}

/*
|--------------------------------------------------------------------------
| Escrita e leitura
|--------------------------------------------------------------------------
*/

it('escreve e le a mesma planilha', function () {
    $caminho = planilhaTemporaria([
        'Catalogo' => [['codigo', 'servico', 'custo'], [['scpc-bvs', 'SCPC BVS', 2.80]]],
    ]);

    $linhas = Planilha::ler($caminho);

    expect($linhas[0])->toBe(['codigo', 'servico', 'custo'])
        ->and($linhas[1][0])->toBe('scpc-bvs')
        ->and($linhas[1][1])->toBe('SCPC BVS')
        ->and($linhas[1][2])->toBe('2.8');

    unlink($caminho);
});

it('preserva acento e caractere que quebraria o XML', function () {
    $caminho = planilhaTemporaria([
        'Catalogo' => [['servico'], [['Ações judiciais & "cartórios" <PF>']]],
    ]);

    expect(Planilha::ler($caminho)[1][0])->toBe('Ações judiciais & "cartórios" <PF>');

    unlink($caminho);
});

it('le csv de ponto e virgula, que e o que o Excel salva em portugues', function () {
    $caminho = tempnam(sys_get_temp_dir(), 'teste').'.csv';
    file_put_contents($caminho, "codigo;custo\nscpc-bvs;2,80\n");

    expect(Planilha::ler($caminho))->toBe([['codigo', 'custo'], ['scpc-bvs', '2,80']]);

    unlink($caminho);
});

/*
|--------------------------------------------------------------------------
| Exportar
|--------------------------------------------------------------------------
*/

it('exporta uma planilha com as tres abas', function () {
    Catalogo::factory()->comServico('scpc-bvs', [0 => 631, 90_000 => 493])->create();
    Plano::factory()->create(['nome' => 'Plano teste']);

    $resposta = admin()->get(route('catalogo.planilha.exportar'))->assertOk();

    $caminho = tempnam(sys_get_temp_dir(), 'baixado').'.xlsx';
    file_put_contents($caminho, $resposta->streamedContent());

    $zip = new ZipArchive;
    $zip->open($caminho);
    $pasta = $zip->getFromName('xl/workbook.xml');
    $zip->close();

    expect($pasta)->toContain('name="Catalogo"')
        ->and($pasta)->toContain('name="Planos"')
        ->and($pasta)->toContain('name="Servicos"');

    unlink($caminho);
});

it('leva codigo, custo e preco de cada faixa para a aba de catalogo', function () {
    $catalogo = Catalogo::factory()->comServico('scpc-bvs', [0 => 631, 90_000 => 493])->create();
    $catalogo->precos()->update(['custo_cents' => 280]);

    $resposta = admin()->get(route('catalogo.planilha.exportar'));
    $caminho = tempnam(sys_get_temp_dir(), 'baixado').'.xlsx';
    file_put_contents($caminho, $resposta->streamedContent());

    $linhas = Planilha::ler($caminho);

    expect($linhas[0])->toContain('codigo')
        ->and($linhas[0])->toContain('custo')
        ->and($linhas[0])->toContain('sem minimo')
        ->and($linhas[0])->toContain('faixa 900,00')
        ->and($linhas[1][0])->toBe('scpc-bvs');

    unlink($caminho);
});

it('nao deixa vendedor exportar', function () {
    $this->actingAs(Staff::factory()->create(), 'staff')
        ->withSession(['versao_staff' => 1])
        ->get(route('catalogo.planilha.exportar'))
        ->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| Importar
|--------------------------------------------------------------------------
*/

it('grava preco e custo vindos da planilha', function () {
    $catalogo = Catalogo::factory()->comServico('scpc-bvs', [0 => 631, 90_000 => 493])->create();

    $caminho = planilhaTemporaria([
        'Catalogo' => [
            ['codigo', 'servico', 'custo', 'sem minimo', 'faixa 900,00'],
            [['scpc-bvs', 'SCPC BVS', '2,80', '8,39', '6,18']],
        ],
    ]);

    admin()->post(route('catalogo.planilha.importar'), [
        'planilha' => new UploadedFile($caminho, 'catalogo.xlsx', null, null, true),
    ])->assertSessionHas('ok');

    expect($catalogo->precoDe('scpc-bvs', 0))->toBe(839)
        ->and($catalogo->precoDe('scpc-bvs', 90_000))->toBe(618)
        ->and($catalogo->precos()->pluck('custo_cents')->unique()->all())->toBe([280]);
});

it('casa pelo titulo da coluna, e nao pela posicao', function () {
    // Quem edita no Excel move coluna. Quebrar por isso seria armadilha.
    $catalogo = Catalogo::factory()->comServico('scpc-bvs', [0 => 631, 90_000 => 493])->create();

    $caminho = planilhaTemporaria([
        'Catalogo' => [
            ['faixa 900,00', 'custo', 'servico', 'codigo', 'sem minimo'],
            [['6,18', '2,80', 'SCPC BVS', 'scpc-bvs', '8,39']],
        ],
    ]);

    admin()->post(route('catalogo.planilha.importar'), [
        'planilha' => new UploadedFile($caminho, 'catalogo.xlsx', null, null, true),
    ]);

    expect($catalogo->precoDe('scpc-bvs', 0))->toBe(839)
        ->and($catalogo->precoDe('scpc-bvs', 90_000))->toBe(618);
});

it('ignora codigo que nao existe em vez de criar servico', function () {
    // Criar servico e decisao comercial, nao efeito colateral de importacao.
    $catalogo = Catalogo::factory()->comServico('scpc-bvs', [0 => 631])->create();

    $caminho = planilhaTemporaria([
        'Catalogo' => [['codigo', 'sem minimo'], [['servico-inventado', '9,99'], ['scpc-bvs', '8,39']]],
    ]);

    admin()->post(route('catalogo.planilha.importar'), [
        'planilha' => new UploadedFile($caminho, 'catalogo.xlsx', null, null, true),
    ])->assertSessionHas('ok');

    expect(Servico::count())->toBe(1)
        ->and($catalogo->precoDe('scpc-bvs', 0))->toBe(839);
});

it('recusa planilha sem a coluna de codigo', function () {
    Catalogo::factory()->comServico('scpc-bvs', [0 => 631])->create();

    $caminho = planilhaTemporaria([
        'Catalogo' => [['servico', 'sem minimo'], [['SCPC BVS', '9,99']]],
    ]);

    admin()->post(route('catalogo.planilha.importar'), [
        'planilha' => new UploadedFile($caminho, 'catalogo.xlsx', null, null, true),
    ])->assertSessionHas('erro');
});

it('sobrevive ao ciclo completo de exportar e importar sem mudar nada', function () {
    $catalogo = Catalogo::factory()->comServico('scpc-bvs', [0 => 631, 90_000 => 493])->create();
    $catalogo->precos()->update(['custo_cents' => 280]);

    $baixado = tempnam(sys_get_temp_dir(), 'ciclo').'.xlsx';
    file_put_contents($baixado, admin()->get(route('catalogo.planilha.exportar'))->streamedContent());

    admin()->post(route('catalogo.planilha.importar'), [
        'planilha' => new UploadedFile($baixado, 'catalogo.xlsx', null, null, true),
    ])->assertSessionHas('ok');

    expect($catalogo->precoDe('scpc-bvs', 0))->toBe(631)
        ->and($catalogo->precoDe('scpc-bvs', 90_000))->toBe(493)
        ->and($catalogo->precos()->pluck('custo_cents')->unique()->all())->toBe([280]);

    unlink($baixado);
});

it('nao deixa vendedor importar', function () {
    $caminho = planilhaTemporaria(['Catalogo' => [['codigo'], [['scpc-bvs']]]]);

    $this->actingAs(Staff::factory()->create(), 'staff')
        ->withSession(['versao_staff' => 1])
        ->post(route('catalogo.planilha.importar'), [
            'planilha' => new UploadedFile($caminho, 'catalogo.xlsx', null, null, true),
        ])
        ->assertForbidden();
});
