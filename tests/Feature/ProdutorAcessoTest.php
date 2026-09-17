<?php

use App\Http\Controllers\ProdutorAcessoController;
use App\Models\InteressadoCobranca;
use App\Models\Produtor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| A conta do produtor
|--------------------------------------------------------------------------
|
| O cadastro e auto-servico e curto: quem chega ainda esta decidindo, e pedir
| dados bancarios antes de mostrar o painel perde a pessoa na primeira tela.
| Quem entra aqui nasce pendente, e pendente ve o painel mas nao vende.
|
*/

function cadastro(array $ajustes = []): array
{
    return array_merge([
        'nome' => 'Marina Costa',
        'documento' => '123.456.789-09',
        'email' => 'marina@escola.com.br',
        'whatsapp' => '(34) 99911-2233',
        'senha' => 'senha-bem-grande',
        'senha_confirmation' => 'senha-bem-grande',
    ], $ajustes);
}

it('cria a conta e ja entra no painel', function () {
    $this->post(route('produtor.cadastrar'), cadastro())->assertRedirect(route('produtor.painel'));

    $produtor = Produtor::sole();

    expect($produtor->situacao)->toBe('pendente')
        ->and($produtor->documento)->toBe('12345678909')
        // Senha guardada com hash, nunca em claro.
        ->and($produtor->senha)->not->toBe('senha-bem-grande')
        ->and(Auth::guard('produtor')->id())->toBe($produtor->id);
});

it('mostra o painel com o aviso de analise para quem acabou de entrar', function () {
    $this->post(route('produtor.cadastrar'), cadastro());

    $this->get(route('produtor.painel'))->assertOk()
        ->assertSee('Cadastro em análise')
        ->assertSee('Nenhuma venda ainda');
});

it('liga o cadastro ao pre-cadastro que veio da pagina publica', function () {
    // E o que permite saber depois qual porta trouxe cada produtor.
    $interessado = InteressadoCobranca::create([
        'nome' => 'Marina Costa',
        'documento' => '12345678909',
        'email' => 'marina@escola.com.br',
        'whatsapp' => '34999112233',
        'ticket_medio_cents' => 250000,
        'volume_mensal' => 'Até R$ 10 mil',
    ]);

    $this->post(route('produtor.cadastrar'), cadastro());

    expect(Produtor::sole()->interessado_cobranca_id)->toBe($interessado->id);
});

it('recusa segundo cadastro com o mesmo e-mail', function () {
    $this->post(route('produtor.cadastrar'), cadastro());
    Auth::guard('produtor')->logout();

    $this->post(route('produtor.cadastrar'), cadastro(['documento' => '39.914.870/0001-01']))
        ->assertSessionHasErrors('email');

    expect(Produtor::count())->toBe(1);
});

it('exige senha confirmada e com tamanho', function () {
    $this->post(route('produtor.cadastrar'), cadastro(['senha' => 'curta', 'senha_confirmation' => 'curta']))
        ->assertSessionHasErrors('senha');

    $this->post(route('produtor.cadastrar'), cadastro(['senha_confirmation' => 'outra-coisa']))
        ->assertSessionHasErrors('senha');

    expect(Produtor::count())->toBe(0);
});

it('entra com a senha certa e recusa a errada', function () {
    $this->post(route('produtor.cadastrar'), cadastro());
    Auth::guard('produtor')->logout();

    $this->post(route('produtor.entrar.enviar'), ['email' => 'marina@escola.com.br', 'senha' => 'errada'])
        ->assertSessionHasErrors('email', null, ProdutorAcessoController::BAG);

    expect(Auth::guard('produtor')->check())->toBeFalse();

    $this->post(route('produtor.entrar.enviar'), ['email' => 'marina@escola.com.br', 'senha' => 'senha-bem-grande'])
        ->assertRedirect(route('produtor.painel'));

    expect(Auth::guard('produtor')->check())->toBeTrue();
});

it('responde igual para e-mail que nao existe', function () {
    // Mensagem diferente transformaria a tela de login numa consulta de quem e
    // produtor aqui.
    $this->post(route('produtor.cadastrar'), cadastro());
    Auth::guard('produtor')->logout();

    $comConta = $this->post(route('produtor.entrar.enviar'), ['email' => 'marina@escola.com.br', 'senha' => 'errada']);
    $semConta = $this->post(route('produtor.entrar.enviar'), ['email' => 'ninguem@lugar.com.br', 'senha' => 'errada']);

    $bag = ProdutorAcessoController::BAG;

    expect($comConta->getSession()->get('errors')->getBag($bag)->first('email'))
        ->toBe($semConta->getSession()->get('errors')->getBag($bag)->first('email'));
});

