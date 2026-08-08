<?php

use App\Actions\Consumo\FecharCompetencia;
use App\Models\Consulta;
use App\Models\Servico;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * O demonstrativo da fatura em arquivo.
 *
 * Quem paga costuma ser outra pessoa que nao entra no portal, e essa pessoa
 * pede um PDF para anexar ao pagamento. O que se protege aqui e o cerco (so a
 * empresa dona baixa) e o sigilo do que e interno: custo, lucro, margem e
 * comissao nunca podem aparecer num arquivo que sai da nossa mao.
 */
function faturaDoMes(): array
{
    $empresa = empresaComPlano();
    $servico = Servico::firstWhere('codigo', 'scpc-bvs');

    app(App\Actions\Consumo\RegistrarConsulta::class)($empresa, $servico, 12);
    $fatura = app(FecharCompetencia::class)($empresa, Consulta::competenciaDe())['fatura'];

    return [$empresa, $fatura];
}

it('entrega o demonstrativo da fatura para a empresa', function () {
    [$empresa, $fatura] = faturaDoMes();

    $resposta = comoEmpresa($empresa)->get(route('empresa.faturas.pdf', $fatura))->assertOk();

    expect($resposta->headers->get('content-type'))->toContain('application/pdf')
        ->and($resposta->getContent())->toStartWith('%PDF')
        ->and($resposta->headers->get('content-disposition'))->toContain($fatura->competencia);
});

it('nao coloca custo, lucro nem comissao no arquivo do cliente', function () {
    [, $fatura] = faturaDoMes();

    $pdf = App\Support\FaturaPdf::demonstrativo($fatura->load('itens', 'cliente'));

    // O PDF guarda o texto legivel no stream, entao procurar a palavra vale.
    foreach (['Custo', 'custo', 'Lucro', 'lucro', 'Margem', 'margem', 'Comiss'] as $proibida) {
        expect($pdf)->not->toContain($proibida);
    }

    // E os numeros internos tambem nao podem aparecer formatados.
    expect($fatura->custo_cents)->toBeGreaterThan(0)
        ->and($pdf)->not->toContain(App\Support\Dinheiro::brl($fatura->custo_cents))
        ->and($pdf)->not->toContain(App\Support\Dinheiro::brl($fatura->comissao_cents));
});

it('mostra a composicao que a tela mostra', function () {
    [, $fatura] = faturaDoMes();

    $pdf = App\Support\FaturaPdf::demonstrativo($fatura->load('itens', 'cliente'));

    expect($pdf)->toContain('Demonstrativo da fatura')
        ->and($pdf)->toContain('Mensalidade do plano')
        ->and($pdf)->toContain('Total');
});

it('nao entrega o demonstrativo de outra empresa', function () {
    [, $fatura] = faturaDoMes();
    $outra = empresaComPlano(['cnpj' => '11222333000181', 'email' => 'outra@teste.com.br']);

    comoEmpresa($outra)->get(route('empresa.faturas.pdf', $fatura))->assertForbidden();
});

it('entrega o mesmo arquivo ao atendimento', function () {
    [, $fatura] = faturaDoMes();

    admin()->get(route('financeiro.pdf', $fatura))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});
