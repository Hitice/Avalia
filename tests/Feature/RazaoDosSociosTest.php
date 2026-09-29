<?php

use App\Enums\NaturezaLancamento;
use App\Exceptions\Recusa;
use App\Models\Auditoria;
use App\Models\ContaFinanceira;
use App\Models\LancamentoFinanceiro;
use App\Models\PartidaFinanceira;
use App\Models\Socio;
use App\Models\Staff;
use App\Support\Dinheiro;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(Database\Seeders\ContasFinanceirasSeeder::class);
});

function lancar(NaturezaLancamento $natureza, array $dados = []): LancamentoFinanceiro
{
    return app(App\Actions\Socios\RegistrarLancamento::class)($natureza, array_merge([
        'descricao' => 'teste',
        'competencia' => '2026-09',
        'ocorrido_em' => '2026-09-15',
        'valor_cents' => 10_000,
    ], $dados));
}

function socio(string $nome = 'Pedro'): Socio
{
    return Socio::create(['nome' => $nome, 'participacao_bps' => 5_000, 'ativo' => true]);
}

function saldo(string $codigo): int
{
    return ContaFinanceira::firstWhere('codigo', $codigo)?->saldoCents() ?? 0;
}

/*
|--------------------------------------------------------------------------
| A invariante
|--------------------------------------------------------------------------
*/

it('grava toda natureza com partidas que somam zero', function () {
    // A soma e a unica conferencia que pega erro de traducao sem reconferir
    // extrato. Vale para as nove, e nao so para as que alguem lembrou de testar.
    $socio = socio();
    $destino = ContaFinanceira::create(['codigo' => 'banco', 'nome' => 'Banco', 'grupo' => 'ativo']);

    foreach (NaturezaLancamento::cases() as $natureza) {
        $lancamento = lancar($natureza, ['socio_id' => $socio->id, 'destino_id' => $destino->id]);

        expect($lancamento->partidas->sum('valor_cents'))->toBe(0)
            ->and($lancamento->partidas)->toHaveCount(2);
    }
});

it('recusa valor zero ou negativo', function () {
    expect(fn () => lancar(NaturezaLancamento::Despesa, ['valor_cents' => 0]))->toThrow(Recusa::class)
        ->and(fn () => lancar(NaturezaLancamento::Despesa, ['valor_cents' => -100]))->toThrow(Recusa::class);
});

it('recusa lancamento de socio sem dizer qual socio', function () {
    expect(fn () => lancar(NaturezaLancamento::Aporte, ['socio_id' => null]))->toThrow(Recusa::class);
});

it('recusa transferencia sem destino, e diz o que falta', function () {
    // Sem destino as duas pernas cairiam na mesma conta e o lancamento sumiria
    // numa soma zero que nao move nada.
    try {
        lancar(NaturezaLancamento::Transferencia);
        $this->fail('deveria ter recusado');
    } catch (Recusa $e) {
        expect($e->getMessage())->toContain('destino');
    }
});

it('recusa transferencia para a propria conta de origem', function () {
    $caixa = ContaFinanceira::firstWhere('codigo', ContaFinanceira::CAIXA);

    expect(fn () => lancar(NaturezaLancamento::Transferencia, ['destino_id' => $caixa->id]))
        ->toThrow(Recusa::class);
});

/*
|--------------------------------------------------------------------------
| Os exemplos de aceite
|--------------------------------------------------------------------------
|
| Cada um abaixo e uma das confusoes que custam dinheiro numa sociedade.
|
*/

it('socio paga hospedagem: vira despesa e valor a reembolsar, nao duas despesas', function () {
    $pedro = socio();

    lancar(NaturezaLancamento::DespesaDoSocio, ['socio_id' => $pedro->id, 'valor_cents' => 10_000]);

    expect(saldo(ContaFinanceira::DESPESA))->toBe(10_000)
        ->and(saldo('emprestimo:'.$pedro->id))->toBe(10_000)
        // O caixa da empresa nao se moveu: quem pagou foi ele.
        ->and(saldo(ContaFinanceira::CAIXA))->toBe(0);

    // O reembolso liquida a obrigacao e reduz o caixa. A despesa continua uma.
    lancar(NaturezaLancamento::Reembolso, ['socio_id' => $pedro->id, 'valor_cents' => 10_000]);

    expect(saldo(ContaFinanceira::DESPESA))->toBe(10_000)
        ->and(saldo('emprestimo:'.$pedro->id))->toBe(0)
        ->and(saldo(ContaFinanceira::CAIXA))->toBe(-10_000);
});

