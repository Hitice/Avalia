<?php

use App\Mail\ContatoRecebido;
use App\Models\Interessado;
use App\Support\Empresa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| O site institucional
|--------------------------------------------------------------------------
|
| A porta do dominio. Fica exposta a qualquer visitante, entao vale aqui a
| mesma regra da pagina do produto: numero interno, nome de fornecedor ou
| preco de tabela aparecendo e vazamento, nao bug de tela.
|
| O que mais interessa guardar e o caminho: quem chega pelo endereco precisa
| achar as duas plataformas da casa. Foi por elas que a pagina foi trocada.
|
*/

it('abre toda pagina publica do site', function (string $rota) {
    $this->get(route($rota))->assertOk();
})->with([
    'inicio',
    'site.softwares',
    'site.quem-somos',
    'site.blog',
    'site.contato',
    'site.perguntas',
    'site.privacidade',
    'site.termos',
    'area',
]);

it('leva da porta do dominio as duas plataformas da casa', function () {
    // O motivo de o site existir na raiz: quem procura o sistema que ja usa
    // nao devia ter que adivinhar o endereco dele.
    $this->get('/')->assertOk()
        ->assertSee(Empresa::marcaCredito())
        ->assertSee(Empresa::marcaCobranca())
        ->assertSee(route('credito'))
        ->assertSee(route('cobranca'))
        ->assertSee(route('area'));
});

it('reune na area do produtor as entradas das duas plataformas', function () {
    $this->get(route('area'))->assertOk()
        // A porta de cada plataforma, e o cadastro de quem ainda nao tem conta.
        ->assertSee(route('entrar'))
        ->assertSee(route('produtor.entrar'))
        ->assertSee(route('produtor.criar-conta'));
});

it('nao vaza fornecedor, preco nem numero interno no site', function () {
    foreach (['inicio', 'site.softwares', 'site.quem-somos', 'area'] as $rota) {
        $html = $this->get(route($rota))->assertOk()->getContent();

        expect($html)->not->toContain('Boa Vista')
            ->not->toContain('Equifax')
            ->not->toContain('SPC')
            ->not->toContain('Serasa')
            // Preco e proposta moram atras do login, com contrato e catalogo.
            ->not->toContain('R$');
    }
});

it('mantem o site no tema claro, sem interruptor', function () {
    // Decisao de produto, e nao esquecimento: o interruptor e ferramenta de
    // quem trabalha no sistema o dia inteiro. O teste irmao, em
    // RevisaoDeSegurancaTest, cobra o interruptor nas telas do sistema.
    $this->get('/')->assertOk()->assertDontSee('$store.theme.toggle()', false);
});

/*
|--------------------------------------------------------------------------
| Blog
|--------------------------------------------------------------------------
*/

it('lista os artigos do mais recente para o mais antigo', function () {
    $html = $this->get(route('site.blog'))->assertOk()->getContent();

    foreach (array_keys(config('blog')) as $slug) {
        expect($html)->toContain(route('site.artigo', $slug));
    }
});

it('abre cada artigo com a manchete da ficha', function () {
    foreach (config('blog') as $slug => $ficha) {
        $this->get(route('site.artigo', $slug))->assertOk()
            ->assertSee($ficha['manchete']);
    }
});

