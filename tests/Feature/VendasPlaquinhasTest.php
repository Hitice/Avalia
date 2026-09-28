<?php

use App\Models\DestinoEtiqueta;
use App\Models\Etiqueta;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Cria os dois socios do config, na ordem em que eles dividem.
 *
 * @return array{0: Staff, 1: Staff}
 */
function socios(): array
{
    $emails = config('etiquetas.socios');

    return [
        Staff::factory()->admin()->create(['email' => $emails[0], 'nome' => 'Pedro']),
        Staff::factory()->admin()->create(['email' => $emails[1], 'nome' => 'Ruan']),
    ];
}

/*
|--------------------------------------------------------------------------
| Quem entra
|--------------------------------------------------------------------------
*/

it('nao deixa o vendedor ver a margem da casa', function () {
    // A tela mostra custo e divisao entre socios. O vendedor ve a comissao dele
    // na propria tela; o resto nao e assunto de quem vende.
    $vendedor = Staff::factory()->create(['papel' => 'vendedor']);

    test()->actingAs($vendedor, 'staff')->withSession(['versao_staff' => 1])
        ->get(route('plaquinhas.vendas'))
        ->assertForbidden();
});

it('nao abre para quem nao entrou', function () {
    $this->get(route('plaquinhas.vendas'))->assertRedirect();
});

/*
|--------------------------------------------------------------------------
| A apuracao
|--------------------------------------------------------------------------
*/

it('apura a venda do vendedor comum com a comissao sobre o liquido', function () {
    socios();
    $warley = Staff::factory()->create(['papel' => 'vendedor', 'nome' => 'Warley']);

    Etiqueta::factory()->ativa()->count(2)->create(['vendedor_id' => $warley->id]);

    $tela = admin()->get(route('plaquinhas.vendas'))->assertOk();

    // Duas placas de 89,90: bruto 179,80, custo 10,00, liquido 169,80.
    //
    // A comissao arredonda POR VENDA, e nao sobre o total do mes: 25% de 84,90
    // sao 21,225, que viram 21,23 em cada placa, e duas dao 42,46. Sobre o
    // total fechado daria 42,45. Um centavo de diferenca, e a versao por venda
    // e a certa: e assim que o vendedor confere, placa por placa, e um repasse
    // que nao bate com a conta de quem recebe vira discussao todo mes.
    $totais = $tela->viewData('totais');

    expect($totais['bruto'])->toBe(17_980)
        ->and($totais['custo'])->toBe(1_000)
        ->and($totais['liquido'])->toBe(16_980)
        ->and($totais['comissao'])->toBe(4_246)
        ->and($totais['sobra'])->toBe(12_734);

    expect($tela->viewData('porSocio')->pluck('cents')->all())->toBe([6_367, 6_367]);
});

it('nao tira comissao quando quem vendeu e socio', function () {
    [$pedro] = socios();

    Etiqueta::factory()->ativa()->create(['vendedor_id' => $pedro->id]);

    $tela = admin()->get(route('plaquinhas.vendas'))->assertOk();

    expect($tela->viewData('totais')['comissao'])->toBe(0)
        // O liquido inteiro vai para a divisao: 84,90 em duas partes iguais.
        ->and($tela->viewData('porSocio')->pluck('cents')->all())->toBe([4_245, 4_245]);
});

it('conta a venda pelo mes em que ela aconteceu, e nao pela geracao da placa', function () {
    socios();

    // A placa foi cortada em janeiro e vendida so em marco. Contar pela criacao
    // creditaria a venda ao mes em que a grafica imprimiu.
    Etiqueta::factory()->ativa()->create([
        'created_at' => now()->subMonths(2),
        'vendida_em' => now(),
    ]);

    expect(admin()->get(route('plaquinhas.vendas'))->viewData('placas'))->toBe(1);

    $mesPassado = admin()->get(route('plaquinhas.vendas', ['mes' => now()->subMonth()->format('Y-m')]));

    expect($mesPassado->viewData('placas'))->toBe(0);
});

