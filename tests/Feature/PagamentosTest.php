<?php

use App\Actions\Consumo\FecharCompetencia;
use App\Actions\Consumo\RegistrarConsulta;
use App\Actions\Financeiro\EstornarLiquidacao;
use App\Actions\Financeiro\PagarComissaoOne;
use App\Actions\Financeiro\RegistrarLiquidacao;
use App\Exceptions\Recusa;
use App\Models\Consulta;
use App\Models\ContaAPagar;
use App\Models\ContaFinanceira;
use App\Models\LancamentoFinanceiro;
use App\Models\Socio;
use App\Models\Staff;
use App\Support\Caixa;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| A fatura inteira no razao, e a pagamentos
|--------------------------------------------------------------------------
*/

function faturaLiquidada(): array
{
    [$vendedor, $empresa, $servico] = carteira();
    app(RegistrarConsulta::class)($empresa, $servico, 3);
    $fatura = app(FecharCompetencia::class)($empresa, '2026-07')['fatura'];
    app(RegistrarLiquidacao::class)($fatura);

    return [$vendedor, $fatura->fresh()];
}

function financeiro(): Tests\TestCase
{
    $conta = Staff::factory()->admin()->create(['pode_financeiro' => true]);

    return test()->actingAs($conta, 'staff')->withSession(['versao_staff' => $conta->sessao_versao]);
}

it('lanca a fatura liquidada com receita, imposto, custo e comissao, e estorna tudo junto', function () {
    [, $fatura] = faturaLiquidada();

    expect(saldo('receita:one'))->toBe((int) $fatura->total_cents)
        ->and(saldo('caixa'))->toBe((int) $fatura->total_cents)
        ->and(saldo('imposto'))->toBe((int) $fatura->imposto_cents)
        ->and(saldo('imposto-a-pagar'))->toBe((int) $fatura->imposto_cents)
        ->and(saldo('custo:one'))->toBe((int) $fatura->custo_cents)
        ->and(saldo('comissao-a-pagar'))->toBe((int) $fatura->comissao_cents)
        ->and($fatura->comissao_cents)->toBeGreaterThan(0);

    app(EstornarLiquidacao::class)($fatura, 'Pix devolvido');

    expect(saldo('receita:one'))->toBe(0)->and(saldo('comissao-a-pagar'))->toBe(0)->and(saldo('caixa'))->toBe(0);

    // Liquidar de novo lanca de novo, mesmo com o original estornado dono da origem.
    app(RegistrarLiquidacao::class)($fatura->fresh());
    expect(saldo('receita:one'))->toBe((int) $fatura->total_cents)
        ->and(LancamentoFinanceiro::where('origem_tipo', 'like', 'fatura%')->where('origem_id', $fatura->id)->count())->toBe(2);
});

it('paga a comissao de consultas descontando as demonstracoes, uma vez so', function () {
    [$vendedor, $fatura] = faturaLiquidada();
    Consulta::factory()->create(['vendedor_id' => $vendedor->id, 'situacao' => Consulta::SUCESSO, 'custo_cents' => 150]);
    $esperado = (int) $fatura->comissao_cents - 150;

    expect(Caixa::aRepassarCents())->toBe($esperado);

    $pago = app(PagarComissaoOne::class)($vendedor);

    expect($pago['cents'])->toBe($esperado)
        ->and($fatura->fresh()->comissao_paga_em)->not->toBeNull()
        ->and(Consulta::whereNull('descontada_em')->where('vendedor_id', $vendedor->id)->count())->toBe(0)
        ->and(saldo('comissao-a-pagar'))->toBe(150)
        ->and(Caixa::aRepassarCents())->toBe(0);

    expect(fn () => app(PagarComissaoOne::class)($vendedor))->toThrow(Recusa::class);
});

