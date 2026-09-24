<?php

use App\Models\AcessoEtiqueta;
use App\Models\Etiqueta;
use App\Support\Dinheiro;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| A leitura de uma plaquinha
|--------------------------------------------------------------------------
|
| A rota mais importante do sistema, e a unica cujo erro nao tem conserto: o
| codigo ja esta gravado em acrilico no balcao de uma loja, e quem ler a placa
| nao vai tentar de novo amanha.
|
| O primeiro teste deste arquivo e o que sustenta o produto inteiro. Se o
| redirecionamento sair como 301, navegador, provedor e antivirus guardam o
| destino para sempre, a plaquinha congela no primeiro endereco, e nao ha como
| desfazer: o cache mora na maquina de quem leu. O produto que estamos vendendo
| e justamente poder trocar o destino depois de impresso.
|
*/

it('redireciona com 302 e proibe qualquer cache', function () {
    $etiqueta = Etiqueta::factory()->ativa('https://padariadoze.com.br')->create(['codigo' => 'K7M2PX']);

    $resposta = $this->get('/q/K7M2PX');

    $resposta->assertStatus(302)
        ->assertRedirect('https://padariadoze.com.br');

    // 301 seria permanente e irreversivel do lado de quem leu.
    expect($resposta->getStatusCode())->not->toBe(301)
        ->and($resposta->headers->get('Cache-Control'))->toContain('no-store');

    expect($etiqueta->fresh()->total_acessos)->toBe(1);
});

it('nao guarda sessao de quem so leu a plaquinha', function () {
    // A rota vive fora do grupo `web` de proposito: sessao por leitura
    // significaria um arquivo no disco da hospedagem por freguês que passou na
    // frente de uma loja. Cookie de sessao aqui e sinal de que ela voltou.
    Etiqueta::factory()->ativa()->create(['codigo' => 'K7M2PX']);

    $resposta = $this->get('/q/K7M2PX');

    expect($resposta->headers->getCookies())->toBeEmpty();
});

/*
|--------------------------------------------------------------------------
| O codigo do jeito que a pessoa digita
|--------------------------------------------------------------------------
*/

it('acha a plaquinha com o codigo digitado em minusculo', function () {
    // O QR grava em MAIUSCULO pelo modo alfanumerico, mas quem digita a mao
    // digita como quiser.
    Etiqueta::factory()->ativa('https://exemplo.com.br')->create(['codigo' => 'K7M2PX']);

    $this->get('/q/k7m2px')->assertRedirect('https://exemplo.com.br');
});

it('acha a plaquinha quando trocam 1 por I e 0 por O', function () {
    // Sao as letras que o alfabeto exclui justamente porque se confundem com
    // digito no acrilico. Quem le a placa e digita, erra exatamente assim.
    Etiqueta::factory()->ativa('https://exemplo.com.br')->create(['codigo' => 'K7M2P1']);

    $this->get('/q/K7M2PI')->assertRedirect('https://exemplo.com.br');
    $this->get('/q/k7m2pl')->assertRedirect('https://exemplo.com.br');
});

/*
|--------------------------------------------------------------------------
| Os estados que nao redirecionam
|--------------------------------------------------------------------------
|
| Nenhum deles devolve erro cru: do outro lado ha um fregues parado no balcao
| de uma loja, e ele merece uma pagina que explique.
|
*/

it('explica a plaquinha em branco em vez de devolver 404', function () {
    Etiqueta::factory()->create(['codigo' => 'K7M2PX']);

    $this->get('/q/K7M2PX')
        ->assertOk()
        ->assertSee('ainda não foi ativada')
        ->assertSee('K7M2PX');
});

it('nao redireciona plaquinha suspensa', function () {
    Etiqueta::factory()->suspensa()->create(['codigo' => 'K7M2PX']);

    $this->get('/q/K7M2PX')->assertOk()->assertSee('fora do ar');
});

it('continua redirecionando durante a carencia', function () {
    // Trinta dias depois do vencimento a loja do cliente continua de pe. Quem
    // esqueceu o boleto nao perde a freguesia por causa disso.
    Etiqueta::factory()->vencidaHa(10)->create(['codigo' => 'K7M2PX', 'destino' => 'https://exemplo.com.br']);

    $this->get('/q/K7M2PX')->assertRedirect('https://exemplo.com.br');
});

it('oferece a renovacao quando a carencia acaba', function () {
    Etiqueta::factory()->vencidaHa(31)->create(['codigo' => 'K7M2PX']);

    $this->get('/q/K7M2PX')
        ->assertOk()
        // O titulo e neutro: quem le a placa e o fregues do lojista, e cobranca
        // na cara dele constrange o nosso cliente na frente do cliente dele.
        ->assertSee('fora do ar')
        ->assertSee('É o responsável por esta plaquinha?', false)
        ->assertSee(Dinheiro::brl((int) config('etiquetas.precos.renovacao_cents')));
});

it('nao redireciona plaquinha baixada', function () {
    Etiqueta::factory()->baixada()->create(['codigo' => 'K7M2PX']);

    $this->get('/q/K7M2PX')->assertOk()->assertSee('encerrada');
});

