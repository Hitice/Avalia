<?php

use App\Actions\Consumo\ExecutarConsulta;
use App\Actions\Consumo\ExpurgarRespostas;
use App\Actions\Consumo\FecharCompetencia;
use App\Contracts\ConectorBureau;
use App\Models\Auditoria;
use App\Models\Consulta;
use App\Models\DocumentoLegal;
use App\Models\Servico;
use App\Support\RespostaConsulta;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** Documento que o conector simulado responde, e um que ele recusa. */
const DOC_OK = '11144477735';

const DOC_RECUSADO = '11144477730';

/*
|--------------------------------------------------------------------------
| A consulta
|--------------------------------------------------------------------------
*/

it('registra a consulta com o que foi perguntado e por que', function () {
    // Finalidade e responsavel sao exigencia da secao 14 do PDD: sem eles nao
    // ha base legal que se sustente numa fiscalizacao.
    $empresa = empresaComPlano();
    $servico = Servico::firstWhere('codigo', 'scpc-bvs');

    $resultado = app(ExecutarConsulta::class)(
        $empresa, $servico, DOC_OK, 'Análise de crédito para venda a prazo', 'Maria da Silva',
    );

    $consulta = $resultado['consulta'];

    expect($resultado['erro'])->toBeNull()
        ->and($consulta->deuCerto())->toBeTrue()
        ->and($consulta->documento)->toBe(DOC_OK)
        ->and($consulta->finalidade)->toBe('Análise de crédito para venda a prazo')
        ->and($consulta->solicitante)->toBe('Maria da Silva')
        ->and($consulta->resposta['simulado'])->toBeTrue()
        ->and($consulta->referencia_externa)->toStartWith('SIM-');
});

it('congela o preco da faixa no instante da consulta', function () {
    $empresa = empresaComPlano();
    $servico = Servico::firstWhere('codigo', 'scpc-bvs');

    $consulta = app(ExecutarConsulta::class)($empresa, $servico, DOC_OK, 'Análise')['consulta'];

    $empresa->plano->catalogo->precos()->update(['preco_cents' => 9_999]);

    expect($consulta->fresh()->preco_cents)->toBe(324);
});

it('nao cobra pela consulta que nao respondeu', function () {
    // Cobrar por resposta que nao veio e o jeito mais rapido de perder um
    // contrato. O registro fica, com o motivo; a cobranca nao.
    $empresa = empresaComPlano();
    $servico = Servico::firstWhere('codigo', 'scpc-bvs');

    $resultado = app(ExecutarConsulta::class)($empresa, $servico, DOC_RECUSADO, 'Análise');
    $consulta = $resultado['consulta'];

    expect($resultado['erro'])->not->toBeNull()
        ->and($consulta->deuCerto())->toBeFalse()
        ->and($consulta->preco_cents)->toBe(0)
        ->and($consulta->custo_cents)->toBe(0)
        ->and($consulta->resposta['erro'])->not->toBeNull();
});

it('deixa a falha fora do consumo da competencia', function () {
    $empresa = empresaComPlano();
    $servico = Servico::firstWhere('codigo', 'scpc-bvs');

    app(ExecutarConsulta::class)($empresa, $servico, DOC_OK, 'Análise');
    app(ExecutarConsulta::class)($empresa, $servico, DOC_RECUSADO, 'Análise');

    $fatura = app(FecharCompetencia::class)($empresa, Consulta::competenciaDe())['fatura'];

    // Duas tentativas, uma cobrada.
    expect(Consulta::count())->toBe(2)
        ->and($fatura->consumo_bruto_cents)->toBe(324);
});

