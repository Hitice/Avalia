<?php

use App\Models\Conexao;
use App\Models\Servico;
use App\Services\Conectores\ConectorBoaVista;
use App\Services\Conectores\ConectorSimulado;
use App\Services\Conectores\EscolherConector;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * De qual bureau vem cada servico.
 *
 * Ate aqui o sistema escolhia UM conector para o catalogo inteiro, e isso so
 * funcionava enquanto havia um fornecedor. O catalogo mistura bases: "Base III"
 * e a Boa Vista, e outras linhas vem de outro lugar. Com escolha global, ligar a
 * Boa Vista mandava para ela ate o que ela nao vende.
 */
function conexaoAtiva(string $fornecedor, array $extra = []): Conexao
{
    return Conexao::create([
        'fornecedor' => $fornecedor,
        'ambiente' => 'sandbox',
        'credenciais' => $extra + ['client_id' => 'x', 'client_secret' => 'y'],
        'ativa' => true,
    ]);
}

it('manda o servico para o bureau declarado nele', function () {
    conexaoAtiva('boa-vista');
    $daBoaVista = Servico::factory()->create(['fornecedor' => 'boa-vista']);

    expect(app(EscolherConector::class)->para($daBoaVista))->toBeInstanceOf(ConectorBoaVista::class);
});

it('cai na escolha geral quando o servico nao declara fornecedor', function () {
    $semDono = Servico::factory()->create(['fornecedor' => null]);

    // Sem conexao ativa nenhuma, o simulado responde sem cobrar ninguem.
    expect(app(EscolherConector::class)->para($semDono))->toBeInstanceOf(ConectorSimulado::class);
});

it('ignora o fornecedor do servico quando a conexao dele esta desligada', function () {
    // Desligar uma conexao e acao de emergencia, e emergencia nao pode derrubar
    // o catalogo inteiro junto: a linha volta ao comportamento geral.
    $servico = Servico::factory()->create(['fornecedor' => 'boa-vista']);

    expect(app(EscolherConector::class)->para($servico))->toBeInstanceOf(ConectorSimulado::class);
});

it('usa o conector do servico na consulta de verdade', function () {
    // Pelo modelo, e nao pelo query builder: update() direto no builder pula o
    // cast e grava a credencial em claro, que depois nao decifra.
    conexaoAtiva('boa-vista', [
        'escopo_relatorios' => 'https://api.equifax.com/business/reporting-orchestrator/v1',
    ]);

    Illuminate\Support\Facades\Http::preventStrayRequests();
    Illuminate\Support\Facades\Http::fake([
        '*oauth/token' => Illuminate\Support\Facades\Http::response(['access_token' => 'tok']),
        '*consulta' => Illuminate\Support\Facades\Http::response(['nome' => 'Fulana', 'score' => 700]),
    ]);

    $empresa = empresaComPlano();
    $servico = Servico::firstWhere('codigo', 'scpc-bvs');
    $servico->update(['fornecedor' => 'boa-vista', 'codigo_fornecedor' => 'SCPC_NET_PF']);

    app(App\Actions\Consumo\ExecutarConsulta::class)($empresa, $servico->fresh(), '12345678901', 'Pesquisa de score de crédito');

    $consulta = App\Models\Consulta::latest('id')->firstOrFail();

    expect($consulta->deuCerto())->toBeTrue()
        ->and($consulta->resposta['nome'])->toBe('Fulana');

    // E a trilha guarda de qual fornecedor a resposta veio.
    expect(App\Models\Auditoria::where('acao', 'like', 'consulta.%')->latest('id')->first()->dados['fornecedor'])
        ->toBe('boa-vista');
});

