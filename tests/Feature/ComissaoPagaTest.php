<?php

use App\Models\Etiqueta;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| A sexta-feira do vendedor
|--------------------------------------------------------------------------
*/

function vendedorComDuasVendas(): array
{
    $emails = config('etiquetas.socios');
    Staff::factory()->admin()->create(['email' => $emails[0]]);
    Staff::factory()->admin()->create(['email' => $emails[1]]);
    $maria = Staff::factory()->create(['papel' => 'vendedor', 'nome' => 'Maria']);
    Etiqueta::factory()->ativa()->count(2)->create(['vendedor_id' => $maria->id]);
    test()->artisan('avalia:lastrear-plaquinhas')->assertSuccessful();

    $comissao = 2 * (int) round((int) config('etiquetas.precos.placa_cents') * (int) config('etiquetas.comissao_pct') / 100);

    return [$maria, $comissao];
}

it('zera a comissao atual do vendedor e baixa a divida no razao', function () {
    [$maria, $comissao] = vendedorComDuasVendas();
    expect(saldo('comissao-a-pagar'))->toBe($comissao);

    admin()->from(route('plaquinhas.vendas'))->followingRedirects()
        ->post(route('plaquinhas.comissao.pagar', $maria))
        ->assertOk()->assertSee('Comissão de Maria paga');

    expect(Etiqueta::comissaoEmAberto()->count())->toBe(0)
        ->and(saldo('comissao-a-pagar'))->toBe(0)
        // A despesa fica: foi reconhecida na venda, e o pagamento nao e outra.
        ->and(saldo('comissao'))->toBe($comissao);

    $html = comoVendedor($maria)->get(route('sales.inicio'))->assertOk()->getContent();

    expect($html)->toContain(App\Support\Dinheiro::brl(0))
        ->and($html)->toContain(App\Support\Dinheiro::brl($comissao));
});

it('nao paga duas vezes nem paga socio', function () {
    [$maria] = vendedorComDuasVendas();
    admin()->post(route('plaquinhas.comissao.pagar', $maria));

    admin()->from(route('plaquinhas.vendas'))->post(route('plaquinhas.comissao.pagar', $maria))
        ->assertRedirect(route('plaquinhas.vendas'))->assertSessionHas('erro');

    $socio = Staff::firstWhere('email', config('etiquetas.socios')[0]);
    admin()->from(route('plaquinhas.vendas'))->post(route('plaquinhas.comissao.pagar', $socio))
        ->assertSessionHas('erro');

    expect(App\Models\LancamentoFinanceiro::where('natureza', 'pagamento')->count())->toBe(1);
});

it('mostra ao admin quem tem comissao a pagar, e ao vendedor nao abre', function () {
    [$maria, $comissao] = vendedorComDuasVendas();

    $html = admin()->get(route('plaquinhas.vendas'))->assertOk()->getContent();
    expect($html)->toContain('A pagar')->and($html)->toContain('Pagar '.App\Support\Dinheiro::brl($comissao))
        ->and($html)->toContain('Nenhuma comissão paga ainda');

    admin()->post(route('plaquinhas.comissao.pagar', $maria));
    $depois = admin()->get(route('plaquinhas.vendas'))->assertOk()->getContent();
    expect($depois)->toContain('Pagas')->toContain(now()->format('d/m H:i'))->toContain('Maria');

    comoVendedor($maria)->post(route('plaquinhas.comissao.pagar', $maria))->assertForbidden();
});
