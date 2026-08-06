<?php

use App\Models\Catalogo;
use App\Models\Cliente;
use App\Models\Plano;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Quem entra
|--------------------------------------------------------------------------
*/

it('nao deixa vendedor abrir a lista de empresas', function () {
    // A ficha mostra custo e lucro por fatura, que sao internos.
    $this->actingAs(Staff::factory()->create(), 'staff')
        ->withSession(['versao_staff' => 1])
        ->get(route('empresas.index'))
        ->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| Cadastro
|--------------------------------------------------------------------------
*/

it('cadastra a empresa com plano e vendedor', function () {
    $plano = Plano::factory()->consumoMinimo(900)->create();
    $vendedor = Staff::factory()->create();

    admin()->post(route('empresas.salvar'), [
        'razao_social' => 'Padaria do Bairro LTDA',
        'cnpj' => '12.345.678/0001-95',
        'email' => 'contato@padaria.com.br',
        'senha' => 'senha-valida-123',
        'situacao' => 'ativo',
        'plano_id' => $plano->id,
        'vendedor_id' => $vendedor->id,
    ])->assertRedirect();

    $empresa = Cliente::firstWhere('razao_social', 'Padaria do Bairro LTDA');

    // Guardado sem pontuacao, para comparar documento sem depender de formato.
    expect($empresa->cnpj)->toBe('12345678000195')
        ->and($empresa->cnpjRotulo())->toBe('12.345.678/0001-95')
        ->and($empresa->plano_id)->toBe($plano->id)
        ->and($empresa->vendedor_id)->toBe($vendedor->id);
});

it('recusa CNPJ com digito verificador errado', function () {
    admin()->post(route('empresas.salvar'), [
        'razao_social' => 'Empresa Qualquer',
        'cnpj' => '12.345.678/0001-00',
        'email' => 'a@b.com.br',
        'senha' => 'senha-valida-123',
        'situacao' => 'ativo',
    ])->assertSessionHasErrors('cnpj');

    expect(Cliente::count())->toBe(0);
});

it('recusa o mesmo CNPJ duas vezes, com ou sem pontuacao', function () {
    Cliente::factory()->create(['cnpj' => '12345678000195']);

    admin()->post(route('empresas.salvar'), [
        'razao_social' => 'Outra Empresa',
        'cnpj' => '12.345.678/0001-95',
        'email' => 'outra@empresa.com.br',
        'senha' => 'senha-valida-123',
        'situacao' => 'ativo',
    ])->assertSessionHasErrors('cnpj');
});

it('mantem a senha quando o campo volta vazio', function () {
    $empresa = Cliente::factory()->create(['cnpj' => '12345678000195']);
    $senha = $empresa->senha;

    admin()->put(route('empresas.atualizar', $empresa), [
        'razao_social' => 'Nome Novo LTDA',
        'cnpj' => '12345678000195',
        'email' => $empresa->email,
        'situacao' => 'ativo',
        'senha' => '',
    ])->assertRedirect();

    expect($empresa->fresh()->razao_social)->toBe('Nome Novo LTDA')
        ->and($empresa->fresh()->senha)->toBe($senha);
});

it('derruba a sessao ao encerrar o contrato', function () {
    // Sem isso a empresa continua dentro do sistema ate o cookie expirar.
    $empresa = Cliente::factory()->create(['cnpj' => '12345678000195']);
    $versao = $empresa->sessao_versao;

    admin()->put(route('empresas.atualizar', $empresa), [
        'razao_social' => $empresa->razao_social,
        'cnpj' => $empresa->cnpj,
        'email' => $empresa->email,
        'situacao' => 'inativo',
    ]);

    expect($empresa->fresh()->sessao_versao)->toBeGreaterThan($versao);
});

/*
|--------------------------------------------------------------------------
| Ficha e fluxo
|--------------------------------------------------------------------------
*/

it('fecha a competência exibida na ficha', function () {
    $catalogo = Catalogo::factory()->comServico('scpc-bvs', [90_000 => 324])->create();
    $catalogo->precos()->update(['custo_cents' => 150]);

    $plano = Plano::factory()->consumoMinimo(900)->create([
        'catalogo_id' => $catalogo->id,
        'mensalidade_cents' => 7_990,
    ]);

    $empresa = Cliente::factory()->create(['plano_id' => $plano->id, 'cnpj' => '12345678000195']);
    admin()->post(route('empresas.fechar', $empresa))->assertSessionHas('ok');

    $fatura = $empresa->faturas()->first();

    expect($fatura->consumo_realizado_cents)->toBe(0)
        ->and($fatura->consumo_faturado_cents)->toBe(90_000)   // o piso do plano
        ->and($fatura->total_cents)->toBe(97_990);

    // A ficha passa a mostrar a fatura fechada.
    admin()->get(route('empresas.ficha', $empresa))
        ->assertOk()
        ->assertSee("R$\u{00A0}979,90", false);
});

it('abre a ficha de empresa sem plano sem quebrar', function () {
    $empresa = Cliente::factory()->create();

    admin()->get(route('empresas.ficha', $empresa))
        ->assertOk()
        ->assertSee('Sem plano contratado');
});

it('calcula o fim da vigencia a partir do tipo, e nao de digitacao', function () {
    // 12 meses e um prazo fechado: o fim decorre do inicio. Aceitar um fim
    // digitado ao lado do prazo era ambiguidade virando dado divergente.
    admin()->post(route('empresas.salvar'), [
        'razao_social' => 'Vigente LTDA',
        'cnpj' => '12.345.678/0001-95',
        'email' => 'vigente@cliente.com.br',
        'situacao' => 'ativo',
        'vigencia_tipo' => '12_meses',
        'contrato_inicio' => '2026-08-06',
        'contrato_fim' => '2030-01-01',
    ]);

    $empresa = App\Models\Cliente::firstWhere('email', 'vigente@cliente.com.br');

    expect($empresa->contrato_fim->toDateString())->toBe('2027-08-06')
        ->and($empresa->carencia_ate)->toBeNull();
});

it('sem vigencia nao guarda data nenhuma', function () {
    admin()->post(route('empresas.salvar'), [
        'razao_social' => 'Aberta LTDA',
        'cnpj' => '11.222.333/0001-81',
        'email' => 'aberta@cliente.com.br',
        'situacao' => 'ativo',
        'vigencia_tipo' => 'sem_vigencia',
        'contrato_fim' => '2030-01-01',
        'carencia_ate' => '2030-01-01',
    ]);

    $empresa = App\Models\Cliente::firstWhere('email', 'aberta@cliente.com.br');

    expect($empresa->contrato_fim)->toBeNull()
        ->and($empresa->carencia_ate)->toBeNull();
});
