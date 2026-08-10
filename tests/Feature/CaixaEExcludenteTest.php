<?php

use App\Actions\Consumo\FecharCompetencia;
use App\Actions\Financeiro\RegistrarLiquidacao;
use App\Models\Consulta;
use App\Models\Servico;
use App\Support\FaturaPdf;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * O caixa na visao geral, e a excludente de responsabilidade na fatura.
 *
 * "A receber" e "Em atraso" sao promessa e promessa quebrada; nenhum dos dois
 * responde quanto a casa recebeu de fato. E a excludente precisa estar tambem
 * na fatura, e nao so no laudo: o laudo fica com quem consultou, a fatura vai
 * para a contabilidade e para o juridico do cliente, e ninguem guarda os PDFs
 * de consulta.
 */
function faturaPaga(): App\Models\Fatura
{
    $empresa = empresaComPlano();
    app(App\Actions\Consumo\RegistrarConsulta::class)($empresa, Servico::firstWhere('codigo', 'scpc-bvs'), 7);
    $fatura = app(FecharCompetencia::class)($empresa, Consulta::competenciaDe())['fatura'];
    app(RegistrarLiquidacao::class)($fatura);

    return $fatura->fresh();
}

it('mostra na visao geral o dinheiro que entrou', function () {
    $fatura = faturaPaga();

    $resposta = admin()->get(route('painel'))->assertOk();

    expect($resposta->viewData('recebidoCents'))->toBe($fatura->total_cents);

    $resposta->assertSee('Recebido no mês', false)
        ->assertSee('Comissão a repassar', false);
});

it('conta o recebido pela data da baixa, e nao pela competencia', function () {
    $fatura = faturaPaga();

    // Baixa de mes passado sai do caixa deste mes, mesmo com a fatura aberta
    // no mesmo periodo: o mes do dinheiro e o mes em que ele chegou.
    $fatura->update(['liquidada_em' => now()->subMonthNoOverflow()->startOfMonth()]);

    expect(admin()->get(route('painel'))->viewData('recebidoCents'))->toBe(0);
});

it('leva a excludente de risco de credito para o demonstrativo', function () {
    $fatura = faturaPaga();

    $pdf = FaturaPdf::demonstrativo($fatura->load('itens', 'cliente'));

    // O PDF quebra linha, entao a frase inteira nao existe como uma string so:
    // procura-se por pedacos curtos, que cabem numa linha.
    expect($pdf)->toContain('Aten')
        ->toContain('responsabilidade')
        ->toContain('decis')
        // O resumo do periodo vem antes do detalhe, como o mercado escreve.
        ->toContain('Resumo do per');
});

it('nao promete o que nao entrega no demonstrativo', function () {
    $fatura = faturaPaga();

    $pdf = FaturaPdf::demonstrativo($fatura->load('itens', 'cliente'));

    expect($pdf)->toContain('substitui a nota fiscal');

    // E continua sem numero interno nenhum: a fatura vai para o cliente.
    foreach (['custo', 'lucro', 'margem', 'comiss'] as $proibida) {
        expect($pdf)->not->toMatch('/\b'.$proibida.'\b/i');
    }
});

it('repete a excludente na tela de faturas do cliente', function () {
    $fatura = faturaPaga();

    // Trecho curto: no HTML a frase esta quebrada em varias linhas, e a
    // comparacao e byte a byte.
    comoEmpresa($fatura->cliente)->get(route('empresa.faturas'))
        ->assertOk()
        ->assertSee('de exclusiva responsabilidade', false)
        ->assertSee('Consulta não concluída não é cobrada', false);
});
