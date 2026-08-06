<?php

use App\Actions\Consumo\FecharCompetencia;
use App\Actions\Consumo\RegistrarConsulta;
use App\Models\Auditoria;
use App\Models\Cliente;
use App\Models\DocumentoLegal;
use App\Models\Servico;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** Entra como a empresa contratante. */
function comoEmpresa(Cliente $empresa): Tests\TestCase
{
    return test()->actingAs($empresa, 'empresa')
        ->withSession(['versao_empresa' => $empresa->sessao_versao]);
}

/*
|--------------------------------------------------------------------------
| O que a empresa ve
|--------------------------------------------------------------------------
*/

it('abre o painel da empresa com plano, uso e faturas', function () {
    $empresa = empresaComPlano();
    $servico = Servico::firstWhere('codigo', 'scpc-bvs');

    app(RegistrarConsulta::class)($empresa, $servico, 4);
    app(FecharCompetencia::class)($empresa, '2026-07');

    $painel = comoEmpresa($empresa)->get(route('empresa.painel'))->assertOk();
    $faturas = comoEmpresa($empresa)->get(route('empresa.faturas'))->assertOk();

    expect($faturas->viewData('faturas'))->toHaveCount(1)
        ->and($painel->viewData('uso')[$servico->id])->toBe(4);
});

it('nao leva custo, lucro nem margem para o portal do cliente', function () {
    // Sao numeros internos da Avalia. O cliente ve o que paga, nao o que a
    // operacao ganha.
    $empresa = empresaComPlano();
    app(FecharCompetencia::class)($empresa, '2026-07');

    // Varre as cinco telas, e nao so o painel: a area do cliente deixou de ser
    // uma pagina unica, e vazamento aparece justamente na tela nova.
    foreach (['painel', 'consultar', 'consultas', 'faturas', 'documentos'] as $tela) {
        $html = comoEmpresa($empresa)->get(route('empresa.'.$tela))->assertOk()->getContent();

        expect($html)->not->toContain('Custo do fornecedor')
            ->not->toContain('Lucro')
            ->not->toContain('Margem');
    }
});

it('mostra a cada empresa so as faturas dela', function () {
    $minha = empresaComPlano();
    $alheia = empresaComPlano();

    app(FecharCompetencia::class)($minha, '2026-07');
    app(FecharCompetencia::class)($alheia, '2026-07');

    $faturas = comoEmpresa($minha)->get(route('empresa.faturas'))->viewData('faturas');

    expect($faturas)->toHaveCount(1)
        ->and($faturas->first()->cliente_id)->toBe($minha->id);
});

it('nao deixa a empresa abrir tela nenhuma de gestao', function () {
    $empresa = empresaComPlano();

    foreach (['catalogo.tabela', 'empresas.index', 'financeiro.index', 'carteira', 'auditoria'] as $rota) {
        comoEmpresa($empresa)->get(route($rota))->assertRedirect(route('entrar'));
    }
});

it('nao deixa o staff abrir o portal da empresa', function () {
    $this->actingAs(Staff::factory()->admin()->create(), 'staff')
        ->withSession(['versao_staff' => 1])
        ->get(route('empresa.painel'))
        ->assertRedirect(route('entrar'));
});

/*
|--------------------------------------------------------------------------
| Aceite de documento
|--------------------------------------------------------------------------
*/

it('registra o aceite e nao registra duas vezes', function () {
    // Aceite e prova juridica: duplicar confundiria qual valeu.
    $empresa = empresaComPlano();
    $documento = DocumentoLegal::create([
        'titulo' => 'Contrato de prestação de serviços',
        'tipo' => 'contrato',
        'versao' => '1.0',
        'conteudo' => 'Conteúdo do contrato.',
        'exige_aceite' => true,
        'ativo' => true,
    ]);

    comoEmpresa($empresa)->post(route('empresa.documentos.aceitar', $documento))
        ->assertRedirect();

    comoEmpresa($empresa)->post(route('empresa.documentos.aceitar', $documento));

    expect($empresa->aceitesDocumentos()->where('documento_id', $documento->id)->count())->toBe(1);
});

it('deixa rastro do aceite na trilha', function () {
    $empresa = empresaComPlano();
    $documento = DocumentoLegal::create([
        'titulo' => 'Termo de uso',
        'tipo' => 'termo',
        'versao' => '2.0',
        'conteudo' => 'Conteúdo do termo.',
        'exige_aceite' => true,
        'ativo' => true,
    ]);

    comoEmpresa($empresa)->post(route('empresa.documentos.aceitar', $documento));

    expect(Auditoria::where('acao', 'documento.aceito')->count())->toBe(1);
});

it('bloqueia a consulta enquanto ha documento obrigatorio pendente', function () {
    // Para LGPD e SCR a trava e requisito: consultar sem aceite deixaria a
    // Avalia sem base legal para o que ja foi consultado.
    $empresa = empresaComPlano();
    $servico = Servico::firstWhere('codigo', 'scpc-bvs');

    $documento = DocumentoLegal::create([
        'titulo' => 'Contrato obrigatório',
        'tipo' => 'contrato',
        'versao' => '1.0',
        'conteudo' => 'Conteúdo.',
        'exige_aceite' => true,
        'ativo' => true,
    ]);

    expect(app(RegistrarConsulta::class)($empresa, $servico)['erro'])
        ->toContain('pendentes de aceite');

    comoEmpresa($empresa)->post(route('empresa.documentos.aceitar', $documento));

    expect(app(RegistrarConsulta::class)($empresa->fresh(), $servico)['erro'])->toBeNull();
});

it('nao trava a consulta por documento que nao e obrigatorio', function () {
    $empresa = empresaComPlano();

    DocumentoLegal::create([
        'titulo' => 'Material de apoio',
        'tipo' => 'apoio',
        'versao' => '1.0',
        'conteudo' => 'Conteúdo.',
        'exige_aceite' => false,
        'ativo' => true,
    ]);

    expect(app(RegistrarConsulta::class)($empresa, Servico::firstWhere('codigo', 'scpc-bvs'))['erro'])
        ->toBeNull();
});
