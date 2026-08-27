<?php

use App\Enums\SituacaoLead;
use App\Models\Lead;
use Database\Seeders\LeadsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| A carga da base de prospeccao
|--------------------------------------------------------------------------
|
| O arquivo de dados e gerado por tools/gera_leads.py a partir de
| docs/leads.html. Estes testes travam o que a carga promete: chega inteira,
| chega normalizada, e rodar de novo nao duplica nem desfaz correcao da tela.
|
*/

it('carrega a base inteira sem duplicar codigo', function () {
    $this->seed(LeadsSeeder::class);

    expect(Lead::count())->toBe(1037)
        ->and(Lead::distinct()->count('codigo'))->toBe(1037);
});

it('guarda o CNPJ sem mascara, para casar com a carteira de clientes', function () {
    $this->seed(LeadsSeeder::class);

    $lead = Lead::firstWhere('codigo', '13694');

    expect($lead->cnpj)->toBe('08876860000103')
        ->and(Lead::whereNotNull('cnpj')->count())->toBe(891);
});

/**
 * O marcador "(INATIVO)" vinha colado no comeco do nome na base de origem.
 * Nome nao e lugar de guardar situacao: o vendedor le o nome em voz alta no
 * telefone. E a migration do funil o traduziu para "nao atender", preservando
 * no cadastro o porque, que era a unica informacao que o marcador carregava.
 */
it('tira o marcador de inativo do nome e o transforma em estagio do funil', function () {
    $this->seed(LeadsSeeder::class);

    $bloqueado = Lead::firstWhere('codigo', '13694');

    expect($bloqueado->nome)->toBe('CESTA MAIS COMERCIO DE ALIMENTOS LTDA')
        ->and($bloqueado->situacao)->toBe(SituacaoLead::Bloqueado)
        ->and($bloqueado->observacao)->toContain('INATIVO na base de origem')
        ->and(Lead::where('nome', 'like', '%INATIVO%')->count())->toBe(0);
});

it('poe todo o resto da base como lead novo, que e o unico estagio possivel', function () {
    $this->seed(LeadsSeeder::class);

    expect(Lead::where('situacao', SituacaoLead::Novo)->count())->toBe(1036)
        ->and(Lead::whereNotNull('agendado_para')->count())->toBe(0)
        ->and(Lead::whereNotNull('cliente_id')->count())->toBe(0);
});

it('roda duas vezes sem duplicar nada', function () {
    $this->seed(LeadsSeeder::class);
    $this->seed(LeadsSeeder::class);

    expect(Lead::count())->toBe(1037);
});

it('nao desfaz correcao feita na tela nem remocao', function () {
    $this->seed(LeadsSeeder::class);

    $corrigido = Lead::firstWhere('codigo', '13694');
    $corrigido->update(['telefone' => '(31) 99999-0000']);

    $descartado = Lead::firstWhere('codigo', '26779');
    $descartado->delete();

    $this->seed(LeadsSeeder::class);

    expect($corrigido->fresh()->telefone)->toBe('(31) 99999-0000')
        // Lead removido nao volta pela carga: a remocao foi uma decisao.
        ->and(Lead::withTrashed()->where('codigo', '26779')->count())->toBe(1)
        ->and(Lead::count())->toBe(1036);
});

it('mostra a base carregada na tela da administracao', function () {
    $this->seed(LeadsSeeder::class);

    admin()->get(route('leads.index'))
        ->assertOk()
        // Os numeros do recorte, que e a base inteira quando nao ha filtro.
        ->assertSee('1.037')
        // A primeira pagina, em ordem de nome. A base nao cabe numa tela.
        ->assertSee('1100 MOTOR PECAS LTDA');

    // E o lead do meio da base se acha pelo filtro, nao rolando mil linhas.
    admin()->get(route('leads.index', ['busca' => 'CESTA MAIS']))
        ->assertOk()
        ->assertSee('CESTA MAIS COMERCIO DE ALIMENTOS LTDA');
});
