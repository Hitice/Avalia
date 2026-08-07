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

    // Varre todas as telas, e nao so o painel: a area do cliente deixou de ser
    // uma pagina unica, e vazamento aparece justamente na tela nova.
    foreach (['painel', 'consultar', 'consultas', 'faturas', 'simulador', 'documentos'] as $tela) {
        $html = comoEmpresa($empresa)->get(route('empresa.'.$tela))->assertOk()->getContent();

        expect($html)->not->toContain('Custo do fornecedor')
            ->not->toContain('Lucro')
            ->not->toContain('Margem');
    }
});

it('mostra no painel quanto falta para o consumo minimo', function () {
    // Surpresa de fatura minima e a reclamacao mais evitavel: a regua fica no
    // painel, antes de a fatura existir. Sem consulta nenhuma, falta o minimo
    // inteiro.
    $empresa = empresaComPlano();

    comoEmpresa($empresa)->get(route('empresa.painel'))->assertOk()
        ->assertSee("Faltam R$\u{00A0}900,00 para o consumo mínimo do plano.", false);
});

it('mostra preco e descricao do servico antes de consultar', function () {
    // Ninguem deveria descobrir o valor da consulta na fatura, nem contratar
    // sem saber o que a consulta devolve.
    $empresa = empresaComPlano();
    $servico = Servico::firstWhere('codigo', 'scpc-bvs');
    $servico->update(['descricao' => 'Restrições, protestos e score do documento consultado.']);
    $empresa->plano->franquias()->create(['servico_id' => $servico->id, 'quantidade' => 10]);

    app(RegistrarConsulta::class)($empresa, $servico, 4);

    $precos = comoEmpresa($empresa)->get(route('empresa.consultar'))->assertOk()
        ->viewData('precos');

    expect($precos[$servico->id])->toContain('Restrições, protestos e score')
        ->toContain('Valor por consulta')
        ->toContain("R$\u{00A0}3,24")
        ->toContain('6 de 10 consultas da franquia');
});

it('abre a composicao da fatura sem custo nem margem', function () {
    $empresa = empresaComPlano();
    $servico = Servico::firstWhere('codigo', 'scpc-bvs');

    // Fecha a competencia em que as consultas caem, senao a fatura sai vazia.
    app(RegistrarConsulta::class)($empresa, $servico, 2);
    app(FecharCompetencia::class)($empresa, App\Models\Consulta::competenciaDe());

    $html = comoEmpresa($empresa)->get(route('empresa.faturas'))->assertOk()->getContent();

    // A fatura se explica linha a linha: servico consumido e o complemento
    // que fecha a conta no minimo contratado.
    expect($html)->toContain($servico->nome)
        ->toContain('2 consultas')
        ->toContain('Complemento até o consumo mínimo contratado')
        ->not->toContain('Custo');
});

it('simula a fatura do mes com a mesma conta do fechamento', function () {
    // Franquia nao soma, excedente compara com o minimo, total = mensalidade
    // mais o maior dos dois. A simulacao e leitura pura: nada e gravado.
    $empresa = empresaComPlano();
    $servico = Servico::firstWhere('codigo', 'scpc-bvs');
    $empresa->plano->franquias()->create(['servico_id' => $servico->id, 'quantidade' => 10]);

    // 100 consultas a R$ 3,24: 10 na franquia, 90 excedentes = R$ 291,60.
    // Abaixo do minimo de R$ 900, entao o mes sai pelo piso + mensalidade.
    $resposta = comoEmpresa($empresa)
        ->get(route('empresa.simulador', ['q' => [$servico->id => 100]]))
        ->assertOk();

    expect($resposta->viewData('excedente'))->toBe(29_160)
        ->and($resposta->viewData('faturado'))->toBe(90_000)
        ->and($resposta->viewData('total'))->toBe(97_990)
        ->and(App\Models\Consulta::count())->toBe(0);
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

    comoEmpresa($empresa)->post(route('empresa.documentos.aceitar', $documento), aceiteValido($documento))
        ->assertRedirect();

    comoEmpresa($empresa)->post(route('empresa.documentos.aceitar', $documento), aceiteValido($documento));

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

    comoEmpresa($empresa)->post(route('empresa.documentos.aceitar', $documento), aceiteValido($documento));

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

    comoEmpresa($empresa)->post(route('empresa.documentos.aceitar', $documento), aceiteValido($documento));

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
