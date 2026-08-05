<?php

use App\Actions\Consumo\FecharCompetencia;
use App\Models\Auditoria;
use App\Models\Catalogo;
use App\Models\Cliente;
use App\Models\Plano;
use App\Models\Staff;
use App\Support\Comissao;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Cadastro
|--------------------------------------------------------------------------
*/

it('nao deixa vendedor abrir a equipe', function () {
    // Quem define a propria comissao nao pode ser quem a recebe.
    $this->actingAs(Staff::factory()->create(['papel' => 'vendedor']), 'staff')
        ->withSession(['versao_staff' => 1])
        ->get(route('equipe.index'))
        ->assertForbidden();
});

it('cadastra vendedor com a comissao negociada', function () {
    admin()->post(route('equipe.salvar'), [
        'nome' => 'Vendedor Novo',
        'email' => 'novo@avalia.com.br',
        'senha' => 'senha-valida-123',
        'papel' => 'vendedor',
        'comissao_pct' => 15,
        'ativo' => '1',
    ])->assertRedirect(route('equipe.index'));

    expect(Staff::firstWhere('email', 'novo@avalia.com.br')->comissao_pct)->toBe(15);
});

it('comeca em 10% quando ninguem informa outra coisa', function () {
    expect(Staff::factory()->create()->comissao_pct)->toBe(Comissao::PCT_PADRAO);
});

it('recusa comissao acima do teto', function () {
    // Um zero a mais digitado por engano viraria repasse no fechamento.
    admin()->post(route('equipe.salvar'), [
        'nome' => 'Vendedor Caro',
        'email' => 'caro@avalia.com.br',
        'senha' => 'senha-valida-123',
        'papel' => 'vendedor',
        'comissao_pct' => 100,
        'ativo' => '1',
    ])->assertSessionHasErrors('comissao_pct');

    expect(Staff::firstWhere('email', 'caro@avalia.com.br'))->toBeNull();
});

it('derruba a sessao ao trocar o papel', function () {
    // A sessao aberta continuaria com a permissao antiga ate o cookie expirar.
    $membro = Staff::factory()->create(['papel' => 'vendedor']);
    $versao = $membro->sessao_versao;

    admin()->put(route('equipe.atualizar', $membro), [
        'nome' => $membro->nome,
        'email' => $membro->email,
        'papel' => 'admin',
        'comissao_pct' => 10,
        'ativo' => '1',
    ]);

    expect($membro->fresh()->sessao_versao)->toBeGreaterThan($versao);
});

it('registra na auditoria a mudanca de comissao', function () {
    $membro = Staff::factory()->create(['papel' => 'vendedor', 'comissao_pct' => 10]);

    admin()->put(route('equipe.atualizar', $membro), [
        'nome' => $membro->nome,
        'email' => $membro->email,
        'papel' => 'vendedor',
        'comissao_pct' => 20,
        'ativo' => '1',
    ]);

    expect(Auditoria::where('acao', 'equipe.alterada')->count())->toBe(1)
        ->and($membro->fresh()->comissao_pct)->toBe(20);
});

it('nao registra auditoria quando so o nome muda', function () {
    $membro = Staff::factory()->create(['papel' => 'vendedor', 'comissao_pct' => 10]);

    admin()->put(route('equipe.atualizar', $membro), [
        'nome' => 'Nome Corrigido',
        'email' => $membro->email,
        'papel' => 'vendedor',
        'comissao_pct' => 10,
        'ativo' => '1',
    ]);

    expect(Auditoria::where('acao', 'equipe.alterada')->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Efeito no fechamento
|--------------------------------------------------------------------------
*/

it('usa a comissao do vendedor da carteira, e nao a do sistema', function () {
    $catalogo = Catalogo::factory()->comServico('scpc-bvs', [90_000 => 324])->create();
    $plano = Plano::factory()->consumoMinimo(900)->create([
        'catalogo_id' => $catalogo->id,
        'mensalidade_cents' => 7_990,
    ]);

    $generoso = Staff::factory()->create(['papel' => 'vendedor', 'comissao_pct' => 20]);
    $padrao = Staff::factory()->create(['papel' => 'vendedor', 'comissao_pct' => 10]);

    $fecha = fn (Staff $vendedor) => app(FecharCompetencia::class)(
        Cliente::factory()->create(['plano_id' => $plano->id, 'vendedor_id' => $vendedor->id]),
        '2026-07',
    )['fatura'];

    $comDobro = $fecha($generoso);
    $comPadrao = $fecha($padrao);

    expect($comDobro->comissao_pct)->toBe(20)
        ->and($comPadrao->comissao_pct)->toBe(10)
        ->and($comDobro->comissao_cents)->toBe($comPadrao->comissao_cents * 2)
        // O lucro da Avalia cai na mesma medida: a comissao sai de la.
        ->and($comDobro->lucro_cents)->toBeLessThan($comPadrao->lucro_cents);
});

it('nao reescreve competencia fechada ao renegociar a taxa', function () {
    // A fatura guarda o percentual da emissao. Sem isso, mudar a taxa hoje
    // mudaria o valor de um repasse que ja foi combinado.
    $catalogo = Catalogo::factory()->comServico('scpc-bvs', [90_000 => 324])->create();
    $plano = Plano::factory()->consumoMinimo(900)->create(['catalogo_id' => $catalogo->id]);
    $vendedor = Staff::factory()->create(['papel' => 'vendedor', 'comissao_pct' => 10]);
    $empresa = Cliente::factory()->create(['plano_id' => $plano->id, 'vendedor_id' => $vendedor->id]);

    $fatura = app(FecharCompetencia::class)($empresa, '2026-07')['fatura'];
    $comissao = $fatura->comissao_cents;

    $vendedor->update(['comissao_pct' => 30]);

    expect($fatura->fresh()->comissao_pct)->toBe(10)
        ->and($fatura->fresh()->comissao_cents)->toBe($comissao);
});

it('cai no padrao quando a empresa esta sem vendedor', function () {
    $catalogo = Catalogo::factory()->comServico('scpc-bvs', [90_000 => 324])->create();
    $plano = Plano::factory()->consumoMinimo(900)->create(['catalogo_id' => $catalogo->id]);
    $empresa = Cliente::factory()->create(['plano_id' => $plano->id, 'vendedor_id' => null]);

    $fatura = app(FecharCompetencia::class)($empresa, '2026-07')['fatura'];

    expect($fatura->comissao_pct)->toBe(Comissao::PCT_PADRAO);
});

it('ignora taxa fora da faixa em vez de pagar errado', function () {
    expect(Comissao::pct(null))->toBe(10)
        ->and(Comissao::pct(-5))->toBe(10)
        ->and(Comissao::pct(90))->toBe(10)
        ->and(Comissao::pct(20))->toBe(20);
});
