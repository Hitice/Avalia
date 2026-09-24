<?php

use App\Http\Controllers\SiteController;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| A vitrine dos servicos digitais
|--------------------------------------------------------------------------
|
| Uma lista so, em config/servicos-digitais.php, alimenta a aba do site, a
| pagina de indice e a secao dentro de /softwares. O que estes testes guardam e
| justamente isso: no dia em que um servico entrar na lista, ele precisa
| aparecer nos tres lugares sem ninguem lembrar de mexer em tres telas. Foi a
| divergencia entre cartao e secao que ja custou caro em config/softwares.php.
|
*/

it('abre a aba de serviços em todas as paginas do site', function () {
    foreach (['inicio', 'site.softwares', 'site.quem-somos', 'site.contato'] as $rota) {
        $this->get(route($rota))
            ->assertOk()
            ->assertSee('href="'.route('digitais.index').'"', false);
    }
});

it('mostra na pagina de indice todo servico publico, e so ele', function () {
    $pagina = $this->get(route('digitais.index'))->assertOk();

    foreach (SiteController::servicosPublicos() as $servico) {
        $pagina->assertSee($servico['titulo']);
    }

    // Servico decidido e ainda nao construido fica registrado no config e fora
    // da vitrine: cartao "em breve" promete data que ninguem marcou.
    $pagina->assertDontSee(config('servicos-digitais.nfc.titulo'));
});

it('mostra os mesmos servicos no pe da pagina de softwares', function () {
    $pagina = $this->get(route('site.softwares'))->assertOk();

    foreach (SiteController::servicosPublicos() as $servico) {
        $pagina->assertSee($servico['titulo']);
    }
});

it('leva cada cartao a uma pagina que existe', function () {
    foreach (SiteController::servicosPublicos() as $chave => $servico) {
        expect($servico['rota'])->not->toBeNull("o serviço {$chave} está público sem página");

        $this->get(route($servico['rota']))->assertOk();
    }
});

/*
|--------------------------------------------------------------------------
| O que cada pagina precisa dizer
|--------------------------------------------------------------------------
*/

it('diz o preco da plaquinha e o da renovacao', function () {
    // Produto de tabela: quem abre a pagina veio saber quanto custa, e
    // esconder o preco atras de um formulario e o jeito de perder a venda.
    $this->get(route('digitais.plaquinhas'))
        ->assertOk()
        ->assertSee(App\Support\Dinheiro::brl((int) config('etiquetas.precos.placa_cents')))
        ->assertSee(App\Support\Dinheiro::brl((int) config('etiquetas.precos.renovacao_cents')));
});

it('avisa que o QR gratis e estatico antes de alguem mandar imprimir mil', function () {
    $this->get(route('digitais.qr'))
        ->assertOk()
        ->assertSee('estático', false)
        // A saida para quem concluir que precisa trocar o destino depois.
        ->assertSee('href="'.route('digitais.plaquinhas').'"', false);
});

it('poe as paginas novas no mapa do site', function () {
    $mapa = $this->get(route('site.sitemap'))->assertOk();

    foreach (['digitais.index', 'digitais.plaquinhas', 'digitais.qr'] as $rota) {
        $mapa->assertSee(route($rota));
    }
});

it('nao pede sessao em nenhuma pagina da vitrine', function () {
    // Vitrine. Quem chega aqui esta decidindo se compra, e pedir senha antes
    // de dizer o preco fecha a loja.
    foreach (['digitais.index', 'digitais.plaquinhas', 'digitais.qr'] as $rota) {
        $this->get(route($rota))->assertOk();
    }
});
