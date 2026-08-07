<?php

use App\Models\Operador;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/**
 * Trocar a propria senha estando dentro.
 *
 * Faltava por completo: quem estava logado so trocava a senha saindo e
 * pedindo recuperacao, que e caminho de emergencia e nao de manutencao. Vale
 * para as tres naturezas de conta.
 */
it('troca a senha do staff exigindo a atual', function () {
    $staff = Staff::factory()->admin()->create();

    admin()->post(route('perfil.senha'), [
        'senha_atual' => 'errada-de-proposito',
        'senha' => 'senha-nova-do-admin',
        'senha_confirmation' => 'senha-nova-do-admin',
    ])->assertSessionHasErrors('senha_atual');

    $este = test()->actingAs($staff, 'staff')->withSession(['versao_staff' => $staff->sessao_versao]);

    $este->post(route('perfil.senha'), [
        'senha_atual' => 'senha-valida-123',
        'senha' => 'senha-nova-do-admin',
        'senha_confirmation' => 'senha-nova-do-admin',
    ])->assertSessionHas('ok');

    expect(Hash::check('senha-nova-do-admin', $staff->fresh()->senha))->toBeTrue();
});

it('recusa senha curta ou repetida errada', function () {
    admin()->post(route('perfil.senha'), [
        'senha_atual' => 'senha-valida-123',
        'senha' => 'curta',
        'senha_confirmation' => 'curta',
    ])->assertSessionHasErrors('senha');

    admin()->post(route('perfil.senha'), [
        'senha_atual' => 'senha-valida-123',
        'senha' => 'senha-longa-o-suficiente',
        'senha_confirmation' => 'outra-coisa-diferente',
    ])->assertSessionHasErrors('senha');
});

it('derruba as outras sessoes e mantem a de quem trocou', function () {
    // Quem troca a senha suspeitando de acesso indevido espera exatamente
    // isso: os outros caem, e quem trocou continua trabalhando.
    $staff = Staff::factory()->admin()->create();
    $versaoAntes = $staff->sessao_versao;

    test()->actingAs($staff, 'staff')->withSession(['versao_staff' => $versaoAntes])
        ->post(route('perfil.senha'), [
            'senha_atual' => 'senha-valida-123',
            'senha' => 'senha-nova-do-admin',
            'senha_confirmation' => 'senha-nova-do-admin',
        ])->assertSessionHas('ok');

    expect($staff->fresh()->sessao_versao)->toBeGreaterThan($versaoAntes)
        ->and(session('versao_staff'))->toBe($staff->fresh()->sessao_versao);
});

it('troca a senha da empresa e a do operador, cada uma a sua', function () {
    $empresa = empresaComPlano();
    $operador = Operador::factory()->create(['cliente_id' => $empresa->id, 'email' => 'op@empresa.com.br']);

    comoEmpresa($empresa)->post(route('perfil.senha'), [
        'senha_atual' => 'senha-valida-123',
        'senha' => 'senha-nova-da-empresa',
        'senha_confirmation' => 'senha-nova-da-empresa',
    ])->assertSessionHas('ok');

    expect(Hash::check('senha-nova-da-empresa', $empresa->fresh()->senha))->toBeTrue();

    // O operador entra pela sessao da empresa, mas troca a SUA senha.
    $this->flushSession();
    app('auth')->forgetGuards();
    $this->post('/entrar', ['email' => 'op@empresa.com.br', 'senha' => 'senha-valida-123']);

    $this->post(route('perfil.senha'), [
        'senha_atual' => 'senha-valida-123',
        'senha' => 'senha-nova-do-operador',
        'senha_confirmation' => 'senha-nova-do-operador',
    ])->assertSessionHas('ok');

    expect(Hash::check('senha-nova-do-operador', $operador->fresh()->senha))->toBeTrue()
        // A da empresa continua a que ela definiu.
        ->and(Hash::check('senha-nova-da-empresa', $empresa->fresh()->senha))->toBeTrue();
});

it('nao deixa quem nao entrou abrir a propria conta', function () {
    $this->get(route('perfil'))->assertRedirect(route('entrar'));
});
