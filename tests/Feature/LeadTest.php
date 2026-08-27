<?php

use App\Models\Lead;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Quem entra na base
|--------------------------------------------------------------------------
*/

it('fecha a base de leads para o vendedor', function () {
    [$vendedor] = carteira();

    comoVendedor($vendedor)->get(route('leads.index'))->assertForbidden();
    comoVendedor($vendedor)->post(route('leads.lote'), [
        'acao' => 'compartilhar', 'escopo' => 'marcados', 'vendedor' => $vendedor->id, 'leads' => [1],
    ])->assertForbidden();
});

/**
 * Cada papel enxerga a sua porta: a administracao entra na base, o vendedor
 * entra na lista dele. Menu que leva a 403 ensina o operador a ignorar o menu.
 */
it('poe Leads no menu dos dois lados, cada um na sua porta', function () {
    admin()->get(route('painel'))
        ->assertSee('href="/leads"', false)
        ->assertDontSee('href="/carteira/leads"', false);

    [$vendedor] = carteira();

    comoVendedor($vendedor)->get(route('painel'))
        ->assertSee('href="/carteira/leads"', false)
        ->assertDontSee('href="/leads"', false);
});

it('mostra a base com o contato de cada lead', function () {
    Lead::factory()->create(['nome' => 'FABRICA DE TELHAS LTDA', 'telefone' => '(31) 3546-6800']);

    admin()->get(route('leads.index'))
        ->assertOk()
        ->assertSee('FABRICA DE TELHAS LTDA')
        ->assertSee('(31) 3546-6800');
});

/*
|--------------------------------------------------------------------------
| O recorte que vai ser distribuido
|--------------------------------------------------------------------------
*/

it('recorta a base por cidade e por ter telefone', function () {
    $alvo = Lead::factory()->create(['nome' => 'ALVO DE UBERLANDIA', 'cidade' => 'Uberlandia']);
    Lead::factory()->create(['nome' => 'OUTRA CIDADE LTDA', 'cidade' => 'Goiania']);
    Lead::factory()->semContato()->create(['nome' => 'SEM TELEFONE LTDA', 'cidade' => 'Uberlandia']);

    admin()->get(route('leads.index', ['cidade' => 'Uberlandia', 'contato' => 'telefone']))
        ->assertOk()
        ->assertSee($alvo->nome)
        ->assertDontSee('OUTRA CIDADE LTDA')
        ->assertDontSee('SEM TELEFONE LTDA');
});

it('acha o lead pelo CNPJ colado com mascara', function () {
    $lead = Lead::factory()->create(['nome' => 'PROCURADO LTDA', 'cnpj' => '08876860000103']);
    Lead::factory()->create(['nome' => 'IGNORADO LTDA']);

    admin()->get(route('leads.index', ['busca' => '08.876.860/0001-03']))
        ->assertOk()
        ->assertSee($lead->nome)
        ->assertDontSee('IGNORADO LTDA');
});

it('separa quem ainda nao esta com vendedor nenhum', function () {
    $vendedor = Staff::factory()->create(['papel' => 'vendedor']);
    $comDono = Lead::factory()->create(['nome' => 'JA DISTRIBUIDO LTDA']);
    $parado = Lead::factory()->create(['nome' => 'AINDA PARADO LTDA']);

    app(App\Actions\Prospeccao\CompartilharLeads::class)([$comDono->id], $vendedor);

    admin()->get(route('leads.index', ['vendedor' => 'sem']))
        ->assertOk()
        ->assertSee($parado->nome)
        ->assertDontSee('JA DISTRIBUIDO LTDA');
});

/*
|--------------------------------------------------------------------------
| Compartilhar com um vendedor
|--------------------------------------------------------------------------
*/

it('compartilha os leads marcados com o vendedor escolhido', function () {
    $vendedor = Staff::factory()->create(['papel' => 'vendedor', 'nome' => 'Ana Vendedora']);
    $leads = Lead::factory()->count(3)->create();

    admin()->from(route('leads.index'))
        ->post(route('leads.lote'), [
            'acao' => 'compartilhar',
            'escopo' => 'marcados',
            'vendedor' => $vendedor->id,
            'leads' => $leads->take(2)->pluck('id')->all(),
        ])
        ->assertRedirect(route('leads.index'))
        ->assertSessionHas('ok', '2 leads compartilhados com Ana Vendedora.');

    expect($vendedor->leads()->count())->toBe(2)
        ->and($vendedor->leads()->first()->pivot->compartilhado_em)->not->toBeNull();
});

