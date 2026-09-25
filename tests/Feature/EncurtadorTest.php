<?php

use App\Models\Link;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| O encurtador
|--------------------------------------------------------------------------
|
| Existe por causa da tag NFC: as que a casa usa tem cerca de 140 bytes uteis,
| e endereco de campanha com parametros de origem nao cabe. O que cabe e
| avaliaone.com.br/l/K7M2PX.
|
| Vale aqui a mesma regra da etiqueta, e pela mesma razao: o codigo pode estar
| gravado numa tag na mao de alguem, e nao ha como pedir para tentar de novo.
|
*/

it('devolve um endereco que cabe na tag', function () {
    admin()->post(route('etiquetas.links.salvar'), [
        'destino' => 'https://loja.com.br/promo?utm_source=nfc&utm_medium=tag&utm_campaign=floripa-2026-verao',
    ])->assertRedirect();

    $link = Link::sole();

    expect($link->bytes())->toBeLessThanOrEqual((int) config('etiquetas.bytes_da_tag'))
        ->and($link->economia())->toBeGreaterThan(0)
        ->and($link->url())->toContain('/l/'.$link->codigo);
});

it('devolve o mesmo codigo para o mesmo endereco', function () {
    // Dois codigos para o mesmo lugar dividiriam a contagem de cliques ao
    // meio, e ninguem saberia por que os numeros nao batem.
    admin()->post(route('etiquetas.links.salvar'), ['destino' => 'https://loja.com.br/promo']);
    admin()->post(route('etiquetas.links.salvar'), ['destino' => 'https://loja.com.br/promo']);

    expect(Link::count())->toBe(1);
});

it('completa o https que ninguem digita', function () {
    admin()->post(route('etiquetas.links.salvar'), ['destino' => 'loja.com.br/promo']);

    expect(Link::sole()->destino)->toBe('https://loja.com.br/promo');
});

it('recusa endereco que o navegador executaria', function () {
    // Mesmo campo de texto, mesmo risco: ele manda um desconhecido para fora
    // do dominio sem clique intermediario.
    admin()->post(route('etiquetas.links.salvar'), ['destino' => 'javascript:alert(1)'])
        ->assertRedirect()
        ->assertSessionHas('erro');

    expect(Link::count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| A abertura
|--------------------------------------------------------------------------
*/

it('abre com 302 e proibe cache', function () {
    // 301 congelaria o link no primeiro destino, na maquina de quem abriu, e
    // a tag ja estaria gravada.
    admin()->post(route('etiquetas.links.salvar'), ['destino' => 'https://loja.com.br/promo']);

    $resposta = $this->get(route('l', Link::sole()->codigo));

    $resposta->assertStatus(302)->assertRedirect('https://loja.com.br/promo');

    expect($resposta->headers->get('Cache-Control'))->toContain('no-store');
});

it('abre com o caminho em maiusculas, como a tag grava', function () {
    admin()->post(route('etiquetas.links.salvar'), ['destino' => 'https://loja.com.br/promo']);

    $this->get('/L/'.Link::sole()->codigo)->assertRedirect('https://loja.com.br/promo');
});

it('conta o clique de gente e ignora o de robo', function () {
    admin()->post(route('etiquetas.links.salvar'), ['destino' => 'https://loja.com.br/promo']);
    $codigo = Link::sole()->codigo;

    $this->get(route('l', $codigo));
    $this->withHeader('User-Agent', 'WhatsApp/2.23.20.0 A')->get(route('l', $codigo));

    expect(Link::sole()->cliques)->toBe(1);
});

it('explica em vez de redirecionar quando o link esta desligado', function () {
    admin()->post(route('etiquetas.links.salvar'), ['destino' => 'https://loja.com.br/promo']);
    $link = Link::sole();

    admin()->post(route('etiquetas.links.alternar', $link));

    // Desligar nao apaga: o codigo pode estar gravado numa tag que ja saiu.
    $this->get(route('l', $link->codigo))->assertOk()->assertSee('fora do ar');

    expect(Link::whereKey($link->id)->exists())->toBeTrue();
});

it('passa a mandar para o destino novo assim que ele muda', function () {
    admin()->post(route('etiquetas.links.salvar'), ['destino' => 'https://antigo.com.br']);
    $link = Link::sole();

    $this->get(route('l', $link->codigo))->assertRedirect('https://antigo.com.br');

    $link->update(['destino' => 'https://novo.com.br']);

    $this->get(route('l', $link->codigo))->assertRedirect('https://novo.com.br');
});

/*
|--------------------------------------------------------------------------
| A porta
|--------------------------------------------------------------------------
*/

it('so encurta atras do mesmo login do QR dinamico', function () {
    // Encurtador aberto a qualquer um vira alvo de phishing em dias, e o dia
    // em que o dominio entrar numa lista de bloqueio, todas as etiquetas
    // vendidas param de abrir junto.
    $this->post(route('etiquetas.links.salvar'), ['destino' => 'https://loja.com.br'])
        ->assertRedirect(route('entrar'));

    comoVendedor(Staff::factory()->create(['papel' => 'vendedor']))
        ->get(route('etiquetas.links.index'))->assertForbidden();

    expect(Link::count())->toBe(0);
});
