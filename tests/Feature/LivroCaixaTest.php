<?php

use App\Contabil\LivroCaixa;
use App\Enums\NaturezaLancamento;
use App\Exceptions\Recusa;
use App\Models\ContaFinanceira;
use App\Models\LancamentoFinanceiro;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| O livro-caixa dos socios
|--------------------------------------------------------------------------
*/

function lancarNoCaixa(NaturezaLancamento $natureza, int $cents, array $dados = []): LancamentoFinanceiro
{
    return app(App\Actions\Socios\RegistrarLancamento::class)($natureza, array_merge([
        'descricao' => 'teste', 'competencia' => now()->format('Y-m'), 'ocorrido_em' => now()->toDateString(), 'valor_cents' => $cents,
    ], $dados));
}

it('lanca a despesa na categoria escolhida, e recusa categoria de outro grupo', function () {
    $infra = ContaFinanceira::firstWhere('codigo', 'despesa:infra');
    lancarNoCaixa(NaturezaLancamento::Despesa, 5_000, ['categoria_id' => $infra->id, 'descricao' => 'Hostinger']);

    expect(saldo('despesa:infra'))->toBe(5_000)->and(saldo('caixa'))->toBe(-5_000);

    $receita = ContaFinanceira::firstWhere('codigo', 'receita:servicos');
    expect(fn () => lancarNoCaixa(NaturezaLancamento::Despesa, 1_000, ['categoria_id' => $receita->id]))
        ->toThrow(Recusa::class);
});

it('mostra entradas, saidas e o saldo linha a linha, e nao lista o que nao passou pelo caixa', function () {
    $socio = App\Models\Socio::create(['nome' => 'Pedro', 'participacao_bps' => 5_000, 'ativo' => true]);
    lancarNoCaixa(NaturezaLancamento::Aporte, 100_000, ['socio_id' => $socio->id, 'ocorrido_em' => now()->startOfMonth()->toDateString()]);
    lancarNoCaixa(NaturezaLancamento::Despesa, 30_000, ['descricao' => 'Placas', 'categoria_id' => ContaFinanceira::firstWhere('codigo', 'despesa:fornecedor')->id]);
    lancarNoCaixa(NaturezaLancamento::DespesaDoSocio, 2_000, ['socio_id' => $socio->id, 'descricao' => 'Dominio pago pelo Pedro']);

    $doMes = LancamentoFinanceiro::daCompetencia(now()->format('Y-m'))->with('partidas.conta')->get();
    $caixa = LivroCaixa::doMes($doMes, now()->format('Y-m'));

    expect($caixa['entradas'])->toBe(100_000)
        ->and($caixa['saidas'])->toBe(30_000)
        ->and($caixa['movimentos'])->toHaveCount(2)
        ->and($caixa['movimentos']->first()['saldo'])->toBe(70_000)
        ->and($caixa['movimentos']->first()['categoria'])->toBe('Fornecedores e materiais');

    $categorias = LivroCaixa::porCategoria($doMes);
    expect($categorias->pluck('nome')->all())->toContain('Fornecedores e materiais', 'Despesa');
});

it('nao conta como entrada e saida o lancamento estornado no mesmo mes', function () {
    $socio = App\Models\Socio::create(['nome' => 'Pedro', 'participacao_bps' => 5_000, 'ativo' => true]);
    lancarNoCaixa(NaturezaLancamento::Aporte, 100_000, ['socio_id' => $socio->id]);
    $errado = lancarNoCaixa(NaturezaLancamento::Despesa, 30_000, ['descricao' => 'Placas', 'categoria_id' => ContaFinanceira::firstWhere('codigo', 'despesa:fornecedor')->id]);
    app(App\Actions\Socios\EstornarLancamento::class)($errado, 'valor errado');

    $doMes = LancamentoFinanceiro::daCompetencia(now()->format('Y-m'))->with('partidas.conta')->get();
    $caixa = LivroCaixa::doMes($doMes, now()->format('Y-m'));

    expect($caixa['entradas'])->toBe(100_000)
        ->and($caixa['saidas'])->toBe(0)
        ->and($caixa['movimentos'])->toHaveCount(1);
});
it('baixa o mes em planilha', function () {
    lancarNoCaixa(NaturezaLancamento::Despesa, 1_000, ['descricao' => 'Café']);
    $socio = App\Models\Staff::factory()->admin()->create(['pode_socios' => true]);

    test()->actingAs($socio, 'staff')->withSession(['versao_staff' => $socio->sessao_versao])
        ->get(route('socios.planilha', ['competencia' => now()->format('Y-m')]))
        ->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});