/**
 * A base tem mais de mil leads e a tela mostra cinquenta. Sem esta porta,
 * "passar Uberlandia para a Ana" seria distribuido pagina por pagina, e a
 * distribuicao sairia incompleta sem ninguem perceber.
 */
it('compartilha o recorte inteiro do filtro, e nao so a pagina aberta', function () {
    $vendedor = Staff::factory()->create(['papel' => 'vendedor']);
    Lead::factory()->count(4)->create(['cidade' => 'Uberlandia']);
    Lead::factory()->count(2)->create(['cidade' => 'Goiania']);

    admin()->post(route('leads.lote', ['cidade' => 'Uberlandia']), [
        'acao' => 'compartilhar',
        'escopo' => 'filtro',
        'vendedor' => $vendedor->id,
        // Nenhuma linha marcada de proposito: o escopo do filtro nao depende
        // do que o navegador mandou.
        'leads' => [],
    ])->assertSessionHas('ok', '4 leads compartilhados com '.$vendedor->nome.'.');

    expect($vendedor->leads()->count())->toBe(4)
        ->and($vendedor->leads()->pluck('cidade')->unique()->all())->toBe(['Uberlandia']);
});

it('nao duplica nem reescreve a data quando compartilha de novo', function () {
    $vendedor = Staff::factory()->create(['papel' => 'vendedor']);
    $lead = Lead::factory()->create();

    $compartilhar = app(App\Actions\Prospeccao\CompartilharLeads::class);

    expect($compartilhar([$lead->id], $vendedor))->toBe(1);

    $primeiraVez = $vendedor->leads()->first()->pivot->compartilhado_em;

    $this->travel(2)->days();

    expect($compartilhar([$lead->id], $vendedor))->toBe(0)
        ->and($vendedor->leads()->count())->toBe(1)
        ->and($vendedor->leads()->first()->pivot->compartilhado_em)->toBe($primeiraVez);
});

it('nao compartilha lead com quem administra', function () {
    $outroAdmin = Staff::factory()->admin()->create(['nome' => 'Outro Admin']);
    $lead = Lead::factory()->create();

    admin()->post(route('leads.lote'), [
        'acao' => 'compartilhar', 'escopo' => 'marcados',
        'vendedor' => $outroAdmin->id, 'leads' => [$lead->id],
    ])->assertSessionHas('erro', 'Escolha um vendedor da equipe.');

    expect($outroAdmin->leads()->count())->toBe(0);
});

