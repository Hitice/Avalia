<?php

use App\Enums\SituacaoEtiqueta;
use App\Models\Auditoria;
use App\Models\Etiqueta;
use App\Models\LoteEtiqueta;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| A tiragem de plaquinhas
|--------------------------------------------------------------------------
|
| O erro caro desta etapa nao aparece na tela: aparece meses depois, quando
| duas placas com o mesmo codigo estiverem em balcoes diferentes e a freguesia
| de um lojista cair na loja do outro. Depois de cortado o acrilico, nao ha
| conserto. Por isso a unicidade e cobrada aqui e tambem no indice do banco.
|
*/

it('abre a tiragem com a quantidade pedida, toda em branco', function () {
    admin()->post(route('etiquetas.lotes.salvar'), [
        'titulo' => 'Plaquinhas de balcão',
        'quantidade' => 25,
        'tipo' => 'qr_nfc',
    ])->assertRedirect();

    $lote = LoteEtiqueta::sole();

    expect($lote->codigo)->toBe('LT-0001')
        ->and($lote->quantidade)->toBe(25)
        ->and($lote->etiquetas)->toHaveCount(25);

    // Plaquinha sai da oficina sem dono e sem destino: ela so vira endereco de
    // alguem depois de vendida.
    $lote->etiquetas->each(function (Etiqueta $etiqueta) {
        expect($etiqueta->situacao)->toBe(SituacaoEtiqueta::EmBranco)
            ->and($etiqueta->destino)->toBeNull()
            ->and($etiqueta->tipo)->toBe('qr_nfc');
    });
});

it('numera as plaquinhas em sequencia, a partir de um', function () {
    // A sequencia e o que nomeia o arquivo dentro do ZIP e a linha do CSV que
    // o Print Merge do Corel casa com ele.
    admin()->post(route('etiquetas.lotes.salvar'), [
        'titulo' => 'Tiragem', 'quantidade' => 10, 'tipo' => 'qr',
    ]);

    expect(Etiqueta::orderBy('sequencia')->pluck('sequencia')->all())->toBe(range(1, 10));
});

it('nunca repete um codigo, nem dentro nem entre tiragens', function () {
    foreach (range(1, 3) as $vez) {
        admin()->post(route('etiquetas.lotes.salvar'), [
            'titulo' => "Tiragem {$vez}", 'quantidade' => 60, 'tipo' => 'qr',
        ]);
    }

    $codigos = Etiqueta::pluck('codigo');

    expect($codigos)->toHaveCount(180)
        ->and($codigos->unique())->toHaveCount(180);
});

it('deixa o banco recusar codigo repetido, e nao so o sorteio', function () {
    // O sorteio confere antes de gravar, mas quem garante e o indice unico:
    // duas tiragens abertas ao mesmo tempo passariam pela conferencia e
    // chegariam juntas no banco.
    Etiqueta::factory()->create(['codigo' => 'K7M2PX']);

    expect(fn () => Etiqueta::factory()->create(['codigo' => 'K7M2PX']))
        ->toThrow(Illuminate\Database\QueryException::class);
});

it('recusa tiragem acima do teto', function () {
    // Quem desenha os arquivos e o navegador de quem pediu, e acima do teto a
    // aba fica presa por minutos montando imagem.
    admin()->post(route('etiquetas.lotes.salvar'), [
        'titulo' => 'Exagero',
        'quantidade' => config('etiquetas.lote_maximo') + 1,
        'tipo' => 'qr',
    ])->assertSessionHasErrors('quantidade');

    expect(LoteEtiqueta::count())->toBe(0)->and(Etiqueta::count())->toBe(0);
});

it('registra a tiragem na auditoria', function () {
    admin()->post(route('etiquetas.lotes.salvar'), [
        'titulo' => 'Tiragem', 'quantidade' => 5, 'tipo' => 'qr',
    ]);

    expect(Auditoria::where('acao', 'etiquetas.lote.gerado')->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| A ficha que a bancada usa
|--------------------------------------------------------------------------
*/

it('entrega a ficha com o endereco do QR em maiusculo', function () {
    admin()->post(route('etiquetas.lotes.salvar'), [
        'titulo' => 'Tiragem', 'quantidade' => 3, 'tipo' => 'qr',
    ]);

    $lote = LoteEtiqueta::sole();
    $primeira = $lote->etiquetas()->first();

    $resposta = admin()->get(route('etiquetas.lotes.ficha', $lote))->assertOk();

    // Confere o que o navegador recebe, e nao o HTML escapado: o `@js` do
    // Blade codifica duas vezes, e um teste que persegue barra invertida so
    // guarda o formato do escape, nunca o conteudo.
    $payload = $resposta->viewData('etiquetas')->first();

    expect($payload['url'])->toBe($primeira->urlParaQr())
        // Maiuscula pelo modo alfanumerico do QR, que e bem mais compacto.
        ->and($payload['url'])->toContain('/Q/'.$primeira->codigo)
        ->and($payload['url'])->toBe(mb_strtoupper($payload['url']))
        // O nome do arquivo e o que casa a imagem com a linha do CSV no Corel.
        ->and($payload['arquivo'])->toBe('0001-'.$primeira->codigo);
});

it('mantem o vendedor fora das tiragens', function () {
    // Aqui se decide para onde aponta a placa que esta no balcao de um
    // cliente. Nao e acao de carteira.
    $vendedor = Staff::factory()->create(['papel' => 'vendedor']);

    comoVendedor($vendedor)->get(route('etiquetas.lotes.index'))->assertForbidden();
    comoVendedor($vendedor)->get(route('etiquetas.lotes.criar'))->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| O caminho ate a tela
|--------------------------------------------------------------------------
*/

it('poe as plaquinhas na lateral, sem esconder atras de um pai', function () {
    // Item solto, e nao submenu. Submenu comeca fechado, e o modulo que so
    // aparece depois de um clique e o modulo que ninguem acha: foi assim que
    // as telas do vendedor sumiram dentro de Carteira.
    $painel = admin()->get(route('painel'))->assertOk();

    // A lateral monta href com caminho relativo, e nao com a URL inteira.
    $painel->assertSee('Plaquinhas')->assertSee('href="/etiquetas"', false);

    expect($painel->getContent())->not->toContain('Serviços digitais');
});

it('nao mostra plaquinhas ao vendedor', function () {
    // Menu que leva a 403 ensina o operador a ignorar o menu.
    $vendedor = Staff::factory()->create(['papel' => 'vendedor']);

    comoVendedor($vendedor)->get(route('painel'))->assertOk()->assertDontSee('Plaquinhas');
});
