<?php

use App\Models\Auditoria;
use App\Models\Cliente;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Exclusao definitiva: so removido e so sem historico.
 *
 * A regra da casa continua valendo: onde ha consulta, fatura ou aceite nao
 * existe exclusao, existe desativacao. O definitivo serve para o cadastro de
 * teste ou duplicado que nunca operou, e mesmo ele deixa o nome congelado na
 * trilha.
 */
it('exclui em definitivo empresa removida sem historico', function () {
    $empresa = Cliente::factory()->create(['razao_social' => 'Cadastro de Teste LTDA']);
    $empresa->delete();

    admin()->delete(route('empresas.excluir', $empresa->id))
        ->assertRedirect(route('empresas.index', ['removidas' => 1]));

    expect(Cliente::withTrashed()->find($empresa->id))->toBeNull()
        ->and(Auditoria::where('acao', 'empresa.excluida')->sole()->entidade_rotulo)->toBe('Cadastro de Teste LTDA');
});

it('recusa excluir empresa com historico', function () {
    $empresa = empresaComPlano();
    app(App\Actions\Consumo\FecharCompetencia::class)($empresa, '2026-07');
    $empresa->delete();

    admin()->from(route('empresas.index', ['removidas' => 1]))
        ->delete(route('empresas.excluir', $empresa->id))
        ->assertSessionHas('erro');

    expect(Cliente::onlyTrashed()->find($empresa->id))->not->toBeNull();
});

it('so exclui empresa que ja foi removida', function () {
    $empresa = Cliente::factory()->create();

    admin()->delete(route('empresas.excluir', $empresa->id))->assertNotFound();
});

it('exclui em definitivo membro removido sem carteira', function () {
    $membro = Staff::factory()->create(['papel' => 'vendedor']);
    $membro->delete();

    admin()->delete(route('equipe.excluir', $membro->id))->assertSessionHas('ok');

    expect(Staff::withTrashed()->find($membro->id))->toBeNull();
});

it('recusa excluir membro com carteira', function () {
    [$vendedor] = carteira();
    $vendedor->delete();

    admin()->delete(route('equipe.excluir', $vendedor->id))->assertSessionHas('erro');

    expect(Staff::onlyTrashed()->find($vendedor->id))->not->toBeNull();
});

it('registra na trilha a mudanca de situacao da empresa', function () {
    $empresa = Cliente::factory()->create(['situacao' => 'ativo', 'cnpj' => '12345678000195']);

    admin()->put(route('empresas.atualizar', $empresa), [
        'razao_social' => $empresa->razao_social,
        'cnpj' => $empresa->cnpj,
        'email' => $empresa->email,
        'situacao' => 'bloqueado',
    ]);

    expect($empresa->fresh()->situacao)->toBe('bloqueado');

    $registro = Auditoria::where('acao', 'empresa.situacao')->sole();

    // Antes e depois em lingua de gente, nao chave de banco.
    expect($registro->dados['de'])->toBe('Ativa')
        ->and($registro->dados['para'])->toBe('Bloqueada');
});
