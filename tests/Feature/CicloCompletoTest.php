<?php

use App\Actions\Financeiro\AtualizarInadimplencia;
use App\Actions\Financeiro\FecharCompetenciasVencidas;
use App\Models\Catalogo;
use App\Models\Cliente;
use App\Models\CobrancaAsaas;
use App\Models\Consulta;
use App\Models\DocumentoLegal;
use App\Models\Fatura;
use App\Models\Plano;
use App\Models\Servico;
use App\Models\Staff;
use App\Support\Margem;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| O ciclo inteiro, do cadastro ao repasse
|--------------------------------------------------------------------------
|
| Cada modulo tem os proprios testes. Este percorre a linha toda, na ordem em
| que a vida acontece, e confere o que so quebra na emenda entre eles: um
| numero que muda de valor ao passar de uma etapa para a seguinte.
|
| A conta que precisa fechar em cada degrau:
|
|     fatura = mensalidade + consumo faturado
|     fatura = imposto + custo + comissao + lucro
|
| Se as duas baterem em todos os pontos, nao ha dinheiro se perdendo nem
| aparecendo entre a consulta e o repasse.
|
*/

it('percorre cadastro, aceite, consulta, fechamento, cobranca e repasse', function () {
    /*
     * 1. A administracao monta o catalogo e o plano.
     */
    $catalogo = Catalogo::factory()->comServico('scpc-bvs', [90_000 => 324])->create();
    $catalogo->precos()->update(['custo_cents' => 150]);

    $plano = Plano::factory()->consumoMinimo(900)->create([
        'catalogo_id' => $catalogo->id,
        'mensalidade_cents' => 7_990,
    ]);

    $servico = Servico::firstWhere('codigo', 'scpc-bvs');

    $documento = DocumentoLegal::create([
        'titulo' => 'Contrato de prestação de serviços',
        'tipo' => 'contrato',
        'versao' => '1.0',
        'conteudo' => 'Conteúdo do contrato.',
        'exige_aceite' => true,
        'ativo' => true,
    ]);

    /*
     * 2. O vendedor cadastra a empresa, que cai na carteira dele.
     */
    $vendedor = Staff::factory()->create(['papel' => 'vendedor', 'comissao_pct' => 15]);

    comoVendedor($vendedor)->post(route('empresas.salvar'), [
        'razao_social' => 'Cliente do Ciclo LTDA',
        'cnpj' => '11111111000191',
        'email' => 'ciclo@cliente.com.br',
        'senha' => 'senha-valida-123',
        'situacao' => 'ativo',
        'plano_id' => $plano->id,
    ])->assertRedirect(route('carteira'));

    $empresa = Cliente::firstWhere('email', 'ciclo@cliente.com.br');

    expect($empresa->vendedor_id)->toBe($vendedor->id);

    /*
     * 3. Sem aceite do contrato, nao consulta.
     */
    comoEmpresa($empresa)->post(route('empresa.consultas.executar'), [
        'servico_id' => $servico->id,
        'documento' => '11144477735',
        'finalidade' => 'Análise de crédito para venda a prazo',
    ])->assertSessionHas('erro');

    expect(Consulta::count())->toBe(0);

    comoEmpresa($empresa)->post(route('empresa.documentos.aceitar', $documento))->assertRedirect();

    /*
     * 4. Agora consulta. Cinco dao certo, uma nao, e a que falhou nao e cobrada.
     */
    foreach (['11144477735', '11144477736', '11144477737', '11144477738', '11144477739'] as $doc) {
        comoEmpresa($empresa->fresh())->post(route('empresa.consultas.executar'), [
            'servico_id' => $servico->id,
            'documento' => $doc,
            'finalidade' => 'Análise de crédito para venda a prazo',
        ])->assertRedirect();
    }

    comoEmpresa($empresa->fresh())->post(route('empresa.consultas.executar'), [
        'servico_id' => $servico->id,
        'documento' => '11144477730',
        'finalidade' => 'Análise de crédito para venda a prazo',
    ])->assertSessionHas('erro');

    expect(Consulta::count())->toBe(6)
        ->and(Consulta::where('situacao', Consulta::SUCESSO)->count())->toBe(5)
        ->and((int) Consulta::sum('preco_cents'))->toBe(5 * 324);

    /*
     * 5. A rotina noturna fecha a competencia.
     */
    $competencia = Consulta::competenciaDe();
    Consulta::query()->update(['competencia' => '2026-07']);

    expect(app(FecharCompetenciasVencidas::class)(new DateTimeImmutable('2026-08-15')))->toBe(1);

    $fatura = $empresa->faturas()->firstOrFail();

    // Consumo de R$ 16,20 nao atinge o minimo de R$ 900: vale o piso.
    expect($fatura->consumo_realizado_cents)->toBe(1_620)
        ->and($fatura->consumo_faturado_cents)->toBe(90_000)
        ->and($fatura->total_cents)->toBe(97_990);

    // A cascata fecha, e a comissao usa a taxa negociada do vendedor.
    expect($fatura->comissao_pct)->toBe(15)
        ->and($fatura->total_cents - $fatura->imposto_cents - $fatura->custo_cents - $fatura->comissao_cents)
        ->toBe($fatura->lucro_cents);

    // E o imposto e o do catalogo, congelado na emissao.
    expect($fatura->imposto_cents)->toBe(Margem::impostoCents($fatura->total_cents, $catalogo->imposto_bps));

    /*
     * 6. A cobranca nasce junto da fatura.
     */
    $cobranca = CobrancaAsaas::where('fatura_id', $fatura->id)->firstOrFail();

    expect($cobranca->valor_cents)->toBe($fatura->total_cents);

    /*
     * 7. Ninguem pagou: vence e depois bloqueia.
     */
    app(AtualizarInadimplencia::class)(new DateTimeImmutable('2026-08-11'));
    expect($fatura->fresh()->situacao_pagamento)->toBe(Fatura::PAGAMENTO_VENCIDO);

    app(AtualizarInadimplencia::class)(new DateTimeImmutable('2026-08-20'));
    expect($empresa->fresh()->situacao)->toBe('inadimplente');

    // Bloqueada, ela para de consultar mas continua entrando para regularizar.
    comoEmpresa($empresa->fresh())->post(route('empresa.consultas.executar'), [
        'servico_id' => $servico->id,
        'documento' => '11144477735',
        'finalidade' => 'Análise de crédito para venda a prazo',
    ])->assertSessionHas('erro');

    comoEmpresa($empresa->fresh())->get(route('empresa.painel'))->assertOk();

    /*
     * 8. O provedor confirma o pagamento. Comissao liberada, acesso de volta.
     */
    $cobranca->update(['asaas_charge_id' => 'pay_ciclo']);
    config()->set('services.asaas.webhook_token', 'token-de-teste');

    $this->withHeaders(['asaas-access-token' => 'token-de-teste'])
        ->postJson(route('webhooks.asaas'), [
            'id' => 'evt_ciclo',
            'event' => 'PAYMENT_RECEIVED',
            'payment' => ['id' => 'pay_ciclo', 'status' => 'RECEIVED'],
        ])->assertOk();

    $fatura->refresh();

    expect($fatura->estaLiquidada())->toBeTrue()
        ->and($fatura->comissao_liberada_em)->not->toBeNull()
        ->and($empresa->fresh()->situacao)->toBe('ativo');

    /*
     * 9. O vendedor ve a comissao liberada, e so a dele.
     */
    $carteira = comoVendedor($vendedor)->get(route('carteira'))->assertOk();

    expect($carteira->viewData('aReceber'))->toBe($fatura->comissao_cents)
        ->and($carteira->viewData('aConfirmar'))->toBe(0);

    /*
     * 10. A administracao ve o mesmo valor no repasse a pagar.
     */
    $financeiro = admin()->get(route('financeiro.index'))->assertOk();

    expect($financeiro->viewData('comissoes')->first()->total_cents)->toBe($fatura->comissao_cents)
        ->and($financeiro->viewData('totais')['liquidado'])->toBe($fatura->total_cents);

    /*
     * 11. A empresa volta a consultar, e a competencia nova comeca do zero.
     *
     * Documento novo de proposito: repetir um ja consultado cairia na janela
     * que impede cobrar duas vezes pela mesma informacao, e o teste estaria
     * medindo outra coisa.
     */
    comoEmpresa($empresa->fresh())->post(route('empresa.consultas.executar'), [
        'servico_id' => $servico->id,
        'documento' => '11144477741',
        'finalidade' => 'Análise de crédito para venda a prazo',
    ])->assertRedirect();

    expect(Consulta::where('competencia', $competencia)->count())->toBe(1);
});

