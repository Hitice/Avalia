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
    admin()->post(route('etiquetas.gerar'), [
        'titulo' => 'Códigos de balcão',
        'quantidade' => 25,
    ])->assertRedirect();

    $lote = LoteEtiqueta::sole();

    expect($lote->codigo)->toBe('LT-0001')
        ->and($lote->quantidade)->toBe(25)
        ->and($lote->etiquetas)->toHaveCount(25);

    // Plaquinha sai da oficina sem dono e sem destino: ela so vira endereco de
    // alguem depois de vendida.
    $lote->etiquetas->each(function (Etiqueta $etiqueta) {
        expect($etiqueta->situacao)->toBe(SituacaoEtiqueta::EmBranco)
            ->and($etiqueta->destino)->toBeNull();
    });
});

it('numera as plaquinhas em sequencia, a partir de um', function () {
    // A sequencia e o que nomeia o arquivo dentro do ZIP e a linha do CSV que
    // o Print Merge do Corel casa com ele.
    admin()->post(route('etiquetas.gerar'), [
        'titulo' => 'Tiragem', 'quantidade' => 10,
    ]);

    expect(Etiqueta::orderBy('sequencia')->pluck('sequencia')->all())->toBe(range(1, 10));
});

it('nunca repete um codigo, nem dentro nem entre tiragens', function () {
    foreach (range(1, 3) as $vez) {
        admin()->post(route('etiquetas.gerar'), [
            'titulo' => "Tiragem {$vez}", 'quantidade' => 60,
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
    admin()->post(route('etiquetas.gerar'), [
        'titulo' => 'Exagero',
        'quantidade' => config('etiquetas.lote_maximo') + 1,
    ])->assertSessionHasErrors('quantidade');

    expect(LoteEtiqueta::count())->toBe(0)->and(Etiqueta::count())->toBe(0);
});

it('registra a tiragem na auditoria', function () {
    admin()->post(route('etiquetas.gerar'), [
        'titulo' => 'Tiragem', 'quantidade' => 5,
    ]);

    expect(Auditoria::where('acao', 'etiquetas.lote.gerado')->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| A ficha que a bancada usa
|--------------------------------------------------------------------------
*/

it('entrega o pacote da campanha dentro da propria tabela', function () {
    // Nao ha mais tela de tiragem: escolhida a campanha no filtro, o pacote da
    // grafica aparece acima da tabela. Uma tela so, em vez de duas que o
    // operador precisava lembrar qual fazia o que.
    admin()->post(route('etiquetas.gerar'), [
        'titulo' => 'Tiragem', 'quantidade' => 3,
    ]);

    $lote = LoteEtiqueta::sole();
    $primeira = $lote->etiquetas()->first();

    $resposta = admin()->get(route('etiquetas.index', ['lote' => $lote->id]))->assertOk();

    // Confere o que o navegador recebe, e nao o HTML escapado: o `@js` do
    // Blade codifica duas vezes, e um teste que persegue barra invertida so
    // guarda o formato do escape, nunca o conteudo.
    $payload = $resposta->viewData('pacote')->first();

    expect($payload['url'])->toBe($primeira->urlParaQr())
        // Maiuscula pelo modo alfanumerico do QR, que e bem mais compacto.
        ->and($payload['url'])->toContain('/Q/'.$primeira->codigo)
        ->and($payload['url'])->toBe(mb_strtoupper($payload['url']))
        // O nome do arquivo e o que casa a imagem com a linha do CSV no Corel.
        ->and($payload['arquivo'])->toBe('0001-'.$primeira->codigo);
});

it('carimba o dono em cada codigo gerado', function () {
    // Sem dono, abrir a porta significaria que qualquer conta ve os codigos de
    // todas as outras e troca o destino deles.
    $vendedor = Staff::factory()->create(['papel' => 'vendedor']);

    comoVendedor($vendedor)->post(route('etiquetas.gerar'), ['quantidade' => 5]);

    expect(Etiqueta::pluck('dono_tipo')->unique()->all())->toBe(['staff'])
        ->and(Etiqueta::pluck('dono_id')->unique()->all())->toBe([$vendedor->id]);
});

it('so monta o pacote quando ha campanha escolhida', function () {
    admin()->post(route('etiquetas.gerar'), ['titulo' => 'Campanha', 'quantidade' => 4]);

    expect(admin()->get(route('etiquetas.index'))->viewData('pacote'))->toBeNull();

    $lote = LoteEtiqueta::sole();

    expect(admin()->get(route('etiquetas.index', ['lote' => $lote->id]))->viewData('pacote'))
        ->toHaveCount(4);
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
    $painel->assertSee('QR dinâmico')->assertSee('href="/etiquetas"', false);

    expect($painel->getContent())->not->toContain('Serviços digitais');
});

it('mostra o QR dinamico na lateral de toda conta', function () {
    // A ferramenta atende todo mundo, entao o menu dela aparece para todo
    // mundo. Menu escondido de quem tem acesso e modulo que ninguem acha.
    $vendedor = Staff::factory()->create(['papel' => 'vendedor']);

    comoVendedor($vendedor)->get(route('painel'))->assertOk()->assertSee('QR dinâmico');
    comoEmpresa(empresaComPlano())->get(route('empresa.painel'))->assertOk()->assertSee('QR dinâmico');
});