it('barra o produtor bloqueado', function () {
    $this->post(route('produtor.cadastrar'), cadastro());
    Produtor::sole()->update(['situacao' => 'bloqueado']);
    Auth::guard('produtor')->logout();

    $this->post(route('produtor.entrar.enviar'), ['email' => 'marina@escola.com.br', 'senha' => 'senha-bem-grande'])
        ->assertSessionHasErrors('email', null, ProdutorAcessoController::BAG);

    expect(Auth::guard('produtor')->check())->toBeFalse();
});

it('exige sessao para ver o painel', function () {
    $this->get(route('produtor.painel'))->assertRedirect();
});

it('manda o produtor deslogado para a porta dele, e nao para a do CRM', function () {
    // Caindo em /entrar, ele tentaria a senha que acabou de criar numa tela
    // que nunca vai aceita-la, e concluiria que o cadastro nao funcionou.
    $this->get(route('produtor.painel'))->assertRedirect(route('produtor.entrar'));
});

it('entra pela caixa que fica no alto da pagina do 360', function () {
    $this->post(route('produtor.cadastrar'), cadastro());
    Auth::guard('produtor')->logout();

    // A caixa de acesso segue o texto da entrada do CRM: quem usa os dois
    // lados da casa nao deveria reaprender a entrar.
    $this->get(route('cobranca'))->assertOk()
        ->assertSee('Bem-vindo de volta')
        ->assertSee('Esqueci minha senha')
        ->assertSee('Solicite seu cadastro.');

    $this->from(route('cobranca'))
        ->post(route('produtor.entrar.enviar'), ['email' => 'marina@escola.com.br', 'senha' => 'senha-bem-grande'])
        ->assertRedirect(route('produtor.painel'));
});

it('nao acende o erro do login no formulario de pre-cadastro', function () {
    // Os dois formularios da pagina tem campo `email`. Sem bags separadas,
    // errar a senha pintaria de vermelho o campo do pre-cadastro logo abaixo,
    // que a pessoa nem tocou.
    $resposta = $this->from(route('cobranca'))
        ->post(route('produtor.entrar.enviar'), ['email' => 'ninguem@lugar.com.br', 'senha' => 'errada']);

    $erros = $resposta->getSession()->get('errors');

    expect($erros->getBag(ProdutorAcessoController::BAG)->has('email'))->toBeTrue()
        ->and($erros->getBag('default')->has('email'))->toBeFalse();
});

it('recusa documento que nao fecha os digitos no cadastro', function () {
    // O documento saiu do formulario de contato, onde so afastava gente, e
    // passou a ser exigido aqui, onde a conta de verdade nasce.
    $this->post(route('produtor.cadastrar'), cadastro(['documento' => '111.111.111-11']))
        ->assertSessionHasErrors('documento');

    expect(Produtor::count())->toBe(0);
});

it('aceita CNPJ no lugar do CPF no cadastro', function () {
    $this->post(route('produtor.cadastrar'), cadastro(['documento' => '39.914.870/0001-01']));

    expect(Produtor::sole()->documento)->toBe('39914870000101');
});

it('leva a tela antiga de login de volta para a pagina do 360', function () {
    // A tela propria saiu: a caixa de acesso mora na pagina, e um endereco
    // separado so para os mesmos dois campos seria um clique a mais e um lugar
    // a mais para o texto divergir. O nome da rota continua, para os links
    // antigos e para o middleware.
    $this->get('/produtor/entrar')->assertRedirect(route('cobranca'));
});

it('deixa o produtor recuperar a senha pela mesma porta das outras contas', function () {
    // Sem isto, o link "esqueci minha senha" responderia "se este e-mail
    // estiver cadastrado" para um e-mail que esta, e ninguem receberia nada.
    $this->post(route('produtor.cadastrar'), cadastro());
    Auth::guard('produtor')->logout();

    $this->from(route('senha.esqueci'))
        ->post(route('senha.esqueci.enviar'), ['email' => 'marina@escola.com.br'])
        ->assertSessionHas('ok');
});