it('nao chama o fornecedor quando a empresa nao pode consultar', function () {
    // Recusar antes de perguntar evita pagar ao fornecedor por uma consulta
    // que nao poderia ter sido feita.
    $empresa = empresaComPlano(['situacao' => 'inadimplente']);
    $servico = Servico::firstWhere('codigo', 'scpc-bvs');

    $espiao = new class implements ConectorBureau
    {
        public bool $chamado = false;

        public function consultar(Servico $servico, string $documento, string $finalidade): RespostaConsulta
        {
            $this->chamado = true;

            return RespostaConsulta::sucesso([]);
        }

        public function nome(): string
        {
            return 'espiao';
        }
    };

    app()->instance(ConectorBureau::class, $espiao);

    $resultado = app(ExecutarConsulta::class)($empresa, $servico, DOC_OK, 'Análise');

    expect($espiao->chamado)->toBeFalse()
        ->and($resultado['erro'])->toContain('fatura em aberto')
        ->and(Consulta::count())->toBe(0);
});

it('recusa consulta de servico fora do plano contratado', function () {
    $empresa = empresaComPlano();
    $outro = Servico::factory()->create(['codigo' => 'sem-preco-aqui']);

    expect(app(ExecutarConsulta::class)($empresa, $outro, DOC_OK, 'Análise')['erro'])
        ->toContain('não está no plano contratado');
});

it('recusa consulta com documento obrigatorio pendente', function () {
    $empresa = empresaComPlano();

    DocumentoLegal::create([
        'titulo' => 'Contrato', 'tipo' => 'contrato', 'versao' => '1.0',
        'conteudo' => 'Conteúdo.', 'exige_aceite' => true, 'ativo' => true,
    ]);

    expect(app(ExecutarConsulta::class)($empresa, Servico::firstWhere('codigo', 'scpc-bvs'), DOC_OK, 'Análise')['erro'])
        ->toContain('pendentes de aceite');
});

it('recusa consulta em competencia ja fechada', function () {
    $empresa = empresaComPlano();
    $servico = Servico::firstWhere('codigo', 'scpc-bvs');

    app(FecharCompetencia::class)($empresa, Consulta::competenciaDe());

    expect(app(ExecutarConsulta::class)($empresa, $servico, DOC_OK, 'Análise')['erro'])
        ->toContain('já foi fechado');
});

it('deixa rastro de cada tentativa, tenha dado certo ou nao', function () {
    $empresa = empresaComPlano();
    $servico = Servico::firstWhere('codigo', 'scpc-bvs');

    app(ExecutarConsulta::class)($empresa, $servico, DOC_OK, 'Análise');
    app(ExecutarConsulta::class)($empresa, $servico, DOC_RECUSADO, 'Análise');

    expect(Auditoria::where('acao', 'consulta.sucesso')->count())->toBe(1)
        ->and(Auditoria::where('acao', 'consulta.falha')->count())->toBe(1);
});

it('responde igual para o mesmo documento', function () {
    // Homologacao com resposta aleatoria faria o operador duvidar do que ve, e
    // o teste passar hoje e falhar amanha.
    $empresa = empresaComPlano();
    $servico = Servico::firstWhere('codigo', 'scpc-bvs');

    $primeira = app(ExecutarConsulta::class)($empresa, $servico, DOC_OK, 'Análise')['consulta'];
    $segunda = app(ExecutarConsulta::class)($empresa, $servico, DOC_OK, 'Análise')['consulta'];

    expect($segunda->resposta['score'])->toBe($primeira->resposta['score'])
        ->and($segunda->referencia_externa)->toBe($primeira->referencia_externa);
});

/*
|--------------------------------------------------------------------------
| Retencao
|--------------------------------------------------------------------------
*/

it('marca o prazo de expurgo em cada consulta', function () {
    $empresa = empresaComPlano();

    $consulta = app(ExecutarConsulta::class)(
        $empresa, Servico::firstWhere('codigo', 'scpc-bvs'), DOC_OK, 'Análise',
    )['consulta'];

    expect($consulta->expurgar_em->toDateString())
        ->toBe(now()->addDays(Consulta::DIAS_DE_RETENCAO)->toDateString());
});