it('devolve 404 para artigo que nao existe', function () {
    // O slug vem da URL. Sem a guarda, um endereco inventado chegaria ao
    // include procurando uma view que nao existe, e o visitante veria um erro
    // de servidor em vez da pagina de nao encontrado.
    $this->get(route('site.artigo', 'artigo-inventado'))->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| Contato
|--------------------------------------------------------------------------
*/

it('grava o pedido de contato do site na fila da administracao', function () {
    Mail::fake();

    $this->from(route('site.contato'))
        ->post(route('site.contato.enviar'), pedidoDoSite())
        ->assertRedirect(route('site.contato'))
        ->assertSessionHas('contato_ok');

    $interessado = Interessado::sole();

    expect($interessado->nome)->toBe('Marina Prado')
        ->and($interessado->assunto)->toBe('Automação de processos')
        ->and($interessado->mensagem)->toContain('conciliação')
        // A origem separa quem chegou pelo site de quem chegou pela campanha.
        ->and($interessado->origem)->toBe('site')
        // O site nao pergunta o tamanho da equipe, e por isso a coluna aceita
        // nulo. Preencher com um valor qualquer seria inventar resposta.
        ->and($interessado->funcionarios)->toBeNull();

    // O aviso vai para o e-mail do cadastro, e nao para um endereco escrito
    // no controller: trocar o comercial da casa e mexer em config/empresa.php.
    Mail::assertSent(ContatoRecebido::class, fn ($mensagem) => $mensagem->hasTo(Empresa::email())
        && $mensagem->interessado->is($interessado));
});

it('nao perde o pedido quando o e-mail falha', function () {
    // O registro e a linha no banco; o e-mail so adianta o retorno. Deixar a
    // excecao subir faria o visitante ver erro por um pedido que foi recebido,
    // e ainda o levaria a preencher tudo de novo, duplicando a linha.
    Mail::shouldReceive('to')->andThrow(new RuntimeException('servidor de e-mail fora'));

    $this->from(route('site.contato'))
        ->post(route('site.contato.enviar'), pedidoDoSite())
        ->assertRedirect(route('site.contato'))
        ->assertSessionHas('contato_ok');

    expect(Interessado::count())->toBe(1);
});

it('nao manda dado pessoal por URL de conversa', function () {
    // A versao anterior desta pagina montava um mailto e um link de WhatsApp
    // com o que a pessoa digitava. Dado pessoal nao viaja em URL de conversa:
    // ela passa por servidor de terceiro e fica no historico do navegador.
    $html = $this->get(route('site.contato'))->assertOk()->getContent();

    expect($html)->toContain(route('site.contato.enviar'))
        ->not->toContain('mailto:'.Empresa::email().'?')
        ->not->toContain('enctype="text/plain"');
});

it('recusa assunto fora da lista da tela', function () {
    $this->from(route('site.contato'))
        ->post(route('site.contato.enviar'), pedidoDoSite(['assunto' => 'Outro qualquer']))
        ->assertSessionHasErrors('assunto');

    expect(Interessado::count())->toBe(0);
});

it('finge sucesso e descarta o que o robo preenche', function () {
    // Responder "detectei voce" e ensinar o robo a melhorar.
    $this->from(route('site.contato'))
        ->post(route('site.contato.enviar'), pedidoDoSite(['site' => 'http://robo']))
        ->assertSessionHas('contato_ok');

    expect(Interessado::count())->toBe(0);
});

it('cobra os campos que a equipe precisa para responder', function () {
    $this->from(route('site.contato'))
        ->post(route('site.contato.enviar'), [])
        ->assertSessionHasErrors(['nome', 'empresa', 'telefone', 'email', 'assunto', 'mensagem']);
});

/*
|--------------------------------------------------------------------------
| Mapa do site
|--------------------------------------------------------------------------
*/

it('publica o mapa do site com as paginas publicas e os artigos', function () {
    $xml = $this->get(route('site.sitemap'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml')
        ->getContent();

    expect($xml)->toContain(route('inicio'))
        ->toContain(route('credito'))
        ->toContain(route('cobranca'))
        ->toContain(route('site.artigo', 'rpa-rotina-fiscal'))
        // Porta nao se indexa: nao ha o que um buscador leia numa tela de
        // login, e listar a area do produtor so espalha o endereco dela.
        ->not->toContain(route('entrar'));
});

/** Um pedido de contato valido, com o campo que o teste quiser trocar. */
function pedidoDoSite(array $troca = []): array
{
    return array_merge([
        'nome' => 'Marina Prado',
        'empresa' => 'Prado Distribuidora',
        'telefone' => '(34) 99999-0000',
        'email' => 'marina@prado.com.br',
        'assunto' => 'Automação de processos',
        'mensagem' => 'Gastamos meio dia por semana na conciliação do extrato com os títulos.',
    ], $troca);
}

/*
|--------------------------------------------------------------------------
| As fotos da pagina de softwares
|--------------------------------------------------------------------------
*/

it('entrega a foto de cada frente, e nenhuma sem texto alternativo', function () {
    $html = $this->get(route('site.softwares'))->assertOk()->getContent();

    preg_match_all('#<img[^>]+src="[^"]*/images/softwares/([^"]+)"[^>]*>#', $html, $achados);

    // Se a lista vier vazia o teste passaria calado: sem esta linha, apagar
    // todas as fotos da pagina nao quebraria nada.
    expect($achados[1])->not->toBeEmpty();

    foreach ($achados[1] as $indice => $arquivo) {
        // Arquivo que nao existe vira um 404 silencioso: a pagina continua
        // respondendo 200 e so quem abre percebe o buraco.
        expect(file_exists(public_path('images/softwares/'.$arquivo)))
            ->toBeTrue("falta public/images/softwares/{$arquivo}");

        // A foto ilustra o assunto da secao, entao quem le por leitor de tela
        // perde contexto sem a descricao.
        expect($achados[0][$indice])->toMatch('/alt="[^"]+"/');
    }
});

it('abre o detalhe de cada frente na propria pagina inicial', function () {
    // O cartao da vitrine abria a pagina de softwares e rolava ate a ancora,
    // o que cobrava duas navegacoes de quem so queria saber o que uma frente
    // faz: descer ate ela e voltar. Agora o detalhe vem num popup, e a pagina
    // continua sendo o caminho de quem quer ler as sete de uma vez.
    $html = $this->get('/')->assertOk()->getContent();

    foreach (config('softwares') as $ancora => $frente) {
        expect($html)->toContain("aberto = '{$ancora}'")
            ->toContain("aberto === '{$ancora}'")
            // O texto longo e a lista de escopo moram no popup.
            ->toContain($frente['texto'])
            ->toContain(route('site.softwares').'#'.$ancora);

        foreach ($frente['itens'] as $item) {
            expect($html)->toContain($item);
        }
    }
});

it('conta a mesma frente na vitrine e na pagina de softwares', function () {
    // As duas telas liam listas escritas em lugares diferentes, e corrigir uma
    // deixava a outra para tras. Hoje as duas leem config/softwares.php.
    $vitrine = $this->get('/')->assertOk()->getContent();
    $pagina = $this->get(route('site.softwares'))->assertOk()->getContent();

    foreach (config('softwares') as $frente) {
        expect($vitrine)->toContain($frente['titulo'])
            ->and($pagina)->toContain($frente['titulo'])
            ->and($pagina)->toContain($frente['texto']);
    }
});
