<?php

use App\Models\ContaFinanceira;
use App\Models\LancamentoFinanceiro;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Fase 1 do plano do financeiro
|--------------------------------------------------------------------------
|
| O razao so consegue responder "quanto o Avalia One deu de lucro" se receita e
| custo tiverem conta por produto. Sem isso a pergunta e respondida recalculando
| faturas em tela, que e a origem da divergencia que o painel de plaquinhas
| mostrou.
*/

it('tem conta de receita e de custo para cada produto', function () {
    foreach (['one', 'gestor', 'plaquinha'] as $produto) {
        expect(ContaFinanceira::whereIn('codigo', ["receita:{$produto}", "custo:{$produto}"])->count())
            ->toBe(2, "faltou receita ou custo de {$produto}");
    }
});

it('separa a comissao apurada da comissao que ainda falta pagar', function () {
    // Sao perguntas diferentes: uma e resultado do mes, a outra e divida em
    // aberto. Na mesma conta, pagar o vendedor apagaria a despesa do mes.
    expect(ContaFinanceira::firstWhere('codigo', 'comissao')?->grupo)->toBe('despesa');
    expect(ContaFinanceira::firstWhere('codigo', 'comissao-a-pagar')?->grupo)->toBe('passivo');
});

it('tem onde guardar fatura fechada que ninguem pagou ainda', function () {
    expect(ContaFinanceira::firstWhere('codigo', 'clientes-a-receber')?->grupo)->toBe('ativo');
});

it('mantem as tres contas antigas, porque as partidas apontam para elas', function () {
    expect(ContaFinanceira::whereIn('codigo', ['caixa', 'receita', 'despesa'])->count())->toBe(3);
});

it('le saldo de passivo com o sinal que a pessoa espera', function () {
    // Passivo cresce no credito, que no razao e valor negativo. Quem le a tela
    // espera "devo R$ 148,61", e nao "menos R$ 148,61".
    $lancamento = LancamentoFinanceiro::create([
        'natureza' => 'despesa',
        'descricao' => 'Comissao do mes',
        'competencia' => '2026-09',
        'ocorrido_em' => '2026-09-30',
    ]);

    $comissao = ContaFinanceira::firstWhere('codigo', 'comissao');
    $aPagar = ContaFinanceira::firstWhere('codigo', 'comissao-a-pagar');

    $lancamento->partidas()->create(['conta_id' => $comissao->id, 'valor_cents' => 14861]);
    $lancamento->partidas()->create(['conta_id' => $aPagar->id, 'valor_cents' => -14861]);

    expect($comissao->saldoCents())->toBe(14861)
        ->and($aPagar->saldoCents())->toBe(14861);

    // A invariante do razao continua valendo depois de somar as contas novas.
    expect((int) DB::table('partidas_financeiras')->sum('valor_cents'))->toBe(0);
});

it('nao duplica conta quando a publicacao roda a migration de novo', function () {
    $antes = ContaFinanceira::count();

    require database_path('migrations/2026_09_29_000008_plano_de_contas_por_produto.php');
    (require database_path('migrations/2026_09_29_000008_plano_de_contas_por_produto.php'))->up();

    expect(ContaFinanceira::count())->toBe($antes);
});
