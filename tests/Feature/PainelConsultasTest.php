<?php

use App\Actions\Consumo\RegistrarConsulta;
use App\Models\Consulta;
use App\Models\Servico;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * O painel de consultas existe em tres recortes: a empresa ve as dela, o
 * vendedor as da carteira e a administracao todas.
 *
 * Sao tres telas separadas de proposito. O que estes testes protegem e o limite
 * de cada uma: nenhum parametro de URL pode fazer um recorte devolver linha de
 * outro.
 */
function comConsultas(): array
{
    $vendedor = Staff::factory()->create(['papel' => 'vendedor']);

    $minha = empresaComPlano(['razao_social' => 'Empresa da Carteira LTDA', 'vendedor_id' => $vendedor->id]);
    $alheia = empresaComPlano(['razao_social' => 'Empresa de Outro LTDA']);

    $servico = Servico::firstWhere('codigo', 'scpc-bvs');

    app(RegistrarConsulta::class)($minha, $servico, 3);
    app(RegistrarConsulta::class)($alheia, $servico, 2);

    return [$vendedor, $minha, $alheia, $servico];
}

/*
|--------------------------------------------------------------------------
| Administracao
|--------------------------------------------------------------------------
*/

it('mostra a administracao as consultas de todas as empresas', function () {
    [, $minha, $alheia] = comConsultas();

    $resposta = admin()->get(route('consultas'))->assertOk();

    expect($resposta->viewData('resumo')['total'])->toBe(5);

    $resposta->assertSee($minha->razao_social)->assertSee($alheia->razao_social);
});

it('separa tentativa de cobranca no resumo do periodo', function () {
    [, $minha, , $servico] = comConsultas();

    // Consulta que falhou fica registrada com preco zero. Ela conta como
    // tentativa e nao pode entrar no valor, senao a tela nao bate com a fatura.
    Consulta::factory()->falha()->create([
        'cliente_id' => $minha->id,
        'servico_id' => $servico->id,
    ]);

    $resumo = admin()->get(route('consultas'))->assertOk()->viewData('resumo');

    expect($resumo['total'])->toBe(6)
        ->and($resumo['falhas'])->toBe(1)
        ->and($resumo['valor_cents'])->toBe(5 * 324);
});

it('nao imprime o documento consultado na lista', function () {
    [, $minha, , $servico] = comConsultas();

    Consulta::factory()->create([
        'cliente_id' => $minha->id,
        'servico_id' => $servico->id,
        'documento' => '52998224725',
    ]);

    // Acesso a dado pessoal e evento, nao coluna de tabela: quem precisa do
    // documento abre a consulta, e a abertura fica registrada.
    admin()->get(route('consultas'))->assertOk()->assertDontSee('52998224725');
});

it('fecha o painel geral de consultas para o vendedor', function () {
    [$vendedor] = comConsultas();

    comoVendedor($vendedor)->get(route('consultas'))->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| Vendedor
|--------------------------------------------------------------------------
*/

it('mostra ao vendedor so as consultas das empresas da carteira dele', function () {
    [$vendedor, $minha, $alheia] = comConsultas();

    $resposta = comoVendedor($vendedor)->get(route('carteira.consultas'))->assertOk();

    expect($resposta->viewData('resumo')['total'])->toBe(3);

    $resposta->assertSee($minha->razao_social)->assertDontSee($alheia->razao_social);
});

it('nao deixa filtro trazer consulta de carteira alheia', function () {
    [$vendedor, , $alheia, $servico] = comConsultas();

    // Filtra pelo servico que as duas empresas usam: o recorte de carteira vem
    // do vinculo, e nao do que se pede na URL.
    $resposta = comoVendedor($vendedor)
        ->get(route('carteira.consultas', ['servico' => $servico->id, 'periodo' => 'tudo']))
        ->assertOk();

    expect($resposta->viewData('consultas')->pluck('cliente_id'))
        ->not->toContain($alheia->id);
});

it('nao leva custo, lucro nem margem para as telas do vendedor', function () {
    [$vendedor] = comConsultas();

    foreach (['carteira.consultas', 'carteira.servicos', 'carteira.simulacao'] as $rota) {
        $html = comoVendedor($vendedor)->get(route($rota))->assertOk()->getContent();

        expect($html)->not->toContain('Custo do fornecedor')
            ->not->toContain('Lucro')
            ->not->toContain('Margem');
    }
});

/*
|--------------------------------------------------------------------------
| Empresa
|--------------------------------------------------------------------------
*/

it('mostra a empresa so as consultas dela', function () {
    [, $minha, $alheia] = comConsultas();

    $resposta = comoEmpresa($minha)->get(route('empresa.consultas'))->assertOk();

    expect($resposta->viewData('resumo')['total'])->toBe(3)
        ->and($resposta->viewData('consultas')->pluck('cliente_id')->unique()->all())
        ->toBe([$minha->id])
        ->and($alheia->consultas()->count())->toBe(2);
});

/*
|--------------------------------------------------------------------------
| Filtros
|--------------------------------------------------------------------------
*/

it('filtra por resultado e por periodo', function () {
    [, $minha, , $servico] = comConsultas();

    Consulta::factory()->falha()->em(now()->subDays(60))->create([
        'cliente_id' => $minha->id,
        'servico_id' => $servico->id,
    ]);

    $falhas = admin()->get(route('consultas', ['situacao' => Consulta::FALHA, 'periodo' => 'tudo']))
        ->assertOk()->viewData('resumo');

    expect($falhas['total'])->toBe(1);

    // A mesma falha, dentro dos ultimos 7 dias, nao aparece.
    $recentes = admin()->get(route('consultas', ['situacao' => Consulta::FALHA, 'periodo' => '7']))
        ->assertOk()->viewData('resumo');

    expect($recentes['total'])->toBe(0);
});

it('volta ao padrao quando o filtro vem com valor desconhecido', function () {
    comConsultas();

    // O endereco da tela e feito para ser colado e editado a mao: valor invalido
    // nao pode virar erro nem lista vazia sem explicacao.
    $resposta = admin()->get(route('consultas', ['periodo' => 'ontem', 'situacao' => 'talvez']))->assertOk();

    expect($resposta->viewData('escolha')['periodo'])->toBe('30')
        ->and($resposta->viewData('escolha')['situacao'])->toBe('')
        ->and($resposta->viewData('resumo')['total'])->toBe(5);
});

it('nao deixa a empresa alcancar o painel de consultas da gestao', function () {
    [, $minha] = comConsultas();

    comoEmpresa($minha)->get(route('consultas'))->assertRedirect(route('entrar'));
});

it('nao deixa o staff entrar nas telas da area do cliente', function () {
    [$vendedor] = comConsultas();

    foreach (['empresa.painel', 'empresa.consultar', 'empresa.consultas', 'empresa.faturas', 'empresa.documentos'] as $rota) {
        comoVendedor($vendedor)->get(route($rota))->assertRedirect(route('entrar'));
    }
});
