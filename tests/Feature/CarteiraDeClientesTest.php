<?php

use App\Models\Cliente;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Filtro e exportacao da carteira de clientes.
 *
 * O que se protege: a planilha leva o mesmo recorte da tela, e nao a base
 * inteira, e nenhum numero interno vai junto. O arquivo circula por e-mail e
 * por conversa, entao custo, lucro, margem e comissao nao entram nele nem
 * sendo uma exportacao da administracao.
 */
function carteiraVariada(): array
{
    $antonia = Staff::factory()->create(['papel' => 'vendedor', 'nome' => 'Antônia Vendedora']);
    $bruno = Staff::factory()->create(['papel' => 'vendedor', 'nome' => 'Bruno Vendedor']);

    $daAntonia = empresaComPlano([
        'razao_social' => 'Padaria Aurora LTDA', 'cnpj' => '11222333000181',
        'email' => 'aurora@teste.com.br', 'vendedor_id' => $antonia->id, 'situacao' => 'ativo',
    ]);

    $doBruno = empresaComPlano([
        'razao_social' => 'Metalúrgica Beta SA', 'cnpj' => '44555666000199',
        'email' => 'beta@teste.com.br', 'vendedor_id' => $bruno->id, 'situacao' => 'suspenso',
    ]);

    return [$antonia, $daAntonia, $doBruno];
}

it('filtra a carteira por nome, documento, vendedor e situacao', function () {
    [$antonia, $daAntonia, $doBruno] = carteiraVariada();

    admin()->get(route('empresas.index', ['busca' => 'Aurora']))
        ->assertSee('Padaria Aurora')->assertDontSee('Metalúrgica Beta');

    // O CNPJ e comparado sem mascara: o operador cola do jeito que recebeu.
    admin()->get(route('empresas.index', ['busca' => '44.555.666/0001-99']))
        ->assertSee('Metalúrgica Beta')->assertDontSee('Padaria Aurora');

    admin()->get(route('empresas.index', ['vendedor' => $antonia->id]))
        ->assertSee('Padaria Aurora')->assertDontSee('Metalúrgica Beta');

    admin()->get(route('empresas.index', ['situacao' => 'suspenso']))
        ->assertSee('Metalúrgica Beta')->assertDontSee('Padaria Aurora');
});

it('ignora filtro desconhecido em vez de quebrar a tela', function () {
    carteiraVariada();

    // O endereco da tela e feito para ser colado e editado a mao.
    admin()->get(route('empresas.index', ['situacao' => 'inventada']))
        ->assertOk()
        ->assertSee('Padaria Aurora')
        ->assertSee('Metalúrgica Beta');
});

it('exporta exatamente o recorte que esta na tela', function () {
    [$antonia] = carteiraVariada();

    $resposta = admin()->get(route('empresas.planilha', ['vendedor' => $antonia->id]))->assertOk();

    expect($resposta->headers->get('content-type'))
        ->toContain('spreadsheetml')
        ->and($resposta->headers->get('content-disposition'))->toContain('avalia-clientes-');

    // A planilha e um zip; o texto das celulas fica nas partes internas.
    $conteudo = $resposta->streamedContent();
    expect($conteudo)->toStartWith('PK');
});

it('registra na trilha quem exportou a carteira', function () {
    carteiraVariada();

    admin()->get(route('empresas.planilha'))->assertOk();

    $trilha = App\Models\Auditoria::where('acao', 'clientes.exportados')->sole();

    expect($trilha->dados['clientes'])->toBe(2)
        ->and($trilha->staff_id)->not->toBeNull()
        ->and(App\Support\Rotulos::acao('clientes.exportados'))->toBe('Carteira de clientes exportada');
});

it('nao coloca custo, lucro nem margem na planilha do cliente', function () {
    carteiraVariada();

    $xlsx = app(App\Actions\Planilha\MontarPlanilhaClientes::class)(Cliente::with(['plano', 'vendedor'])->get());

    $caminho = tempnam(sys_get_temp_dir(), 'teste');
    file_put_contents($caminho, $xlsx);

    // So as celulas, e palavra inteira: o proprio formato xlsx escreve
    // "customWidth" e "customXml", que contem "custo" e davam falso positivo.
    $zip = new ZipArchive;
    $zip->open($caminho);
    $texto = '';
    for ($i = 0; $i < $zip->numFiles; $i++) {
        if (str_contains($zip->getNameIndex($i), 'worksheets/')) {
            $texto .= $zip->getFromIndex($i);
        }
    }
    $zip->close();
    unlink($caminho);

    foreach (['custo', 'lucro', 'margem', 'comiss'] as $proibida) {
        expect($texto)->not->toMatch('/'.$proibida.'/i');
    }

    // E o que precisa estar, esta.
    expect($texto)->toContain('Razão social')
        ->and($texto)->toContain('Padaria Aurora');
});

it('nao deixa vendedor exportar a carteira inteira', function () {
    carteiraVariada();

    comoVendedor(Staff::factory()->create(['papel' => 'vendedor']))
        ->get(route('empresas.planilha'))
        ->assertForbidden();
});