it('recolhe o lead da mao do vendedor', function () {
    $vendedor = Staff::factory()->create(['papel' => 'vendedor', 'nome' => 'Ana Vendedora']);
    $lead = Lead::factory()->create();

    app(App\Actions\Prospeccao\CompartilharLeads::class)([$lead->id], $vendedor);

    admin()->post(route('leads.lote'), [
        'acao' => 'recolher', 'escopo' => 'marcados',
        'vendedor' => $vendedor->id, 'leads' => [$lead->id],
    ])->assertSessionHas('ok', '1 lead fora da lista de Ana Vendedora.');

    expect($vendedor->leads()->count())->toBe(0)
        ->and(Lead::whereKey($lead->id)->exists())->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| A lista do vendedor
|--------------------------------------------------------------------------
*/

it('mostra ao vendedor so os leads que ele recebeu', function () {
    [$vendedor] = carteira();
    $outro = Staff::factory()->create(['papel' => 'vendedor']);

    $dele = Lead::factory()->create(['nome' => 'LEAD DA ANA LTDA']);
    $doOutro = Lead::factory()->create(['nome' => 'LEAD DO COLEGA LTDA']);
    Lead::factory()->create(['nome' => 'LEAD DE NINGUEM LTDA']);

    $compartilhar = app(App\Actions\Prospeccao\CompartilharLeads::class);
    $compartilhar([$dele->id], $vendedor);
    $compartilhar([$doOutro->id], $outro);

    comoVendedor($vendedor)->get(route('carteira.leads'))
        ->assertOk()
        ->assertSee('LEAD DA ANA LTDA')
        ->assertDontSee('LEAD DO COLEGA LTDA')
        ->assertDontSee('LEAD DE NINGUEM LTDA');
});

it('nao deixa o filtro do vendedor alcancar lead que nao e dele', function () {
    [$vendedor] = carteira();
    $outro = Staff::factory()->create(['papel' => 'vendedor']);
    $doOutro = Lead::factory()->create(['nome' => 'LEAD DO COLEGA LTDA', 'cidade' => 'Goiania']);

    app(App\Actions\Prospeccao\CompartilharLeads::class)([$doOutro->id], $outro);

    comoVendedor($vendedor)->get(route('carteira.leads', ['cidade' => 'Goiania', 'vendedor' => $outro->id]))
        ->assertOk()
        ->assertDontSee('LEAD DO COLEGA LTDA');
});

/*
|--------------------------------------------------------------------------
| Cadastro e remocao
|--------------------------------------------------------------------------
*/

it('cadastra lead a mao guardando o CNPJ sem mascara', function () {
    admin()->post(route('leads.salvar'), [
        'nome' => 'INDICACAO DA FEIRA LTDA',
        'cnpj' => '08.876.860/0001-03',
        'cidade' => 'Contagem',
        'uf' => 'mg',
        'telefone' => '(31) 3592-2011',
        'email' => 'CONTATO@Exemplo.com',
        'origem' => 'Indicação',
        'ativo' => '1',
    ])->assertRedirect(route('leads.index'));

    $lead = Lead::firstWhere('nome', 'INDICACAO DA FEIRA LTDA');

    expect($lead->cnpj)->toBe('08876860000103')
        ->and($lead->uf)->toBe('MG')
        ->and($lead->email)->toBe('contato@exemplo.com');
});

it('remove o lead sem apagar o rastro de com quem ele esteve', function () {
    $vendedor = Staff::factory()->create(['papel' => 'vendedor']);
    $lead = Lead::factory()->create(['nome' => 'DESCARTADO LTDA']);

    app(App\Actions\Prospeccao\CompartilharLeads::class)([$lead->id], $vendedor);

    admin()->from(route('leads.index'))->delete(route('leads.remover', $lead))
        ->assertSessionHas('ok');

    expect(Lead::count())->toBe(0)
        ->and(Lead::withTrashed()->count())->toBe(1);

    admin()->from(route('leads.index', ['removidos' => 1]))
        ->post(route('leads.restaurar', $lead->id))
        ->assertSessionHas('ok');

    expect(Lead::count())->toBe(1);
});

/** Lead removido sai tambem do trabalho de quem o tinha. */
it('some da lista do vendedor quando e removido da base', function () {
    $vendedor = Staff::factory()->create(['papel' => 'vendedor']);
    $lead = Lead::factory()->create(['nome' => 'DESCARTADO LTDA']);

    app(App\Actions\Prospeccao\CompartilharLeads::class)([$lead->id], $vendedor);

    comoVendedor($vendedor)->get(route('carteira.leads'))->assertSee('DESCARTADO LTDA');

    $lead->delete();

    comoVendedor($vendedor)->get(route('carteira.leads'))->assertDontSee('DESCARTADO LTDA');
});

/*
|--------------------------------------------------------------------------
| A base em planilha
|--------------------------------------------------------------------------
*/

it('exporta o recorte que esta na tela e registra na trilha', function () {
    Lead::factory()->count(2)->create(['cidade' => 'Uberlandia']);
    Lead::factory()->create(['cidade' => 'Goiania']);

    $resposta = admin()->get(route('leads.planilha', ['cidade' => 'Uberlandia']))->assertOk();

    expect($resposta->headers->get('content-type'))->toContain('spreadsheetml')
        ->and($resposta->headers->get('content-disposition'))->toContain('avalia-leads-')
        // A planilha e um zip; o texto das celulas fica nas partes internas.
        ->and($resposta->streamedContent())->toStartWith('PK');

    $trilha = App\Models\Auditoria::where('acao', 'leads.exportados')->sole();

    expect($trilha->dados['leads'])->toBe(2)
        ->and($trilha->staff_id)->not->toBeNull()
        ->and(App\Support\Rotulos::acao('leads.exportados'))->toBe('Base de leads exportada');
});

it('recusa o lote sem nenhum lead selecionado', function () {
    $vendedor = Staff::factory()->create(['papel' => 'vendedor']);

    admin()->post(route('leads.lote'), [
        'acao' => 'compartilhar', 'escopo' => 'marcados', 'vendedor' => $vendedor->id, 'leads' => [],
    ])->assertSessionHas('erro', 'Nenhum lead selecionado.');
});
