<?php

it('link gerado sem negocio cria o negocio, e o lastro traz os antigos', function () {
    $link = app(App\Actions\Negocios\GerarLinkDeAvaliacao::class)('ChIJN1t_tDeuEmsRUsoyG83frY4', 'Padaria do Zé');
    $negocio = App\Models\Negocio::where('link_avaliacao_id', $link->id)->sole();
    expect($negocio->nome)->toBe('Padaria do Zé')->and($negocio->place_id)->toBe('ChIJN1t_tDeuEmsRUsoyG83frY4');

    // Um link antigo, solto, e uma placa vendida sem negocio.
    App\Models\Negocio::query()->delete();
    App\Models\Etiqueta::factory()->ativa()->create(['cliente_nome' => 'Bar do João', 'cliente_contato' => '34988881111', 'negocio_id' => null]);

    test()->artisan('avalia:lastrear-negocios')->assertSuccessful();
    test()->artisan('avalia:lastrear-negocios')->assertSuccessful();

    expect(App\Models\Negocio::count())->toBe(2)
        ->and(App\Models\Negocio::where('link_avaliacao_id', $link->id)->exists())->toBeTrue()
        ->and(App\Models\Etiqueta::whereNull('negocio_id')->whereNotNull('vendida_em')->count())->toBe(0);
});

use App\Models\Conexao;
use App\Models\Link;
use App\Models\Negocio;
use App\Support\LinkDeAvaliacao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/** A chave do Google, cadastrada em Conexoes como qualquer outro fornecedor. */
function conexaoGoogle(): Conexao
{
    return Conexao::create([
        'fornecedor' => 'google',
        'ambiente' => 'producao',
        'credenciais' => ['api_key' => 'chave-de-teste'],
        'ativa' => true,
    ]);
}

/** @param list<array{0: string, 1: string, 2: string}> $lugares */
function googleResponde(array $lugares): void
{
    Http::fake(['places.googleapis.com/*' => Http::response([
        'places' => collect($lugares)->map(fn (array $l) => [
            'id' => $l[0],
            'displayName' => ['text' => $l[1]],
            'formattedAddress' => $l[2],
        ])->all(),
    ])]);
}

/*
|--------------------------------------------------------------------------
| O endereco de avaliacao
|--------------------------------------------------------------------------
*/

it('monta o endereco documentado, que aceita place id', function () {
    expect(LinkDeAvaliacao::de('ChIJN1t_tDeuEmsRUsoyG83frY4'))
        ->toBe('https://search.google.com/local/writereview?placeid=ChIJN1t_tDeuEmsRUsoyG83frY4');
});

