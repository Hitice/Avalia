<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
 // ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Sessao de administrador pronta para pedir uma rota de gestao.
 *
 * O `versao_staff` na sessao nao e detalhe de teste: sem ele o ConfereSessao
 * derruba a sessao na primeira requisicao, e todo teste de tela viraria um
 * redirect para /entrar.
 */
function admin(): Tests\TestCase
{
    return test()
        ->actingAs(App\Models\Staff::factory()->admin()->create(), 'staff')
        ->withSession(['versao_staff' => 1]);
}

/**
 * Payload de aceite valido para os testes: nome, confirmacao de leitura e o
 * hash do conteudo vigente, como a tela envia.
 */
function aceiteValido(App\Models\DocumentoLegal $documento): array
{
    return [
        'responsavel' => 'Fulana Responsável de Teste',
        'li' => '1',
        'hash' => $documento->hashConteudo(),
    ];
}

/**
 * Uma empresa com plano na faixa de R$ 900 e vendedor na carteira.
 *
 * Vive aqui, e nao no arquivo que a criou, porque metade da suite precisa de
 * uma empresa completa: helper em arquivo de teste so existe quando aquele
 * arquivo carrega, e rodar um teste sozinho quebraria.
 */
function empresaComPlano(array $atributos = []): App\Models\Cliente
{
    $catalogo = App\Models\Catalogo::factory()->comServico('scpc-bvs', [90_000 => 324])->create();
    $catalogo->precos()->update(['custo_cents' => 150]);

    $plano = App\Models\Plano::factory()->consumoMinimo(900)->create([
        'catalogo_id' => $catalogo->id,
        'mensalidade_cents' => 7_990,
    ]);

    return App\Models\Cliente::factory()->create($atributos + [
        'plano_id' => $plano->id,
        'vendedor_id' => App\Models\Staff::factory()->create(['papel' => 'vendedor'])->id,
        'situacao' => 'ativo',
    ]);
}

/** Entra como a empresa contratante. */
function comoEmpresa(App\Models\Cliente $empresa): Tests\TestCase
{
    return test()->actingAs($empresa, 'empresa')
        ->withSession(['versao_empresa' => $empresa->sessao_versao]);
}
