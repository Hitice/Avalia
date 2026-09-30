<?php

use App\Contabil\SociosDaPlaquinha;
use App\Contabil\VendaDeEtiqueta;
use App\Models\Etiqueta;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/** @return array{0: Staff, 1: Staff} */
function sociosDoLastro(): array
{
    $emails = config('etiquetas.socios');

    return [
        Staff::factory()->admin()->create(['email' => $emails[0], 'nome' => 'Pedro']),
        Staff::factory()->admin()->create(['email' => $emails[1], 'nome' => 'Ruan']),
    ];
}

/*
|--------------------------------------------------------------------------
| Fase 3: o historico entra no razao
|--------------------------------------------------------------------------
*/

it('lanca a venda registrada com receita, custo e comissao separados', function () {
    sociosDoLastro();
    $warley = Staff::factory()->create(['papel' => 'vendedor', 'nome' => 'Warley']);

    Etiqueta::factory()->ativa()->count(2)->create(['vendedor_id' => $warley->id]);

    $this->artisan('avalia:lastrear-plaquinhas')->assertSuccessful();

    $valor = (int) config('etiquetas.precos.placa_cents');
    $custo = (int) config('etiquetas.custo_cents');
    $pct = (int) config('etiquetas.comissao_pct');

    $bruto = 2 * $valor;
    $custoTotal = 2 * $custo;

    // Comissao arredonda POR VENDA, e nao sobre o total do mes: e a regra que o
    // painel aplica, e somar diferente aqui reabriria a divergencia.
    $comissao = 2 * (int) round(($valor - $custo) * $pct / 100);

    expect(saldo('receita:plaquinha'))->toBe($bruto)
        ->and(saldo('custo:plaquinha'))->toBe($custoTotal)
        ->and(saldo('comissao'))->toBe($comissao)
        ->and(saldo('comissao-a-pagar'))->toBe($comissao)
        ->and(saldo('caixa'))->toBe($bruto - $custoTotal);
});

it('nao comissiona a venda do socio nem a venda orfa', function () {
    [$pedro] = sociosDoLastro();

    Etiqueta::factory()->ativa()->create(['vendedor_id' => $pedro->id]);
    Etiqueta::factory()->ativa()->create(['vendedor_id' => null]);

    $this->artisan('avalia:lastrear-plaquinhas')->assertSuccessful();

    // Comissao sem destinatario e dinheiro inventado: sairia da divisao dos
    // socios e deixaria o total maior que a soma das linhas por vendedor.
    expect(saldo('comissao'))->toBe(0)
        ->and(saldo('comissao-a-pagar'))->toBe(0);
});

it('rodar de novo nao duplica lancamento', function () {
    sociosDoLastro();
    $warley = Staff::factory()->create(['papel' => 'vendedor']);
    Etiqueta::factory()->ativa()->count(3)->create(['vendedor_id' => $warley->id]);

    $this->artisan('avalia:lastrear-plaquinhas')->assertSuccessful();
    $depoisDaPrimeira = saldo('receita:plaquinha');

    $this->artisan('avalia:lastrear-plaquinhas')->assertSuccessful();

    expect(saldo('receita:plaquinha'))->toBe($depoisDaPrimeira)
        ->and(DB::table('lancamentos_financeiros')->where('origem_tipo', 'etiqueta')->count())->toBe(3);
});

it('nao escreve nas etiquetas, que sao o dado que nao pode se mover', function () {
    sociosDoLastro();
    $warley = Staff::factory()->create(['papel' => 'vendedor']);
    $etiqueta = Etiqueta::factory()->ativa()->create(['vendedor_id' => $warley->id]);

    $antes = $etiqueta->only(['codigo', 'valor_cents', 'custo_cents', 'vendedor_id', 'vendida_em', 'updated_at']);

    $this->artisan('avalia:lastrear-plaquinhas')->assertSuccessful();

    expect($etiqueta->fresh()->only(array_keys($antes)))->toEqual($antes);
});