it('recusa o que nao tem cara de place id, antes de gastar consulta cobrada', function () {
    expect(LinkDeAvaliacao::pareceValido('ChIJN1t_tDeuEmsRUsoyG83frY4'))->toBeTrue()
        ->and(LinkDeAvaliacao::pareceValido('curto'))->toBeFalse()
        ->and(LinkDeAvaliacao::pareceValido('tem espaço no meio'))->toBeFalse()
        ->and(LinkDeAvaliacao::pareceValido(null))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| O servico, de ponta a ponta
|--------------------------------------------------------------------------
*/

it('acha um so e devolve o link curto num passo', function () {
    conexaoGoogle();
    googleResponde([['ChIJabc123def456', 'Barbearia Dom', 'Rua A, 10 - Uberlândia']]);

    $resposta = admin()->post(route('negocios.avaliacao.buscar'), ['nome' => 'Barbearia Dom']);

    $link = Link::first();

    expect($link->destino)->toBe(LinkDeAvaliacao::de('ChIJabc123def456'))
        ->and($link->titulo)->toBe('Avaliação no Google: Barbearia Dom');

    $resposta->assertSessionHas('linkPronto', route('l', ['codigo' => $link->codigo]));
});

it('pede para escolher quando o nome repete, em vez de chutar', function () {
    // Link errado manda a freguesia do cliente avaliar o concorrente.
    conexaoGoogle();
    googleResponde([
        ['ChIJprimeiro00001', 'Pizzaria do Centro', 'Rua A, 10 - Uberlândia'],
        ['ChIJsegundo000002', 'Pizzaria do Centro', 'Av B, 900 - Araguari'],
    ]);

    admin()->post(route('negocios.avaliacao.buscar'), ['nome' => 'Pizzaria do Centro'])
        ->assertSessionHas('lugares');

    expect(Link::count())->toBe(0);
});

it('gera depois da escolha, e amarra ao negocio', function () {
    conexaoGoogle();
    $negocio = Negocio::factory()->create(['nome' => 'Pizzaria do Centro']);

    admin()->post(route('negocios.avaliacao.gerar'), [
        'place_id' => 'ChIJsegundo000002',
        'nome' => 'Pizzaria do Centro',
        'negocio_id' => $negocio->id,
    ])->assertSessionHas('linkPronto');

    $negocio->refresh();

    expect($negocio->place_id)->toBe('ChIJsegundo000002')
        ->and($negocio->linkAvaliacao->destino)->toBe(LinkDeAvaliacao::de('ChIJsegundo000002'));
});

it('pedir duas vezes devolve o mesmo codigo, sem dividir os cliques', function () {
    conexaoGoogle();
    $dados = ['place_id' => 'ChIJabc123def456', 'nome' => 'Barbearia Dom'];

    admin()->post(route('negocios.avaliacao.gerar'), $dados);
    admin()->post(route('negocios.avaliacao.gerar'), $dados);

    expect(Link::count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Quando nao da
|--------------------------------------------------------------------------
*/

it('diz para ativar quando a chave esta cadastrada e a conexao esta desligada', function () {
    // Salvar credencial nao ativa conexao, de proposito: a API e cobrada. Mas a
    // mensagem unica mandava cadastrar a chave a quem ja tinha cadastrado, e isso
    // custou tempo de verdade.
    Conexao::create([
        'fornecedor' => 'google',
        'ambiente' => 'producao',
        'credenciais' => ['api_key' => 'chave-de-teste'],
        'ativa' => false,
    ]);

    Http::fake();

    admin()->post(route('negocios.avaliacao.buscar'), ['nome' => 'Barbearia Dom']);

    expect(session('erro'))->toContain('Ativar');
    Http::assertNothingSent();
});

it('recusa sem a chave cadastrada, em vez de dizer que nao achou', function () {
    // Tela que diz "nenhum resultado" sem credencial ensina a operacao a achar
    // que o estabelecimento nao esta no Google.
    Http::fake();

    admin()->post(route('negocios.avaliacao.buscar'), ['nome' => 'Barbearia Dom'])
        ->assertSessionHas('erro');

    expect(Link::count())->toBe(0);
    Http::assertNothingSent();
});

it('diz o que fazer quando o google recusa a chave', function () {
    conexaoGoogle();
    Http::fake(['places.googleapis.com/*' => Http::response(['error' => ['message' => 'nao']], 403)]);

    $resposta = admin()->post(route('negocios.avaliacao.buscar'), ['nome' => 'Barbearia Dom']);

    // Aponta para o Google Cloud, e nao para Conexoes: um 403 com a chave ativa
    // e restricao de site ou API nao habilitada, e isso se resolve la.
    expect(session('erro'))->toContain('Google Cloud');
    $resposta->assertRedirect();
});

it('avisa quando o google nao acha nada', function () {
    conexaoGoogle();
    googleResponde([]);

    admin()->post(route('negocios.avaliacao.buscar'), ['nome' => 'Loja que nao existe']);

    expect(session('erro'))->toContain('não achou');
});

it('nao abre para vendedor', function () {
    $vendedor = App\Models\Staff::factory()->create(['papel' => 'vendedor']);

    test()->actingAs($vendedor, 'staff')->withSession(['versao_staff' => 1])
        ->post(route('negocios.avaliacao.buscar'), ['nome' => 'Barbearia Dom'])
        ->assertForbidden();
});

it('pede a cidade so depois de nao achar', function () {
    // Na primeira tentativa a cidade e ruido; na segunda e o que desempata.
    conexaoGoogle();
    googleResponde([]);

    admin()->post(route('negocios.avaliacao.buscar'), ['nome' => 'Loja que nao existe'])
        ->assertSessionHas('pedirCidade', true);

    expect(session('erro'))->toContain('tente de novo');
});

it('manda conferir o link antes de usar', function () {
    conexaoGoogle();
    googleResponde([['ChIJabc123def456', 'Barbearia Dom', 'Rua A, 10']]);

    // from(): o back() do controller volta para onde veio, e sem referer o
    // teste caia na home, onde o aviso nao esta.
    $html = admin()->from(route('negocios'))->followingRedirects()
        ->post(route('negocios.avaliacao.buscar'), ['nome' => 'Barbearia Dom'])
        ->getContent();

    expect($html)->toContain('Confira o link antes de cadastrar');
});

it('tirou de Negocios o link de cadastro por vendedor', function () {
    $html = admin()->get(route('negocios'))->assertOk()->getContent();

    expect($html)->not->toContain('SEU_NOME')
        ->and($html)->not->toContain('Link de cadastro para enviar');
});