it('mostra a venda orfa em vez de dividi-la entre os outros', function () {
    socios();

    // Cliente e produtor mexem no proprio codigo, e ai nao ha vendedor da casa.
    // Inventar dono para essa venda seria pior que mostrar que ela existe.
    Etiqueta::factory()->ativa()->create(['vendedor_id' => null]);

    $tela = admin()->get(route('plaquinhas.vendas'))->assertOk();

    expect($tela->viewData('semVendedor'))->toBe(1)
        ->and($tela->viewData('porVendedor'))->toHaveCount(0)
        // O dinheiro continua entrando na apuracao: o que falta e o nome.
        ->and($tela->viewData('totais')['bruto'])->toBe(8_990)
        // E nao gera comissao: nao houve venda de ninguem. O liquido inteiro
        // vai para a divisao.
        ->and($tela->viewData('totais')['comissao'])->toBe(0)
        ->and($tela->viewData('totais')['sobra'])->toBe(8_490);
});

it('nunca mostra mais comissao no total do que a soma dos vendedores', function () {
    // As duas cifras aparecem na MESMA tela: o cartao "Comissões" e a coluna da
    // tabela por vendedor. Divergencia entre elas foi um bug real, causado por
    // venda orfa que gerava comissao sem destinatario.
    socios();
    $warley = Staff::factory()->create(['papel' => 'vendedor']);

    Etiqueta::factory()->ativa()->count(7)->create(['vendedor_id' => $warley->id]);
    Etiqueta::factory()->ativa()->create(['vendedor_id' => null]);

    $tela = admin()->get(route('plaquinhas.vendas'))->assertOk();

    expect($tela->viewData('porVendedor')->sum('comissao'))
        ->toBe($tela->viewData('totais')['comissao']);
});

/*
|--------------------------------------------------------------------------
| O que protege o repasse
|--------------------------------------------------------------------------
*/

it('avisa quando um socio do config nao tem conta', function () {
    // Sem aviso, a divisao continuaria fechando entre menos gente, e o erro
    // apareceria no bolso de alguem.
    Staff::factory()->admin()->create(['email' => config('etiquetas.socios')[0]]);

    admin()->get(route('plaquinhas.vendas'))
        ->assertOk()
        ->assertSee('A divisão está incompleta.', false);
});

it('usa o valor gravado na venda, e nao a tabela de hoje', function () {
    socios();

    // Placa vendida por 79,90 antes do reajuste. Reajuste de hoje nao pode
    // reescrever o que foi cobrado ontem.
    Etiqueta::factory()->ativa()->create(['valor_cents' => 7_990, 'custo_cents' => 500]);

    config(['etiquetas.precos.placa_cents' => 12_000]);

    expect(admin()->get(route('plaquinhas.vendas'))->viewData('totais')['bruto'])->toBe(7_990);
});

it('recupera quem vendeu as placas anteriores a coluna', function () {
    // A migration de recuperacao le o PRIMEIRO apontamento de cada placa: e o
    // passo que tira a placa do estoque, e `destinos_etiqueta` guarda o staff
    // de cada um desde sempre. Sem isto, todo o historico ficaria orfao.
    $warley = Staff::factory()->create(['papel' => 'vendedor', 'nome' => 'Warley']);
    $outro = Staff::factory()->create(['papel' => 'vendedor']);

    $etiqueta = Etiqueta::factory()->ativa()->create(['vendedor_id' => null, 'custo_cents' => null]);

    // Quem vendeu apontou primeiro; a troca de destino veio depois, por outra
    // pessoa. A venda e de quem apontou primeiro.
    DestinoEtiqueta::create([
        'etiqueta_id' => $etiqueta->id, 'destino' => 'https://a.com.br',
        'vigorou_de' => now()->subYear(), 'vigorou_ate' => now()->subMonth(), 'staff_id' => $warley->id,
    ]);
    DestinoEtiqueta::create([
        'etiqueta_id' => $etiqueta->id, 'destino' => 'https://b.com.br',
        'vigorou_de' => now()->subMonth(), 'staff_id' => $outro->id,
    ]);

    (require database_path('migrations/2026_09_28_000002_venda_de_etiqueta_sabe_quem_vendeu.php'))->up();

    $etiqueta->refresh();

    expect($etiqueta->vendedor_id)->toBe($warley->id)
        ->and($etiqueta->custo_cents)->toBe((int) config('etiquetas.custo_cents'));
});

it('cai no mes corrente quando a url traz mes invalido', function () {
    socios();

    // Este endereco vai parar em favorito e em link colado; mes quebrado nao
    // pode virar tela de erro.
    admin()->get(route('plaquinhas.vendas', ['mes' => 'banana']))->assertOk();
});
