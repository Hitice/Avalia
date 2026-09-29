<?php

use App\Models\Auditoria;
use App\Models\Cliente;
use App\Models\Consulta;
use App\Models\Operador;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| A limpeza dos cadastros que abriram a operacao
|--------------------------------------------------------------------------
|
| Exclusao definitiva nao se desfaz. Estes testes existem para a migration
| apagar exatamente o que ela promete, e parar quando encontra o que nao
| examinou.
|
*/

function rodarLimpeza(): void
{
    (require database_path('migrations/2026_09_29_000006_remove_cadastros_de_teste.php'))->up();
}

it('apaga a empresa de teste com o historico dela', function () {
    $empresa = empresaComPlano(['razao_social' => 'Catech Industria', 'email' => 'catech@teste.com.br']);
    Operador::factory()->create(['cliente_id' => $empresa->id]);
    Consulta::factory()->create(['cliente_id' => $empresa->id]);

    rodarLimpeza();

    expect(Cliente::withTrashed()->count())->toBe(0)
        // O cascade do banco leva consulta e operador junto.
        ->and(Consulta::count())->toBe(0)
        ->and(Operador::withTrashed()->count())->toBe(0);
});

it('apaga a conta depois da empresa, na ordem certa', function () {
    // A carteira e as faturas que prendiam a conta eram da empresa. Com ela
    // fora, a conta deixa de ter historico e sai pelo mesmo criterio da tela.
    $conta = Staff::factory()->create(['email' => 'pedromuska@gmail.com']);
    empresaComPlano([
        'razao_social' => 'Catech Industria',
        'email' => 'catech@teste.com.br',
        'vendedor_id' => $conta->id,
    ]);

    rodarLimpeza();

    expect(Staff::withTrashed()->where('email', 'pedromuska@gmail.com')->count())->toBe(0);
});

it('nao apaga a conta quando sobra historico que ninguem examinou', function () {
    // A guarda que impede a limpeza de teste levar junto o que nao foi olhado.
    $conta = Staff::factory()->create(['email' => 'pedromuska@gmail.com']);

    // Uma segunda empresa, que NAO e a de teste, continua na carteira dela.
    empresaComPlano(['razao_social' => 'Cliente de verdade', 'email' => 'real@empresa.com.br', 'vendedor_id' => $conta->id]);
    empresaComPlano(['razao_social' => 'Catech Industria', 'email' => 'catech@teste.com.br']);

    rodarLimpeza();

    expect(Staff::withTrashed()->where('email', 'pedromuska@gmail.com')->count())->toBe(1)
        // A empresa de teste sai de qualquer jeito: ela nao depende da conta.
        ->and(Cliente::withTrashed()->where('razao_social', 'like', '%Catech%')->count())->toBe(0);
});

it('para tambem quando a conta tem trilha de auditoria', function () {
    // Com trilha, o `staff_id` zerado pelo nullOnDelete quebraria o resumo
    // encadeado, porque ele entra no hash.
    $conta = Staff::factory()->create(['email' => 'pedromuska@gmail.com']);

    Auditoria::create([
        'staff_id' => $conta->id,
        'acao' => 'equipe.criada',
        'dados' => [],
        'ocorreu_em' => now(),
        'resumo' => 'x',
    ]);

    rodarLimpeza();

    expect(Staff::withTrashed()->where('email', 'pedromuska@gmail.com')->count())->toBe(1);
});

it('nao reclama quando nao ha nada para apagar', function () {
    // Publicacao repetida nao pode falhar por encontrar o trabalho feito.
    rodarLimpeza();
    rodarLimpeza();

    expect(Cliente::withTrashed()->count())->toBe(0);
});