it('recusa lastrear quando um socio do config nao tem conta', function () {
    Etiqueta::factory()->ativa()->create();

    $this->artisan('avalia:lastrear-plaquinhas')->assertFailed();

    expect(DB::table('lancamentos_financeiros')->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Fase 4: a conciliacao
|--------------------------------------------------------------------------
*/

it('fecha o razao contra o numero que o painel mostra', function () {
    // Este e o teste que existe pela divergencia de R$ 169,84 contra R$ 148,61:
    // duas somas do mesmo dinheiro, uma no cartao e outra na tabela por vendedor,
    // e 899 testes passando. Agora as duas leem a mesma regra, e esta comparacao
    // recusa a volta do problema.
    sociosDoLastro();
    $warley = Staff::factory()->create(['papel' => 'vendedor']);
    $ruan = Staff::withTrashed()->where('nome', 'Ruan')->first();

    Etiqueta::factory()->ativa()->count(7)->create(['vendedor_id' => $warley->id]);
    Etiqueta::factory()->ativa()->count(2)->create(['vendedor_id' => $ruan->id]);
    Etiqueta::factory()->ativa()->create(['vendedor_id' => null]);

    $this->artisan('avalia:lastrear-plaquinhas')->assertSuccessful();

    $socios = SociosDaPlaquinha::resolver();

    $doPainel = ['bruto' => 0, 'custo' => 0, 'comissao' => 0];

    foreach (Etiqueta::whereNotNull('vendida_em')->get() as $etiqueta) {
        $parte = VendaDeEtiqueta::reparte($etiqueta, $socios['ids']);

        foreach ($doPainel as $chave => $acumulado) {
            $doPainel[$chave] = $acumulado + $parte[$chave];
        }
    }

    expect(saldo('receita:plaquinha'))->toBe($doPainel['bruto'])
        ->and(saldo('custo:plaquinha'))->toBe($doPainel['custo'])
        ->and(saldo('comissao'))->toBe($doPainel['comissao']);

    // A invariante do razao vale sobre o lastro inteiro, e nao lancamento a
    // lancamento: um erro de sinal em uma venda apareceria aqui.
    expect((int) DB::table('partidas_financeiras')->sum('valor_cents'))->toBe(0);
});

/*
|--------------------------------------------------------------------------
| O custo zero de 28/09/2026
|--------------------------------------------------------------------------
*/

it('conserta o custo zero e com isso a comissao que saiu inflada', function () {
    sociosDoLastro();
    $warley = Staff::factory()->create(['papel' => 'vendedor']);

    // Custo zero fazia o liquido virar o preco cheio, e a comissao de 25% sair
    // sobre R$ 89,90 em vez de sobre R$ 84,40.
    $etiqueta = Etiqueta::factory()->ativa()->create([
        'vendedor_id' => $warley->id,
        'custo_cents' => 0,
    ]);

    $custo = (int) config('etiquetas.custo_cents');
    $valor = (int) config('etiquetas.precos.placa_cents');
    $pct = (int) config('etiquetas.comissao_pct');

    expect(VendaDeEtiqueta::reparte($etiqueta, [])['comissao'])
        ->toBe((int) round($valor * $pct / 100), 'antes do conserto a comissao sai sobre o preco cheio');

    (require database_path('migrations/2026_09_29_000009_custo_zero_das_placas_de_28_de_setembro.php'))->up();

    expect($etiqueta->fresh()->custo_cents)->toBe($custo)
        ->and(VendaDeEtiqueta::reparte($etiqueta->fresh(), [])['comissao'])
        ->toBe((int) round(($valor - $custo) * $pct / 100));
});

it('nao regrava zero se a chave do custo sair do config', function () {
    // A migration de 28/09 gravou zero justamente porque leu uma chave que ainda
    // nao existia. Corrigir sem essa guarda repetiria o defeito na proxima vez.
    sociosDoLastro();
    $etiqueta = Etiqueta::factory()->ativa()->create(['custo_cents' => 0]);

    config(['etiquetas.custo_cents' => 0]);
    (require database_path('migrations/2026_09_29_000009_custo_zero_das_placas_de_28_de_setembro.php'))->up();

    expect($etiqueta->fresh()->custo_cents)->toBe(0);
});