it('aporte aumenta caixa e patrimonio, e nao gera receita', function () {
    $pedro = socio();

    lancar(NaturezaLancamento::Aporte, ['socio_id' => $pedro->id, 'valor_cents' => 100_000]);

    expect(saldo(ContaFinanceira::CAIXA))->toBe(100_000)
        ->and(saldo('aporte:'.$pedro->id))->toBe(100_000)
        // A linha que mais erra: aporte nao e venda.
        ->and(saldo(ContaFinanceira::RECEITA))->toBe(0);
});

it('transferencia entre contas da empresa nao muda o resultado', function () {
    $outra = ContaFinanceira::create(['codigo' => 'banco', 'nome' => 'Banco', 'grupo' => 'ativo']);

    lancar(NaturezaLancamento::Transferencia, ['valor_cents' => 30_000, 'destino_id' => $outra->id]);

    expect(saldo(ContaFinanceira::CAIXA))->toBe(-30_000)
        ->and($outra->fresh()->saldoCents())->toBe(30_000)
        ->and(saldo(ContaFinanceira::RECEITA))->toBe(0)
        ->and(saldo(ContaFinanceira::DESPESA))->toBe(0);
});

it('nao reconhece a mesma origem duas vezes', function () {
    // Reimportar nao pode transformar uma fatura em duas receitas.
    lancar(NaturezaLancamento::Receita, ['origem_tipo' => 'fatura', 'origem_id' => 7]);

    expect(fn () => lancar(NaturezaLancamento::Receita, ['origem_tipo' => 'fatura', 'origem_id' => 7]))
        ->toThrow(Illuminate\Database\QueryException::class);

    expect(LancamentoFinanceiro::count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| O que so receita e despesa mexem
|--------------------------------------------------------------------------
*/

it('sabe quais naturezas mexem no resultado', function () {
    // A tela de resultado depende disso para nao somar aporte como se fosse
    // venda nem reembolso como se fosse gasto novo.
    $mexem = collect(NaturezaLancamento::cases())
        ->filter(fn ($n) => $n->afetaResultado())
        ->map(fn ($n) => $n->value)
        ->values()
        ->all();

    expect($mexem)->toBe(['despesa', 'despesa_do_socio', 'receita']);
});

it('nao deixa lancamento mudar depois de gravado', function () {
    // Corrigir e estornar. Linha que muda deixa de explicar o saldo conferido.
    lancar(NaturezaLancamento::Despesa);

    $partida = PartidaFinanceira::first();

    expect($partida->timestamps)->toBeFalse()
        ->and(LancamentoFinanceiro::UPDATED_AT)->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Estorno: corrigir e lancar o contrario
|--------------------------------------------------------------------------
*/

function estornar(LancamentoFinanceiro $lancamento, string $motivo = 'valor errado'): LancamentoFinanceiro
{
    return app(App\Actions\Socios\EstornarLancamento::class)($lancamento, $motivo);
}

it('devolve o saldo ao ponto de partida', function () {
    $pedro = socio();

    $lancamento = lancar(NaturezaLancamento::Aporte, ['socio_id' => $pedro->id, 'valor_cents' => 50_000]);
    expect(saldo(ContaFinanceira::CAIXA))->toBe(50_000);

    $estorno = estornar($lancamento);

    expect(saldo(ContaFinanceira::CAIXA))->toBe(0)
        ->and(saldo('aporte:'.$pedro->id))->toBe(0)
        // As duas linhas continuam no extrato: o conserto e um evento, e nao
        // um apagamento.
        ->and(LancamentoFinanceiro::count())->toBe(2)
        ->and($estorno->partidas->sum('valor_cents'))->toBe(0);
});

it('lanca o estorno na competencia de hoje, e nao na do original', function () {
    // Mes fechado continua com o numero que teve; a correcao pertence ao mes em
    // que foi decidida.
    $lancamento = lancar(NaturezaLancamento::Despesa, ['competencia' => '2026-01']);

    expect(estornar($lancamento)->competencia)->toBe(now()->format('Y-m'));
});

it('exige motivo, porque ele fica no extrato', function () {
    expect(fn () => estornar(lancar(NaturezaLancamento::Despesa), '  '))->toThrow(Recusa::class);
});

it('nao estorna duas vezes nem estorna um estorno', function () {
    $lancamento = lancar(NaturezaLancamento::Despesa);
    $estorno = estornar($lancamento);

    expect(fn () => estornar($lancamento))->toThrow(Recusa::class)
        ->and(fn () => estornar($estorno))->toThrow(Recusa::class);
});

/*
|--------------------------------------------------------------------------
| Quem entra no caixa da sociedade
|--------------------------------------------------------------------------
*/

it('nao abre para administrador sem a permissao', function () {
    // Nasce negada, inclusive para quem ja e admin: permissao que vem por
    // heranca e permissao que ninguem decidiu conceder.
    $admin = Staff::factory()->admin()->create(['super' => false, 'pode_socios' => false]);

    test()->actingAs($admin, 'staff')->withSession(['versao_staff' => 1])
        ->get(route('socios.index'))
        ->assertForbidden();
});

it('nao abre para vendedor, mesmo com a marca ligada por engano', function () {
    $vendedor = Staff::factory()->create(['papel' => 'vendedor', 'pode_socios' => true]);

    test()->actingAs($vendedor, 'staff')->withSession(['versao_staff' => 1])
        ->get(route('socios.index'))
        ->assertForbidden();
});

it('abre para administrador com a permissao', function () {
    $admin = Staff::factory()->admin()->create(['super' => false, 'pode_socios' => true]);

    test()->actingAs($admin, 'staff')->withSession(['versao_staff' => 1])
        ->get(route('socios.index'))
        ->assertOk();
});

it('some do menu de quem nao pode, em vez de levar a 403', function () {
    // Menu que leva a 403 ensina o operador a ignorar o menu.
    $semPermissao = Staff::factory()->admin()->create(['super' => false, 'pode_socios' => false]);

    test()->actingAs($semPermissao, 'staff')->withSession(['versao_staff' => 1]);

    $itens = collect(App\Helpers\MenuHelper::getMenuGroups()[0]['items'])->pluck('path');

    expect($itens)->not->toContain('/socios');
});

it('registra e estorna pela tela', function () {
    $admin = Staff::factory()->admin()->create(['super' => true]);
    $pedro = socio();

    $tela = test()->actingAs($admin, 'staff')->withSession(['versao_staff' => 1]);

    $tela->post(route('socios.registrar'), [
        'natureza' => 'despesa_do_socio',
        'descricao' => 'Hospedagem',
        'valor' => '100,00',
        'ocorrido_em' => now()->toDateString(),
        'socio_id' => $pedro->id,
    ])->assertRedirect();

    expect(saldo(ContaFinanceira::DESPESA))->toBe(10_000)
        ->and(saldo('emprestimo:'.$pedro->id))->toBe(10_000);

    $tela->post(route('socios.estornar', LancamentoFinanceiro::first()), ['motivo' => 'lancado errado'])
        ->assertRedirect();

    expect(saldo(ContaFinanceira::DESPESA))->toBe(0)
        ->and(saldo('emprestimo:'.$pedro->id))->toBe(0);
});

/*
|--------------------------------------------------------------------------
| A porta de entrada
|--------------------------------------------------------------------------
*/

it('tem as contas da empresa desde a migration, e nao so no seeder', function () {
    // O modulo subiu em producao respondendo "A conta caixa nao esta
    // cadastrada" a qualquer lancamento: a estrutura existia e o dado que ela
    // exige, nao. Dado sem o qual nada funciona vem por migration.
    App\Models\ContaFinanceira::query()->delete();

    (require database_path('migrations/2026_09_29_000005_semeia_as_contas_da_empresa.php'))->up();

    expect(App\Models\ContaFinanceira::pluck('codigo')->sort()->values()->all())
        ->toBe(['caixa', 'despesa', 'receita']);
});

it('nao duplica conta quando a migration roda de novo', function () {
    (require database_path('migrations/2026_09_29_000005_semeia_as_contas_da_empresa.php'))->up();

    expect(App\Models\ContaFinanceira::where('codigo', 'caixa')->count())->toBe(1);
});

it('cadastra socio pela tela', function () {
    // Seis das nove naturezas exigem socio, e nao havia como criar o primeiro.
    $admin = Staff::factory()->admin()->create(['super' => true]);

    test()->actingAs($admin, 'staff')->withSession(['versao_staff' => 1])
        ->post(route('socios.criar'), ['nome' => 'Ruan', 'participacao' => '50'])
        ->assertRedirect();

    $socio = Socio::sole();

    expect($socio->nome)->toBe('Ruan')
        // Em pontos-base, como o resto do dinheiro desta casa.
        ->and($socio->participacao_bps)->toBe(5_000);
});

it('avisa quando as participacoes nao fecham cem por cento', function () {
    $admin = Staff::factory()->admin()->create(['super' => true]);
    Socio::create(['nome' => 'Pedro', 'participacao_bps' => 4_000, 'ativo' => true]);

    test()->actingAs($admin, 'staff')->withSession(['versao_staff' => 1])
        ->get(route('socios.index'))
        ->assertOk()
        ->assertSee('em vez de 100%', false);
});

/*
|--------------------------------------------------------------------------
| A tela sem socio cadastrado
|--------------------------------------------------------------------------
*/

it('diz o que fazer quando nao ha socio para escolher', function () {
    // Seis das nove naturezas exigem socio. Sem nenhum cadastrado, o select
    // ficava so com "Escolha" e nada para escolher: campo quebrado que nao diz
    // que esta.
    $admin = Staff::factory()->admin()->create(['super' => true]);

    test()->actingAs($admin, 'staff')->withSession(['versao_staff' => 1])
        ->get(route('socios.index'))
        ->assertOk()
        ->assertSee('Cadastre um sócio antes de lançar', false)
        ->assertSee('Nenhum sócio cadastrado', false);
});

it('some com o aviso assim que existe socio', function () {
    $admin = Staff::factory()->admin()->create(['super' => true]);
    socio('Pedro');

    test()->actingAs($admin, 'staff')->withSession(['versao_staff' => 1])
        ->get(route('socios.index'))
        ->assertOk()
        ->assertDontSee('Cadastre um sócio antes de lançar', false)
        ->assertSee('Pedro');
});

it('poe o cadastro de socio na frente quando nao ha nenhum', function () {
    // Tabela vazia acima do formulario escondia o cadastro: quem abria a tela
    // pela primeira vez via tres colunas sem linha e nao achava por onde
    // comecar.
    $admin = Staff::factory()->admin()->create(['super' => true]);

    test()->actingAs($admin, 'staff')->withSession(['versao_staff' => 1])
        ->get(route('socios.index'))
        ->assertOk()
        ->assertSee('Cadastrar sócio', false)
        ->assertSee('O caixa precisa saber de quem é cada parte', false)
        // A tabela de saldos nao aparece antes de existir saldo.
        ->assertDontSee('A devolver', false);
});

/*
|--------------------------------------------------------------------------
| A confirmacao diz o efeito, e nao que gravou
|--------------------------------------------------------------------------
*/

it('explica que despesa paga pelo socio nao mexe no caixa', function () {
    // A primeira duvida real que chegou: "despesa paga pelo socio nao saiu do
    // caixa da empresa". Estava certo, e a tela e que nao contava.
    $admin = Staff::factory()->admin()->create(['super' => true]);
    $pedro = socio('Pedro');

    test()->actingAs($admin, 'staff')->withSession(['versao_staff' => 1])
        ->post(route('socios.registrar'), [
            'natureza' => 'despesa_do_socio',
            'descricao' => 'Hospedagem',
            'valor' => '100,00',
            'ocorrido_em' => now()->toDateString(),
            'socio_id' => $pedro->id,
        ])
        ->assertSessionHas('ok', fn (string $aviso) => str_contains($aviso, 'caixa não se move')
            && str_contains($aviso, 'Pedro')
            // `Dinheiro::brl` usa espaco nao-quebravel entre o simbolo e o
            // numero, para a quebra de linha nao separar "R$" do valor.
            && str_contains($aviso, Dinheiro::brl(10_000)));
});

it('avisa que o reembolso nao gera despesa nova', function () {
    $admin = Staff::factory()->admin()->create(['super' => true]);
    $pedro = socio('Pedro');

    test()->actingAs($admin, 'staff')->withSession(['versao_staff' => 1])
        ->post(route('socios.registrar'), [
            'natureza' => 'reembolso',
            'descricao' => 'Reembolso',
            'valor' => '100,00',
            'ocorrido_em' => now()->toDateString(),
            'socio_id' => $pedro->id,
        ])
        ->assertSessionHas('ok', fn (string $aviso) => str_contains($aviso, 'Não gera despesa nova'));
});

it('tem efeito escrito para toda natureza', function () {
    // Natureza nova sem efeito viraria confirmacao com {valor} cru na tela.
    foreach (NaturezaLancamento::cases() as $natureza) {
        expect($natureza->efeito())->not->toBe('');
    }
});

/*
|--------------------------------------------------------------------------
| Apagar o que se digitou errado
|--------------------------------------------------------------------------
|
| O estorno e o caminho normal e continua sendo. Mas ele protege lancamento que
| teve tempo de ser visto: obrigar estorno no que se acabou de digitar errado
| deixa duas linhas no extrato por causa de um clique no seletor errado.
|
*/

function apagar(LancamentoFinanceiro $lancamento): void
{
    app(App\Actions\Socios\ExcluirLancamento::class)($lancamento);
}

it('apaga o lancamento da competencia corrente e tira o saldo junto', function () {
    lancar(NaturezaLancamento::Despesa, ['valor_cents' => 25_000]);
    expect(saldo(ContaFinanceira::DESPESA))->toBe(25_000);

    apagar(LancamentoFinanceiro::sole());

    expect(LancamentoFinanceiro::count())->toBe(0)
        ->and(PartidaFinanceira::count())->toBe(0)
        ->and(saldo(ContaFinanceira::DESPESA))->toBe(0);
});

it('guarda na trilha o que o razao perde', function () {
    // "Sumiu um lancamento" precisa continuar tendo resposta.
    lancar(NaturezaLancamento::Despesa, ['descricao' => 'hospedagem errada', 'valor_cents' => 25_000]);

    apagar(LancamentoFinanceiro::sole());

    $trilha = Auditoria::where('acao', 'socios.lancamento.excluido')->latest('id')->first();

    expect($trilha)->not->toBeNull()
        ->and($trilha->dados['descricao'])->toBe('hospedagem errada')
        ->and($trilha->dados['valor_cents'])->toBe(25_000);
});

it('nao apaga lancamento de competencia anterior', function () {
    // Mes anterior pode ja ter sido conferido.
    $antigo = lancar(NaturezaLancamento::Despesa, ['competencia' => '2026-01']);

    expect(fn () => apagar($antigo))->toThrow(Recusa::class)
        ->and(LancamentoFinanceiro::count())->toBe(1);
});

it('nao apaga receita que veio de fatura', function () {
    // A origem e unica: apagar devolveria a fatura ao estado de nao
    // reconhecida, em silencio.
    $daFatura = lancar(NaturezaLancamento::Receita, ['origem_tipo' => 'fatura', 'origem_id' => 9]);

    expect(fn () => apagar($daFatura))->toThrow(Recusa::class);
});

it('nao apaga estorno nem lancamento ja estornado', function () {
    // O par conta uma historia; apagar metade dela deixa a outra sem sentido.
    $original = lancar(NaturezaLancamento::Despesa);
    $estorno = estornar($original);

    expect(fn () => apagar($estorno))->toThrow(Recusa::class)
        ->and(fn () => apagar($original->refresh()))->toThrow(Recusa::class);
});

it('so oferece o botao de apagar quando ele funciona', function () {
    // Botao que sempre recusa ensina o operador a nao clicar em botao nenhum.
    $atual = lancar(NaturezaLancamento::Despesa);
    $antigo = lancar(NaturezaLancamento::Despesa, ['competencia' => '2026-01']);
    $daFatura = lancar(NaturezaLancamento::Receita, ['origem_tipo' => 'fatura', 'origem_id' => 3]);

    expect($atual->podeSerApagado())->toBeTrue()
        ->and($antigo->podeSerApagado())->toBeFalse()
        ->and($daFatura->podeSerApagado())->toBeFalse();
});

it('apaga pela tela', function () {
    $admin = Staff::factory()->admin()->create(['super' => true]);
    lancar(NaturezaLancamento::Despesa);

    test()->actingAs($admin, 'staff')->withSession(['versao_staff' => 1])
        ->delete(route('socios.excluir', LancamentoFinanceiro::sole()))
        ->assertRedirect();

    expect(LancamentoFinanceiro::count())->toBe(0);
});