it('completa no lastro a fatura que entrou so com a receita', function () {
    [, $fatura] = faturaLiquidada();
    // A forma antiga: duas pernas. Apaga as outras para simular.
    $lancamento = LancamentoFinanceiro::where('origem_tipo', 'fatura')->where('origem_id', $fatura->id)->sole();
    $ids = ContaFinanceira::whereIn('codigo', ['caixa', 'receita:one'])->pluck('id');
    $lancamento->partidas()->whereNotIn('conta_id', $ids)->delete();
    expect(saldo('imposto'))->toBe(0);

    test()->artisan('avalia:lastrear-faturas')->assertSuccessful();
    test()->artisan('avalia:lastrear-faturas')->assertSuccessful();

    expect(saldo('imposto'))->toBe((int) $fatura->imposto_cents)
        ->and(saldo('comissao-a-pagar'))->toBe((int) $fatura->comissao_cents)
        ->and(LancamentoFinanceiro::where('origem_tipo', 'fatura-complemento')->count())->toBe(1);
});

it('provisiona a conta a pagar na categoria e paga contra o caixa', function () {
    $infra = ContaFinanceira::firstWhere('codigo', 'despesa:infra');

    financeiro()->from(route('erp.contas'))->post(route('erp.contas.salvar'), [
        'descricao' => 'Hostinger', 'fornecedor' => 'Hostinger', 'categoria_id' => $infra->id, 'valor' => '89,90', 'vence_em' => now()->addDays(2)->toDateString(),
    ])->assertRedirect(route('erp.contas'))->assertSessionHas('ok');

    $conta = ContaAPagar::sole();
    expect(saldo('despesa:infra'))->toBe(8_990)->and(saldo('fornecedores-a-pagar'))->toBe(8_990)->and(saldo('caixa'))->toBe(0);

    financeiro()->post(route('erp.contas.pagar', $conta));
    expect(saldo('fornecedores-a-pagar'))->toBe(0)->and(saldo('caixa'))->toBe(-8_990)->and($conta->fresh()->pago_em)->not->toBeNull();

    financeiro()->from(route('erp.contas'))->post(route('erp.contas.pagar', $conta))->assertSessionHas('erro');
});

it('mostra os pagamentos com as quatro partes e a lista de Pix, e lanca o pro-labore', function () {
    $emails = config('etiquetas.socios');
    $pedro = Staff::factory()->admin()->create(['email' => $emails[0], 'nome' => 'Pedro', 'pode_financeiro' => true, 'pix_chave' => 'pedro@pix']);
    Staff::factory()->admin()->create(['email' => $emails[1], 'nome' => 'Ruan']);
    $socio = Socio::create(['nome' => 'Pedro', 'staff_id' => $pedro->id, 'participacao_bps' => 5_000, 'ativo' => true]);
    $maria = Staff::factory()->create(['papel' => 'vendedor', 'nome' => 'Maria', 'pix_chave' => 'maria@pix']);
    App\Models\Etiqueta::factory()->ativa()->count(2)->create(['vendedor_id' => $maria->id]);
    test()->artisan('avalia:lastrear-plaquinhas')->assertSuccessful();

    $como = test()->actingAs($pedro, 'staff')->withSession(['versao_staff' => $pedro->sessao_versao]);
    $html = $como->get(route('erp.pagamentos'))->assertOk()->getContent();

    expect($html)->toContain('Comissão de placas')->toContain('Comissão de consultas')->toContain('Pró-labore')->toContain('Lista de Pix')
        ->and($html)->toContain('maria@pix')->toContain('pedro@pix')
        ->and($html)->toContain('sem cadastro de sócio');

    $antes = saldo('caixa');
    $como->from(route('erp.pagamentos'))->post(route('erp.pagamentos.prolabore', $socio), ['valor' => '25,00'])
        ->assertRedirect(route('erp.pagamentos'))->assertSessionHas('ok');

    expect(saldo('despesa:prolabore'))->toBe(2_500)->and(saldo('caixa'))->toBe($antes - 2_500);

    // Sem a permissao financeira, a porta fecha.
    $sem = Staff::factory()->admin()->create(['pode_financeiro' => false]);
    test()->actingAs($sem, 'staff')->withSession(['versao_staff' => $sem->sessao_versao])
        ->withHeaders(['referer' => ''])->get(route('erp.pagamentos'))->assertForbidden();
});
