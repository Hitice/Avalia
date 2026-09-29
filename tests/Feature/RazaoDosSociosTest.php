<?php

use App\Enums\NaturezaLancamento;
use App\Exceptions\Recusa;
use App\Models\ContaFinanceira;
use App\Models\LancamentoFinanceiro;
use App\Models\PartidaFinanceira;
use App\Models\Socio;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(Database\Seeders\ContasFinanceirasSeeder::class);
});

function lancar(NaturezaLancamento $natureza, array $dados = []): LancamentoFinanceiro
{
    return app(App\Actions\Socios\RegistrarLancamento::class)($natureza, array_merge([
        'descricao' => 'teste',
        'competencia' => '2026-09',
        'ocorrido_em' => '2026-09-15',
        'valor_cents' => 10_000,
    ], $dados));
}

function socio(string $nome = 'Pedro'): Socio
{
    return Socio::create(['nome' => $nome, 'participacao_bps' => 5_000, 'ativo' => true]);
}

function saldo(string $codigo): int
{
    return ContaFinanceira::firstWhere('codigo', $codigo)?->saldoCents() ?? 0;
}

/*
|--------------------------------------------------------------------------
| A invariante
|--------------------------------------------------------------------------
*/

it('grava toda natureza com partidas que somam zero', function () {
    // A soma e a unica conferencia que pega erro de traducao sem reconferir
    // extrato. Vale para as nove, e nao so para as que alguem lembrou de testar.
    $socio = socio();
    $destino = ContaFinanceira::create(['codigo' => 'banco', 'nome' => 'Banco', 'grupo' => 'ativo']);

    foreach (NaturezaLancamento::cases() as $natureza) {
        $lancamento = lancar($natureza, ['socio_id' => $socio->id, 'destino_id' => $destino->id]);

        expect($lancamento->partidas->sum('valor_cents'))->toBe(0)
            ->and($lancamento->partidas)->toHaveCount(2);
    }
});

it('recusa valor zero ou negativo', function () {
    expect(fn () => lancar(NaturezaLancamento::Despesa, ['valor_cents' => 0]))->toThrow(Recusa::class)
        ->and(fn () => lancar(NaturezaLancamento::Despesa, ['valor_cents' => -100]))->toThrow(Recusa::class);
});

it('recusa lancamento de socio sem dizer qual socio', function () {
    expect(fn () => lancar(NaturezaLancamento::Aporte, ['socio_id' => null]))->toThrow(Recusa::class);
});

it('recusa transferencia sem destino, e diz o que falta', function () {
    // Sem destino as duas pernas cairiam na mesma conta e o lancamento sumiria
    // numa soma zero que nao move nada.
    try {
        lancar(NaturezaLancamento::Transferencia);
        $this->fail('deveria ter recusado');
    } catch (Recusa $e) {
        expect($e->getMessage())->toContain('destino');
    }
});

it('recusa transferencia para a propria conta de origem', function () {
    $caixa = ContaFinanceira::firstWhere('codigo', ContaFinanceira::CAIXA);

    expect(fn () => lancar(NaturezaLancamento::Transferencia, ['destino_id' => $caixa->id]))
        ->toThrow(Recusa::class);
});

/*
|--------------------------------------------------------------------------
| Os exemplos de aceite
|--------------------------------------------------------------------------
|
| Cada um abaixo e uma das confusoes que custam dinheiro numa sociedade.
|
*/

