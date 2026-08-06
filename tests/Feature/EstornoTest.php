<?php

use App\Actions\Consumo\FecharCompetencia;
use App\Actions\Financeiro\EstornarLiquidacao;
use App\Actions\Financeiro\RegistrarLiquidacao;
use App\Models\Auditoria;
use App\Models\Fatura;
use App\Models\Staff;
use App\Support\Auditar;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Estorno de recebimento
|--------------------------------------------------------------------------
|
| Pagamento desfeito acontece. Ate existir este caminho, a liquidacao era de
| mao unica e o dinheiro do vendedor saia do controle sem volta.
|
*/

it('recolhe a comissao ao desfazer o recebimento', function () {
    $empresa = empresaComPlano();
    $fatura = app(FecharCompetencia::class)($empresa, '2026-07')['fatura'];

    app(RegistrarLiquidacao::class)($fatura);
    expect($fatura->fresh()->comissao_liberada_em)->not->toBeNull();

    app(EstornarLiquidacao::class)($fatura, 'Chargeback do cartão informado pelo provedor');

    $fatura->refresh();

    expect($fatura->comissao_liberada_em)->toBeNull()
        ->and($fatura->liquidada_em)->toBeNull()
        ->and($fatura->estornada_em)->not->toBeNull();
});

it('nao mexe no valor da fatura ao estornar', function () {
    // O que foi desfeito e o recebimento, nao a venda. A competencia continua
    // fechada com o mesmo total, imposto e comissao apurada.
    $empresa = empresaComPlano();
    $fatura = app(FecharCompetencia::class)($empresa, '2026-07')['fatura'];
    $antes = $fatura->only(['total_cents', 'imposto_cents', 'custo_cents', 'comissao_cents', 'lucro_cents']);

    app(RegistrarLiquidacao::class)($fatura);
    app(EstornarLiquidacao::class)($fatura, 'Pix devolvido pelo banco do pagador');

    expect($fatura->fresh()->only(array_keys($antes)))->toBe($antes);
});

it('devolve a fatura vencida ao estado de vencida, e nao de pendente', function () {
    // O prazo passou de verdade; fingir o contrario adiaria o bloqueio.
    $empresa = empresaComPlano();
    $fatura = app(FecharCompetencia::class)($empresa, '2026-07')['fatura'];

    app(RegistrarLiquidacao::class)($fatura);
    app(EstornarLiquidacao::class)($fatura, 'Boleto baixado por engano', new DateTimeImmutable('2026-08-25'));

    expect($fatura->fresh()->situacao_pagamento)->toBe(Fatura::PAGAMENTO_VENCIDO)
        ->and($empresa->fresh()->situacao)->toBe('inadimplente');
});

it('deixa a fatura em aberto quando o estorno acontece antes do vencimento', function () {
    $empresa = empresaComPlano();
    $fatura = app(FecharCompetencia::class)($empresa, '2026-07')['fatura'];

    app(RegistrarLiquidacao::class)($fatura);
    app(EstornarLiquidacao::class)($fatura, 'Pagamento em duplicidade', new DateTimeImmutable('2026-08-05'));

    expect($fatura->fresh()->situacao_pagamento)->toBe(Fatura::PAGAMENTO_PENDENTE)
        ->and($empresa->fresh()->situacao)->toBe('ativo');
});

it('nao reabre bloqueio administrativo nem contrato encerrado', function () {
    // Desfazer um pagamento nao autoriza reabrir uma punicao.
    foreach (['bloqueado', 'inativo'] as $situacao) {
        $empresa = empresaComPlano();
        $fatura = app(FecharCompetencia::class)($empresa, '2026-07')['fatura'];

        app(RegistrarLiquidacao::class)($fatura);
        $empresa->update(['situacao' => $situacao]);

        app(EstornarLiquidacao::class)($fatura, 'Chargeback informado pelo provedor', new DateTimeImmutable('2026-08-25'));

        expect($empresa->fresh()->situacao)->toBe($situacao);
    }
});