it('nao deixa dinheiro aparecer entre a consulta e a fatura', function () {
    // Consumo acima do minimo: aqui o piso nao mascara erro de soma, e cada
    // centavo cobrado tem de vir de uma consulta que deu certo.
    $empresa = empresaComPlano();
    $servico = Servico::firstWhere('codigo', 'scpc-bvs');

    // 300 consultas a R$ 3,24 passam dos R$ 900 de minimo.
    for ($i = 0; $i < 300; $i++) {
        $empresa->consultas()->create([
            'servico_id' => $servico->id,
            'competencia' => '2026-07',
            'documento' => '11144477735',
            'finalidade' => 'Análise',
            'situacao' => Consulta::SUCESSO,
            'preco_cents' => 324,
            'custo_cents' => 150,
        ]);
    }

    $fatura = app(App\Actions\Consumo\FecharCompetencia::class)($empresa, '2026-07')['fatura'];

    $somaDasConsultas = (int) $empresa->consultas()->where('competencia', '2026-07')->sum('preco_cents');

    expect($fatura->consumo_bruto_cents)->toBe($somaDasConsultas)
        ->and($fatura->consumo_faturado_cents)->toBe($somaDasConsultas)
        ->and($fatura->total_cents)->toBe($empresa->plano->mensalidade_cents + $somaDasConsultas)
        ->and($fatura->custo_cents)->toBe(300 * 150)
        ->and($fatura->total_cents - $fatura->imposto_cents - $fatura->custo_cents - $fatura->comissao_cents)
        ->toBe($fatura->lucro_cents);
});
