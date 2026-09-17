<?php

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
        ->assertSessionHasErrors('email');

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

    expect($comConta->getSession()->get('errors')->first('email'))
        ->toBe($semConta->getSession()->get('errors')->first('email'));
});

it('barra o produtor bloqueado', function () {
    $this->post(route('produtor.cadastrar'), cadastro());
    Produtor::sole()->update(['situacao' => 'bloqueado']);
    Auth::guard('produtor')->logout();

    $this->post(route('produtor.entrar.enviar'), ['email' => 'marina@escola.com.br', 'senha' => 'senha-bem-grande'])
        ->assertSessionHasErrors('email');

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