it('socio paga hospedagem: vira despesa e valor a reembolsar, nao duas despesas', function () {
    $pedro = socio();

    lancar(NaturezaLancamento::DespesaDoSocio, ['socio_id' => $pedro->id, 'valor_cents' => 10_000]);

    expect(saldo(ContaFinanceira::DESPESA))->toBe(10_000)
        ->and(saldo('emprestimo:'.$pedro->id))->toBe(10_000)
        // O caixa da empresa nao se moveu: quem pagou foi ele.
        ->and(saldo(ContaFinanceira::CAIXA))->toBe(0);

    // O reembolso liquida a obrigacao e reduz o caixa. A despesa continua uma.
    lancar(NaturezaLancamento::Reembolso, ['socio_id' => $pedro->id, 'valor_cents' => 10_000]);

    expect(saldo(ContaFinanceira::DESPESA))->toBe(10_000)
        ->and(saldo('emprestimo:'.$pedro->id))->toBe(0)
        ->and(saldo(ContaFinanceira::CAIXA))->toBe(-10_000);
});

it('aporte aumenta caixa e patrimonio, e nao gera receita', function () {
    $pedro = socio();

    lancar(NaturezaLancamento::Aporte, ['socio_id' => $pedro->id, 'valor_cents' => 100_000]);

    expect(saldo(ContaFinanceira::CAIXA))->toBe(100_000)
        ->and(saldo('aporte:'.$pedro->id))->toBe(100_000)
        // A linha que mais erra: aporte nao e venda.
        ->and(saldo(ContaFinanceira::RECEITA))->toBe(0);
});

it('transferencia entre contas da empresa nao muda o resultado', function () {
    $outra = ContaFinanceira::create(['codigo' => 'banco', 'nome' => 'Banco', 'grupo' => 'ativo']);

    lancar(NaturezaLancamento::Transferencia, ['valor_cents' => 30_000, 'destino_id' => $outra->id]);

    expect(saldo(ContaFinanceira::CAIXA))->toBe(-30_000)
        ->and($outra->fresh()->saldoCents())->toBe(30_000)
        ->and(saldo(ContaFinanceira::RECEITA))->toBe(0)
        ->and(saldo(ContaFinanceira::DESPESA))->toBe(0);
});

