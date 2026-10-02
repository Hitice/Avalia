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

/*
 * Os numeros da apuracao, derivados do config e nao escritos a mao.
 *
 * Com valores fixos no teste, trocar o preco da placa quebrava quatro testes de
 * REGRA que nao tinham nada a ver com o preco. O que eles guardam e a regra: a
 * comissao arredonda por venda, venda orfa nao comissiona, o mes nao e o
 * acumulado.
 *
 * @return array{bruto: int, custo: int, liquido: int, comissao: int, lucro: int}
 */
function contaDaPlaca(int $quantas = 1, bool $comComissao = true): array
{
    $valor = (int) config('etiquetas.precos.placa_cents');
    $custo = (int) config('etiquetas.custo_cents');
    $pct = (int) config('etiquetas.comissao_pct');

    // Por venda, e nao sobre o total: e assim que o vendedor confere, placa por
    // placa, e repasse que nao bate com a conta de quem recebe vira discussao.
    // Sobre o valor de venda, nao sobre o liquido: regra da plaquinha.
    $comissaoDeUma = $comComissao ? (int) round($valor * $pct / 100) : 0;

    return [
        'bruto' => $quantas * $valor,
        'custo' => $quantas * $custo,
        'liquido' => $quantas * ($valor - $custo),
        'comissao' => $quantas * $comissaoDeUma,
        'lucro' => $quantas * ($valor - $custo - $comissaoDeUma),
    ];
}
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

it('apura a venda do vendedor comum com a comissao sobre o valor de venda', function () {
    socios();
    $warley = Staff::factory()->create(['papel' => 'vendedor', 'nome' => 'Warley']);

    Etiqueta::factory()->ativa()->count(2)->create(['vendedor_id' => $warley->id]);

    $tela = admin()->get(route('plaquinhas.vendas'))->assertOk();

    $esperado = contaDaPlaca(2);

    expect($tela->viewData('totais'))->toMatchArray($esperado);

    // A divisao usa a propria regra da casa, inclusive o centavo impar.
    expect($tela->viewData('porSocio')->pluck('mes')->all())
        ->toBe(App\Support\RepartePlaquinha::dividir($esperado['lucro'], 2));
});