it('recusa estornar o que nao esta liquidado', function () {
    $empresa = empresaComPlano();
    $fatura = app(FecharCompetencia::class)($empresa, '2026-07')['fatura'];

    expect(app(EstornarLiquidacao::class)($fatura, 'Motivo qualquer aqui')['erro'])
        ->toContain('não está liquidada');
});

it('exige motivo para desfazer pela tela', function () {
    $empresa = empresaComPlano();
    $fatura = app(FecharCompetencia::class)($empresa, '2026-07')['fatura'];
    app(RegistrarLiquidacao::class)($fatura);

    admin()->post(route('financeiro.estornar', $fatura), ['motivo' => 'ok'])
        ->assertSessionHasErrors('motivo');

    expect($fatura->fresh()->estaLiquidada())->toBeTrue();
});

it('deixa o motivo e o valor recolhido na trilha', function () {
    $empresa = empresaComPlano();
    $fatura = app(FecharCompetencia::class)($empresa, '2026-07')['fatura'];
    app(RegistrarLiquidacao::class)($fatura);

    admin()->post(route('financeiro.estornar', $fatura), [
        'motivo' => 'Chargeback informado pelo provedor em 25/08',
    ])->assertSessionHas('ok');

    $registro = Auditoria::where('acao', 'fatura.estornada')->latest('id')->first();

    expect($registro->dados['motivo'])->toContain('Chargeback')
        ->and($registro->dados['comissao_recolhida_cents'])->toBe($fatura->comissao_cents);
});

it('nao deixa administrador sem permissao financeira estornar', function () {
    $empresa = empresaComPlano();
    $fatura = app(FecharCompetencia::class)($empresa, '2026-07')['fatura'];
    app(RegistrarLiquidacao::class)($fatura);

    $semPermissao = Staff::factory()->admin()->create(['pode_financeiro' => false]);

    test()->actingAs($semPermissao, 'staff')
        ->withSession(['versao_staff' => $semPermissao->sessao_versao])
        ->post(route('financeiro.estornar', $fatura), ['motivo' => 'Chargeback informado pelo provedor'])
        ->assertForbidden();

    expect($fatura->fresh()->estaLiquidada())->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Trilha encadeada
|--------------------------------------------------------------------------
*/

it('fecha a corrente da trilha quando nada foi alterado', function () {
    $empresa = empresaComPlano();
    $fatura = app(FecharCompetencia::class)($empresa, '2026-07')['fatura'];
    app(RegistrarLiquidacao::class)($fatura);
    app(EstornarLiquidacao::class)($fatura, 'Chargeback informado pelo provedor');

    expect(Auditoria::count())->toBeGreaterThan(2)
        ->and(Auditar::conferir())->toBeEmpty();
});

it('acusa registro adulterado na trilha', function () {
    // Nao impede a alteracao, porque a tabela continua sendo uma tabela.
    // Impede que ela passe despercebida.
    $empresa = empresaComPlano();
    $fatura = app(FecharCompetencia::class)($empresa, '2026-07')['fatura'];
    app(RegistrarLiquidacao::class)($fatura);

    $registro = Auditoria::where('acao', 'fatura.liquidada')->first();
    $registro->update(['dados' => ['motivo' => 'texto trocado depois']]);

    expect(Auditar::conferir())->toContain($registro->id);
});

it('acusa registro removido do meio da trilha', function () {
    // Do meio, e nao do fim: apagar o ultimo elo nao quebra corrente nenhuma,
    // porque nao sobra registro seguinte para apontar para ele. Detectar isso
    // exigiria ancora fora do banco, e esta escrito em Auditar.
    $empresa = empresaComPlano();
    app(FecharCompetencia::class)($empresa, '2026-07');
    app(FecharCompetencia::class)($empresa, '2026-06');
    app(FecharCompetencia::class)($empresa, '2026-05');

    $doMeio = Auditoria::orderBy('id')->skip(1)->first();
    $doMeio->delete();

    expect(Auditar::conferir())->not->toBeEmpty();
});
