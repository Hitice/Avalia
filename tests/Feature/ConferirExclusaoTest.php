<?php

use App\Models\Auditoria;
use App\Models\Cliente;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| O que impede excluir em definitivo
|--------------------------------------------------------------------------
|
| A tela recusa com um "nao" curto. Este comando diz quanto e de que, porque
| sem SSH a unica forma de olhar o banco e por cron, e la nao cabe consulta com
| string PHP: o campo de comando so aceita dois niveis de aspas.
|
*/

it('diz que pode excluir quando nao ha historico', function () {
    $staff = Staff::factory()->create(['email' => 'teste@avalia.com.br']);
    $staff->delete();

    $this->artisan('avalia:conferir-exclusao', ['--email' => 'teste@avalia.com.br'])
        ->expectsOutputToContain('pode excluir')
        ->assertSuccessful();
});

it('denuncia a trilha de auditoria, que e o que costuma prender', function () {
    // A conta que operou o sistema tem centenas de linhas na trilha. Apagar a
    // linha de staff deixaria a auditoria apontando para um id que nao existe:
    // a corrente continuaria fechando e "quem fez isso" viraria pergunta sem
    // resposta.
    $staff = Staff::factory()->admin()->create(['email' => 'operou@avalia.com.br']);

    Auditoria::create([
        'staff_id' => $staff->id,
        'acao' => 'equipe.criada',
        'dados' => [],
        'ocorreu_em' => now(),
        'resumo' => 'x',
    ]);

    $this->artisan('avalia:conferir-exclusao', ['--email' => 'operou@avalia.com.br'])
        ->expectsOutputToContain('trilha=1')
        ->expectsOutputToContain('NAO pode excluir')
        ->assertSuccessful();
});

it('conta o historico da empresa separado por tipo', function () {
    $empresa = Cliente::factory()->create(['razao_social' => 'Catech Industria']);

    $this->artisan('avalia:conferir-exclusao', ['--empresa' => 'Catech'])
        ->expectsOutputToContain('faturas=0 consultas=0 aceites=0')
        ->expectsOutputToContain('pode excluir')
        ->assertSuccessful();
});

it('nao inventa registro que nao existe', function () {
    $this->artisan('avalia:conferir-exclusao', ['--email' => 'ninguem@x.com'])
        ->expectsOutputToContain('nao existe')
        ->assertSuccessful();
});