it('nao reconhece a mesma origem duas vezes', function () {
    // Reimportar nao pode transformar uma fatura em duas receitas.
    lancar(NaturezaLancamento::Receita, ['origem_tipo' => 'fatura', 'origem_id' => 7]);

    expect(fn () => lancar(NaturezaLancamento::Receita, ['origem_tipo' => 'fatura', 'origem_id' => 7]))
        ->toThrow(Illuminate\Database\QueryException::class);

    expect(LancamentoFinanceiro::count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| O que so receita e despesa mexem
|--------------------------------------------------------------------------
*/

it('sabe quais naturezas mexem no resultado', function () {
    // A tela de resultado depende disso para nao somar aporte como se fosse
    // venda nem reembolso como se fosse gasto novo.
    $mexem = collect(NaturezaLancamento::cases())
        ->filter(fn ($n) => $n->afetaResultado())
        ->map(fn ($n) => $n->value)
        ->values()
        ->all();

    expect($mexem)->toBe(['despesa', 'despesa_do_socio', 'receita']);
});

it('nao deixa lancamento mudar depois de gravado', function () {
    // Corrigir e estornar. Linha que muda deixa de explicar o saldo conferido.
    lancar(NaturezaLancamento::Despesa);

    $partida = PartidaFinanceira::first();

    expect($partida->timestamps)->toBeFalse()
        ->and(LancamentoFinanceiro::UPDATED_AT)->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Estorno: corrigir e lancar o contrario
|--------------------------------------------------------------------------
*/

function estornar(LancamentoFinanceiro $lancamento, string $motivo = 'valor errado'): LancamentoFinanceiro
{
    return app(App\Actions\Socios\EstornarLancamento::class)($lancamento, $motivo);
}

it('devolve o saldo ao ponto de partida', function () {
    $pedro = socio();

    $lancamento = lancar(NaturezaLancamento::Aporte, ['socio_id' => $pedro->id, 'valor_cents' => 50_000]);
    expect(saldo(ContaFinanceira::CAIXA))->toBe(50_000);

    $estorno = estornar($lancamento);

    expect(saldo(ContaFinanceira::CAIXA))->toBe(0)
        ->and(saldo('aporte:'.$pedro->id))->toBe(0)
        // As duas linhas continuam no extrato: o conserto e um evento, e nao
        // um apagamento.
        ->and(LancamentoFinanceiro::count())->toBe(2)
        ->and($estorno->partidas->sum('valor_cents'))->toBe(0);
});

it('lanca o estorno na competencia de hoje, e nao na do original', function () {
    // Mes fechado continua com o numero que teve; a correcao pertence ao mes em
    // que foi decidida.
    $lancamento = lancar(NaturezaLancamento::Despesa, ['competencia' => '2026-01']);

    expect(estornar($lancamento)->competencia)->toBe(now()->format('Y-m'));
});

it('exige motivo, porque ele fica no extrato', function () {
    expect(fn () => estornar(lancar(NaturezaLancamento::Despesa), '  '))->toThrow(Recusa::class);
});

it('nao estorna duas vezes nem estorna um estorno', function () {
    $lancamento = lancar(NaturezaLancamento::Despesa);
    $estorno = estornar($lancamento);

    expect(fn () => estornar($lancamento))->toThrow(Recusa::class)
        ->and(fn () => estornar($estorno))->toThrow(Recusa::class);
});

/*
|--------------------------------------------------------------------------
| Quem entra no caixa da sociedade
|--------------------------------------------------------------------------
*/

it('nao abre para administrador sem a permissao', function () {
    // Nasce negada, inclusive para quem ja e admin: permissao que vem por
    // heranca e permissao que ninguem decidiu conceder.
    $admin = Staff::factory()->admin()->create(['super' => false, 'pode_socios' => false]);

    test()->actingAs($admin, 'staff')->withSession(['versao_staff' => 1])
        ->get(route('socios.index'))
        ->assertForbidden();
});

it('nao abre para vendedor, mesmo com a marca ligada por engano', function () {
    $vendedor = Staff::factory()->create(['papel' => 'vendedor', 'pode_socios' => true]);

    test()->actingAs($vendedor, 'staff')->withSession(['versao_staff' => 1])
        ->get(route('socios.index'))
        ->assertForbidden();
});

it('abre para administrador com a permissao', function () {
    $admin = Staff::factory()->admin()->create(['super' => false, 'pode_socios' => true]);

    test()->actingAs($admin, 'staff')->withSession(['versao_staff' => 1])
        ->get(route('socios.index'))
        ->assertOk();
});

it('some do menu de quem nao pode, em vez de levar a 403', function () {
    // Menu que leva a 403 ensina o operador a ignorar o menu.
    $semPermissao = Staff::factory()->admin()->create(['super' => false, 'pode_socios' => false]);

    test()->actingAs($semPermissao, 'staff')->withSession(['versao_staff' => 1]);

    $itens = collect(App\Helpers\MenuHelper::getMenuGroups()[0]['items'])->pluck('path');

    expect($itens)->not->toContain('/socios');
});

it('registra e estorna pela tela', function () {
    $admin = Staff::factory()->admin()->create(['super' => true]);
    $pedro = socio();

    $tela = test()->actingAs($admin, 'staff')->withSession(['versao_staff' => 1]);

    $tela->post(route('socios.registrar'), [
        'natureza' => 'despesa_do_socio',
        'descricao' => 'Hospedagem',
        'valor' => '100,00',
        'ocorrido_em' => now()->toDateString(),
        'socio_id' => $pedro->id,
    ])->assertRedirect();

    expect(saldo(ContaFinanceira::DESPESA))->toBe(10_000)
        ->and(saldo('emprestimo:'.$pedro->id))->toBe(10_000);

    $tela->post(route('socios.estornar', LancamentoFinanceiro::first()), ['motivo' => 'lancado errado'])
        ->assertRedirect();

    expect(saldo(ContaFinanceira::DESPESA))->toBe(0)
        ->and(saldo('emprestimo:'.$pedro->id))->toBe(0);
});
