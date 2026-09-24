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
| A porta da administracao no cartao
|--------------------------------------------------------------------------
*/

it('abre o acesso no proprio cartao das plaquinhas', function () {
    $this->get(route('digitais.index'))
        ->assertOk()
        ->assertSee('Entrar na ferramenta')
        ->assertSee('abrir-porta', false)
        // Link de verdade para a porta principal: sem JavaScript o clique
        // ainda leva a algum lugar, em vez de nao fazer nada.
        ->assertSee('href="'.route('entrar').'"', false);
});

it('leva o admin a ferramenta assim que a senha passa', function () {
    $admin = App\Models\Staff::factory()->admin()->create(['senha' => bcrypt('segredo-de-teste')]);

    $this->post(route('entrar.enviar'), [
        'email' => $admin->email,
        'senha' => 'segredo-de-teste',
        'destino' => 'plaquinhas',
    ])->assertRedirect(route('etiquetas.index'));
});

it('nao aceita endereco no campo de destino', function () {
    // Campo de destino que aceita URL vira redirecionamento aberto: bastaria
    // um link de login com destino para outro dominio, e a pessoa entraria na
    // Avalia e sairia num site clonado achando que continuava aqui.
    $admin = App\Models\Staff::factory()->admin()->create(['senha' => bcrypt('segredo-de-teste')]);

    $this->post(route('entrar.enviar'), [
        'email' => $admin->email,
        'senha' => 'segredo-de-teste',
        'destino' => 'https://site-clonado.example.com',
    ])->assertSessionHasErrors('destino');
});

it('nao manda o cliente para a ferramenta da administracao', function () {
    // Login certo que termina em 403 e lido como recusa da senha.
    $empresa = App\Models\Cliente::factory()->create(['senha' => bcrypt('segredo-de-teste')]);

    $this->post(route('entrar.enviar'), [
        'email' => $empresa->email,
        'senha' => 'segredo-de-teste',
        'destino' => 'plaquinhas',
    ])->assertRedirect(route('empresa.painel'));
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

it('nao repete id em pagina nenhuma do site', function () {
    // O layout ja tem `main id="conteudo"`, alvo do link de pular para o
    // conteudo. Um campo com o mesmo id quebra a associacao do label e faz o
    // acesso por nome no navegador devolver uma colecao em vez do elemento:
    // foi assim que o gerador imprimiu "[object HTMLCollection]" dentro de um
    // campo, sem erro nenhum no console.
    foreach (['inicio', 'digitais.index', 'digitais.plaquinhas', 'digitais.qr', 'site.contato'] as $rota) {
        preg_match_all('/\sid="([^"]+)"/', $this->get(route($rota))->getContent(), $ids);

        $repetidos = array_keys(array_filter(array_count_values($ids[1]), fn (int $vezes) => $vezes > 1));

        expect($repetidos)->toBeEmpty("a rota {$rota} repete id: ".implode(', ', $repetidos));
    }
});