it('nao tira comissao quando quem vendeu e socio', function () {
    [$pedro] = socios();

    Etiqueta::factory()->ativa()->create(['vendedor_id' => $pedro->id]);

    $tela = admin()->get(route('plaquinhas.vendas'))->assertOk();

    // Sem comissao, o liquido INTEIRO vai para a divisao.
    expect($tela->viewData('totais')['comissao'])->toBe(0)
        ->and($tela->viewData('porSocio')->pluck('mes')->all())
        ->toBe(App\Support\RepartePlaquinha::dividir(contaDaPlaca(1, false)['lucro'], 2));
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
        ->and($tela->viewData('totais')['bruto'])->toBe(contaDaPlaca()['bruto'])
        // E nao gera comissao: nao houve venda de ninguem. O liquido inteiro
        // vai para a divisao.
        ->and($tela->viewData('totais')['comissao'])->toBe(0)
        ->and($tela->viewData('totais')['lucro'])->toBe(contaDaPlaca(1, false)['lucro']);
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
        ->assertSee('Sem conta na equipe', false);
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

/*
|--------------------------------------------------------------------------
| Caixa acumulado e serie diaria
|--------------------------------------------------------------------------
*/

it('separa o caixa do mes do caixa de sempre', function () {
    socios();
    $vendedor = Staff::factory()->create(['papel' => 'vendedor']);

    Etiqueta::factory()->ativa()->create(['vendedor_id' => $vendedor->id]);
    Etiqueta::factory()->ativa()->create([
        'vendedor_id' => $vendedor->id,
        'vendida_em' => now()->subMonths(3),
    ]);

    $tela = admin()->get(route('plaquinhas.vendas'))->assertOk();

    // O recorte do mes responde "como foi este mes"; o acumulado responde
    // "quanto este produto deu ate hoje", e um nao e a soma visivel do outro.
    expect($tela->viewData('totais')['bruto'])->toBe(contaDaPlaca()['bruto'])
        ->and($tela->viewData('total')['bruto'])->toBe(contaDaPlaca(2)['bruto'])
        ->and($tela->viewData('placas'))->toBe(1)
        ->and($tela->viewData('placasTotal'))->toBe(2);
});

it('divide o acumulado entre os socios sem perder centavo', function () {
    socios();
    $vendedor = Staff::factory()->create(['papel' => 'vendedor']);

    Etiqueta::factory()->ativa()->count(3)->create(['vendedor_id' => $vendedor->id]);

    $tela = admin()->get(route('plaquinhas.vendas'))->assertOk();

    expect($tela->viewData('porSocio')->sum('total'))
        ->toBe($tela->viewData('total')['lucro']);
});

it('mostra o mes inteiro na serie diaria, inclusive dia sem venda', function () {
    // Buraco no meio da serie e informacao: dia sem venda tem de aparecer como
    // dia sem venda, e nao ser omitido para a linha ficar bonita.
    socios();

    Etiqueta::factory()->ativa()->create(['vendida_em' => now()->startOfMonth()->addDays(2)]);

    $porDia = admin()->get(route('plaquinhas.vendas'))->viewData('porDia');

    expect($porDia)->toHaveCount(now()->daysInMonth)
        ->and($porDia->sum('placas'))->toBe(1)
        ->and($porDia->firstWhere('dia', 3)['placas'])->toBe(1)
        ->and($porDia->firstWhere('dia', 1)['placas'])->toBe(0);
});

/*
|--------------------------------------------------------------------------
| A regra dos socios, nas palavras do dono
|--------------------------------------------------------------------------
*/

it('poe a venda de um socio no lucro do outro, e vice-versa', function () {
    // "As comissoes entre mim e o Ruan sao divididas 50/50 e precisam refletir
    // as que ele vendeu no meu lucro e vice-versa." Venda de socio nao comissiona
    // o socio: o liquido inteiro vai para a divisao, e metade e do outro.
    [$pedro, $ruan] = socios();

    Etiqueta::factory()->ativa()->count(2)->create(['vendedor_id' => $ruan->id]);

    $tela = admin()->get(route('plaquinhas.vendas'))->assertOk();
    $porSocio = $tela->viewData('porSocio')->keyBy('nome');

    $lucro = contaDaPlaca(2, false)['lucro'];
    [$dePedro, $deRuan] = App\Support\RepartePlaquinha::dividir($lucro, 2);

    expect($tela->viewData('totais')['comissao'])->toBe(0)
        ->and($porSocio['Pedro']['mes'])->toBe($dePedro)
        ->and($porSocio['Ruan']['mes'])->toBe($deRuan)
        ->and($dePedro + $deRuan)->toBe($lucro);
});

it('desconta a comissao do vendedor e divide so o que sobra entre os socios', function () {
    // "Mas as vendas dos vendedores, o que sobra vai para a divisao de socios."
    socios();
    $warley = Staff::factory()->create(['papel' => 'vendedor', 'nome' => 'Warley']);

    Etiqueta::factory()->ativa()->count(3)->create(['vendedor_id' => $warley->id]);

    $tela = admin()->get(route('plaquinhas.vendas'))->assertOk();
    $conta = contaDaPlaca(3);

    expect($tela->viewData('totais')['comissao'])->toBe($conta['comissao'])
        ->and($tela->viewData('porSocio')->sum('mes'))->toBe($conta['lucro'])
        // E o lucro dividido e o liquido MENOS a comissao, nunca o liquido inteiro.
        ->and($conta['lucro'])->toBe($conta['liquido'] - $conta['comissao']);
});

it('mostra de cada socio o que sai na sexta e o que fica na empresa', function () {
    // Da parte de cada um, metade e pro-labore e metade fica no caixa. Decidido
    // em 02/10/2026. O numero que ele usa para pagar e o pro-labore.
    socios();
    $warley = Staff::factory()->create(['papel' => 'vendedor']);
    Etiqueta::factory()->ativa()->count(2)->create(['vendedor_id' => $warley->id]);

    $tela = admin()->get(route('plaquinhas.vendas'))->assertOk();
    $pct = (int) config('etiquetas.retencao_pct');
    $partes = App\Support\RepartePlaquinha::dividir(contaDaPlaca(2)['lucro'], 2);

    foreach ($tela->viewData('porSocio') as $i => $socio) {
        $esperado = App\Support\RepartePlaquinha::retencao($partes[$i], $pct);

        expect($socio['mes'])->toBe($partes[$i])
            ->and($socio['retido'])->toBe($esperado['retido'])
            ->and($socio['prolabore'])->toBe($esperado['prolabore'])
            // Fecha: nada some entre a parte e as duas metades.
            ->and($socio['retido'] + $socio['prolabore'])->toBe($socio['mes']);
    }
});

it('mostra a hora de cada venda do mes', function () {
    Illuminate\Support\Carbon::setTestNow('2026-10-02 14:37:00');
    socios();
    $warley = Staff::factory()->create(['papel' => 'vendedor']);
    Etiqueta::factory()->ativa()->create(['vendedor_id' => $warley->id]);

    $html = admin()->get(route('plaquinhas.vendas'))->assertOk()->getContent();

    expect($html)->toContain('02/10 14:37');
    Illuminate\Support\Carbon::setTestNow();
});