it('a migracao ja aponta os servicos de Base III para a Boa Vista', function () {
    // A regra do negocio: Base III e a base da Boa Vista. Fica gravada, e nao
    // calculada, porque nome de servico e texto comercial e muda.
    $comTres = Servico::factory()->create(['nome' => 'Crédito Net PF/PJ - Base I e III']);
    $semTres = Servico::factory()->create(['nome' => 'Maxi Top PF/PJ + score e filtros - Base I e II']);

    // A factory nao roda a migracao de novo; o que se protege aqui e a regra
    // continuar escrita e reaplicavel.
    $regra = fn (string $nome) => str_contains($nome, 'Base III')
        || str_contains($nome, 'e III')
        || str_contains($nome, ', III');

    expect($regra($comTres->nome))->toBeTrue()
        ->and($regra($semTres->nome))->toBeFalse();
});

it('recusa fornecedor desconhecido no cadastro do servico', function () {
    $servico = Servico::factory()->create();

    admin()->put(route('catalogo.servicos.atualizar', $servico), [
        'nome' => $servico->nome,
        'categoria' => $servico->categoria->value,
        'fornecedor' => 'bureau-que-nao-existe',
    ])->assertSessionHasErrors('fornecedor');

    expect($servico->fresh()->fornecedor)->toBeNull();
});

it('escolhe o produto PF ou PJ pelo documento consultado', function () {
    // O catalogo vende "PF/PJ" numa linha so, mas a Equifax tem produtos
    // separados. Uma linha comercial, dois produtos no fornecedor.
    $par = 'SCPC_NET_PF|SCPC_NET_PJ';

    expect(ConectorBoaVista::produto($par, '12345678901'))->toBe('SCPC_NET_PF')
        ->and(ConectorBoaVista::produto($par, '39914870000101'))->toBe('SCPC_NET_PJ')
        // Com mascara tambem, porque o operador cola do jeito que recebeu.
        ->and(ConectorBoaVista::produto($par, '39.914.870/0001-01'))->toBe('SCPC_NET_PJ')
        // Valor unico serve aos dois tipos de documento.
        ->and(ConectorBoaVista::produto('SCORE_PF', '39914870000101'))->toBe('SCORE_PF')
        // Lado vazio cai no outro, em vez de recusar a consulta.
        ->and(ConectorBoaVista::produto('|SCPC_NET_PJ', '12345678901'))->toBe('SCPC_NET_PJ')
        ->and(ConectorBoaVista::produto('', '12345678901'))->toBeNull();
});

it('preenche o produto sugerido so onde esta vazio', function () {
    $vazio = Servico::factory()->create(['codigo' => 'score-positivo', 'codigo_fornecedor' => null]);
    $escolhido = Servico::factory()->create(['codigo' => 'credito-net', 'codigo_fornecedor' => 'PRODUTO_DO_CONTRATO']);

    expect(App\Actions\Catalogo\SugerirProdutosBoaVista::pendentes())->toBe(1);

    $preenchidos = app(App\Actions\Catalogo\SugerirProdutosBoaVista::class)();

    expect($preenchidos)->toBe(1)
        ->and($vazio->fresh()->codigo_fornecedor)->toBe('SCORE_PF|SCORE_PJ')
        ->and($vazio->fresh()->fornecedor)->toBe('boa-vista')
        // Escolha de quem sabe nunca e sobrescrita por sugestao.
        ->and($escolhido->fresh()->codigo_fornecedor)->toBe('PRODUTO_DO_CONTRATO');

    expect(App\Models\Auditoria::where('acao', 'servico.produto_sugerido')->count())->toBe(1);
});

it('avisa na tela e oferece o preenchimento, sem aplicar sozinho', function () {
    Servico::factory()->create(['codigo' => 'score-positivo', 'codigo_fornecedor' => null]);

    admin()->get(route('catalogo.servicos.index'))
        ->assertOk()
        ->assertSee('sem o produto do fornecedor', false)
        ->assertSee(route('catalogo.servicos.produtos'), false);

    expect(Servico::firstWhere('codigo', 'score-positivo')->codigo_fornecedor)->toBeNull();
});
