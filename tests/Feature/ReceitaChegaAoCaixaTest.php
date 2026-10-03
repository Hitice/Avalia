<?php

use App\Enums\NaturezaLancamento;
use App\Exceptions\Recusa;
use App\Models\ContaFinanceira;
use App\Models\LancamentoFinanceiro;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| O dinheiro do produto chega ao caixa dos socios
|--------------------------------------------------------------------------
|
| A ligacao e de UMA direcao: financeiro alimenta socios, nunca o contrario.
| Ate aqui o razao so conhecia o que alguem digitasse, e o caixa ignorava a
| receita do proprio negocio.
|
*/

function reconhecer(int $origemId = 1, int $cents = 50_000): ?LancamentoFinanceiro
{
    return app(App\Actions\Socios\ReconhecerReceita::class)(
        'fatura', $origemId, $cents, 'Fatura de teste', now(),
    );
}

it('recusa em voz alta quando falta conta, em vez de criar uma por fora do plano', function () {
    // Conta nasce em migration, com codigo estavel. Criar sob demanda foi o
    // que deixou producao com conta fora do plano; agora a falta e erro dito.
    ContaFinanceira::query()->delete();

    expect(fn () => reconhecer())->toThrow(App\Exceptions\Recusa::class);
});

it('leva o valor ao caixa e fecha em zero', function () {
    $lancamento = reconhecer(cents: 50_000);

    expect($lancamento->partidas->sum('valor_cents'))->toBe(0)
        ->and(ContaFinanceira::firstWhere('codigo', 'caixa')->saldoCents())->toBe(50_000)
        ->and(ContaFinanceira::firstWhere('codigo', 'receita')->saldoCents())->toBe(50_000);
});

it('nao reconhece a mesma origem duas vezes', function () {
    // Rotina repetida, webhook duplicado, competencia reprocessada: o par de
    // origem e unico no banco, entao a segunda tentativa encontra o trabalho
    // feito e nao e erro.
    reconhecer(origemId: 7);
    $segunda = reconhecer(origemId: 7);

    expect($segunda)->toBeNull()
        ->and(LancamentoFinanceiro::count())->toBe(1)
        ->and(ContaFinanceira::firstWhere('codigo', 'caixa')->saldoCents())->toBe(50_000);
});

it('ignora valor zero', function () {
    expect(reconhecer(cents: 0))->toBeNull()
        ->and(LancamentoFinanceiro::count())->toBe(0);
});

it('recusa receita digitada na conta de um produto, que entra sozinha pela liquidacao', function () {
    $conta = ContaFinanceira::firstOrCreate(['codigo' => 'receita:one'], ['nome' => 'Receita do One', 'grupo' => 'receita']);

    expect(fn () => app(App\Actions\Socios\RegistrarLancamento::class)(NaturezaLancamento::Receita, [
        'descricao' => 'a mesma fatura, digitada',
        'competencia' => now()->format('Y-m'),
        'ocorrido_em' => now()->toDateString(),
        'valor_cents' => 50_000,
        'categoria_id' => $conta->id,
    ]))->toThrow(Recusa::class);
});

it('deixa lancar receita a mao de outra natureza no mesmo mes da automatica', function () {
    // Receita digitada existe para o que NAO tem origem no sistema, como um
    // projeto de software fechado por fora, e placa vendendo todo mes nao
    // pode fechar essa porta.
    reconhecer();

    $outra = app(App\Actions\Socios\RegistrarLancamento::class)(NaturezaLancamento::Receita, [
        'descricao' => 'projeto de RPA',
        'competencia' => now()->format('Y-m'),
        'ocorrido_em' => now()->toDateString(),
        'valor_cents' => 300_000,
    ]);

    expect($outra->origem_tipo)->toBeNull()
        ->and(LancamentoFinanceiro::count())->toBe(2);
});