it('explica o codigo inexistente com a cara da casa', function () {
    $this->get('/q/K7M2PX')
        ->assertNotFound()
        ->assertSee('Este código não existe.', false)
        // A dica que resolve metade dos chamados de suporte.
        ->assertSee('I, L, O e U', false);
});

it('nao deixa a leitura entrar em buscador', function () {
    Etiqueta::factory()->create(['codigo' => 'K7M2PX']);

    $this->get('/q/K7M2PX')->assertSee('noindex', false);
});

/*
|--------------------------------------------------------------------------
| A contagem
|--------------------------------------------------------------------------
*/

it('soma no mesmo dia em vez de abrir linha nova', function () {
    $etiqueta = Etiqueta::factory()->ativa()->create(['codigo' => 'K7M2PX']);

    $this->get('/q/K7M2PX');
    $this->get('/q/K7M2PX');
    $this->get('/q/K7M2PX');

    // Uma linha por placa por dia. Uma linha por leitura encheria a base de
    // uma hospedagem compartilhada sem responder nada melhor.
    expect(AcessoEtiqueta::count())->toBe(1)
        ->and(AcessoEtiqueta::first()->total)->toBe(3)
        ->and($etiqueta->fresh()->total_acessos)->toBe(3);
});

it('nao conta a previa de link do WhatsApp como leitura', function () {
    // O WhatsApp busca toda URL que passa por ele para montar a previa. Sem
    // filtro, o cliente ve 40 leituras numa placa que ninguem olhou, e a
    // partir dai o numero nao vale nada.
    $etiqueta = Etiqueta::factory()->ativa()->create(['codigo' => 'K7M2PX']);

    $this->withHeader('User-Agent', 'WhatsApp/2.23.20.0 A')->get('/q/K7M2PX')->assertRedirect();

    expect(AcessoEtiqueta::count())->toBe(0)
        ->and($etiqueta->fresh()->total_acessos)->toBe(0);
});

it('conta a leitura de um celular comum, mesmo de marca com bot no nome', function () {
    // CUBOT e marca de Android. Um filtro que procurasse "bot" solto apagaria
    // em silencio as leituras de quem tem um.
    $etiqueta = Etiqueta::factory()->ativa()->create(['codigo' => 'K7M2PX']);

    $this->withHeader('User-Agent', 'Mozilla/5.0 (Linux; Android 13; CUBOT_X30) Chrome/120')
        ->get('/q/K7M2PX')->assertRedirect();

    expect($etiqueta->fresh()->total_acessos)->toBe(1);
});

it('nao conta a requisicao HEAD que so busca cabecalho', function () {
    $etiqueta = Etiqueta::factory()->ativa()->create(['codigo' => 'K7M2PX']);

    $this->head('/q/K7M2PX')->assertStatus(302);

    expect($etiqueta->fresh()->total_acessos)->toBe(0);
});

/*
|--------------------------------------------------------------------------
| O cache do destino
|--------------------------------------------------------------------------
*/

it('passa a mandar para o destino novo assim que ele muda', function () {
    // O cache curto tira o banco do caminho da rota mais quente, mas trocar o
    // destino nao pode esperar por ele: sem a limpeza no evento do model, a
    // tela diria "trocado" e a placa continuaria mandando para o lugar velho.
    $etiqueta = Etiqueta::factory()->ativa('https://antigo.com.br')->create(['codigo' => 'K7M2PX']);

    $this->get('/q/K7M2PX')->assertRedirect('https://antigo.com.br');

    $etiqueta->update(['destino' => 'https://novo.com.br']);

    $this->get('/q/K7M2PX')->assertRedirect('https://novo.com.br');
});

it('para de redirecionar assim que a plaquinha e suspensa', function () {
    $etiqueta = Etiqueta::factory()->ativa()->create(['codigo' => 'K7M2PX']);

    $this->get('/q/K7M2PX')->assertStatus(302);

    $etiqueta->update(['situacao' => App\Enums\SituacaoEtiqueta::Suspensa]);

    $this->get('/q/K7M2PX')->assertOk()->assertSee('fora do ar');
});

/*
|--------------------------------------------------------------------------
| O endereco que vai impresso
|--------------------------------------------------------------------------
*/

it('grava o endereco do QR em maiusculo, e ele funciona assim', function () {
    // Maiusculo nao e capricho: e o modo alfanumerico do QR, que e bem mais
    // compacto que o modo byte. Mas so vale se a URL maiuscula abrir de
    // verdade, e e isso que este teste prova de ponta a ponta.
    $etiqueta = Etiqueta::factory()->ativa('https://exemplo.com.br')->create(['codigo' => 'K7M2PX']);

    expect($etiqueta->urlParaQr())->toBe(mb_strtoupper($etiqueta->url()))
        ->and($etiqueta->urlParaQr())->toContain('/Q/K7M2PX');

    $this->get($etiqueta->urlParaQr())->assertRedirect('https://exemplo.com.br');
});