it('apaga a resposta vencida e preserva o que explica a cobranca', function () {
    // Apagar a consulta inteira deixaria uma fatura sem composicao, o que e
    // problema fiscal, nao ganho de privacidade.
    $empresa = empresaComPlano();

    $consulta = app(ExecutarConsulta::class)(
        $empresa, Servico::firstWhere('codigo', 'scpc-bvs'), DOC_OK, 'Análise',
    )['consulta'];

    $apagadas = app(ExpurgarRespostas::class)(now()->addDays(Consulta::DIAS_DE_RETENCAO + 1));

    $consulta->refresh();

    expect($apagadas)->toBe(1)
        ->and($consulta->resposta)->toBeNull()
        ->and($consulta->documento)->toBeNull()
        ->and($consulta->expurgada())->toBeTrue()
        // O que sustenta a fatura continua la.
        ->and($consulta->preco_cents)->toBe(324)
        ->and($consulta->competencia)->not->toBeNull()
        ->and($consulta->finalidade)->toBe('Análise');
});

it('nao apaga o que ainda esta dentro do prazo', function () {
    $empresa = empresaComPlano();

    app(ExecutarConsulta::class)($empresa, Servico::firstWhere('codigo', 'scpc-bvs'), DOC_OK, 'Análise');

    expect(app(ExpurgarRespostas::class)(now()->addDays(Consulta::DIAS_DE_RETENCAO - 1)))->toBe(0);
});

it('nao expurga duas vezes a mesma consulta', function () {
    $empresa = empresaComPlano();

    app(ExecutarConsulta::class)($empresa, Servico::firstWhere('codigo', 'scpc-bvs'), DOC_OK, 'Análise');

    $depois = now()->addDays(Consulta::DIAS_DE_RETENCAO + 1);

    expect(app(ExpurgarRespostas::class)($depois))->toBe(1)
        ->and(app(ExpurgarRespostas::class)($depois))->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Pela tela do cliente
|--------------------------------------------------------------------------
*/

it('consulta pelo portal e mostra o resultado', function () {
    $empresa = empresaComPlano();
    $servico = Servico::firstWhere('codigo', 'scpc-bvs');

    comoEmpresa($empresa)->post(route('empresa.consultas.executar'), [
        'servico_id' => $servico->id,
        'documento' => DOC_OK,
        'finalidade' => 'Análise de crédito para venda a prazo',
    ])->assertRedirect();

    $consulta = Consulta::first();

    comoEmpresa($empresa)->get(route('empresa.consultas.ver', $consulta))
        ->assertOk()
        ->assertSee($servico->nome)
        ->assertSee($consulta->referencia_externa);
});

it('exige finalidade declarada para consultar', function () {
    // Sem finalidade nao ha base legal, e o campo em branco tornaria a
    // exigencia do PDD uma formalidade vazia.
    $empresa = empresaComPlano();

    comoEmpresa($empresa)->post(route('empresa.consultas.executar'), [
        'servico_id' => Servico::firstWhere('codigo', 'scpc-bvs')->id,
        'documento' => DOC_OK,
        'finalidade' => '',
    ])->assertSessionHasErrors('finalidade');

    expect(Consulta::count())->toBe(0);
});

it('nao deixa uma empresa abrir a consulta de outra', function () {
    $minha = empresaComPlano();
    $alheia = empresaComPlano();
    $servico = Servico::firstWhere('codigo', 'scpc-bvs');

    $consulta = app(ExecutarConsulta::class)($alheia, $servico, DOC_OK, 'Análise')['consulta'];

    comoEmpresa($minha)->get(route('empresa.consultas.ver', $consulta))->assertForbidden();
});

it('avisa na tela quando a consulta nao foi concluida', function () {
    $empresa = empresaComPlano();

    comoEmpresa($empresa)->post(route('empresa.consultas.executar'), [
        'servico_id' => Servico::firstWhere('codigo', 'scpc-bvs')->id,
        'documento' => DOC_RECUSADO,
        'finalidade' => 'Análise de crédito para venda a prazo',
    ])->assertSessionHas('erro');

    expect(Consulta::first()->deuCerto())->toBeFalse();
});
