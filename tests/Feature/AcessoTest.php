<?php

use App\Models\Cliente;
use App\Models\Staff;
use App\Services\ProtecaoLogin;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Entrada
|--------------------------------------------------------------------------
*/

it('deixa o staff entrar e cai no painel de gestao', function () {
    $staff = Staff::factory()->admin()->create(['email' => 'gestor@avalia.local']);

    $this->post('/entrar', ['email' => 'gestor@avalia.local', 'senha' => 'senha-valida-123'])
        ->assertRedirect(route('painel'));

    expect(auth('staff')->check())->toBeTrue()
        ->and(auth('empresa')->check())->toBeFalse();

    // O carimbo lido pelo ConfereSessao tem que existir desde a entrada.
    expect(session('versao_staff'))->toBe($staff->sessao_versao);
});

it('deixa a empresa entrar e cai na area dela', function () {
    Cliente::factory()->create(['email' => 'fin@lojas.com.br']);

    $this->post('/entrar', ['email' => 'fin@lojas.com.br', 'senha' => 'senha-valida-123'])
        ->assertRedirect(route('empresa.painel'));

    expect(auth('empresa')->check())->toBeTrue()
        ->and(auth('staff')->check())->toBeFalse();
});

it('registra o ultimo acesso', function () {
    $staff = Staff::factory()->create(['email' => 'v@avalia.local']);
    expect($staff->ultimo_acesso_em)->toBeNull();

    $this->post('/entrar', ['email' => 'v@avalia.local', 'senha' => 'senha-valida-123']);

    expect($staff->fresh()->ultimo_acesso_em)->not->toBeNull();
});

it('mantem conectado quando marca lembrar', function () {
    $staff = Staff::factory()->admin()->create(['email' => 'lembra@avalia.local']);

    $this->post('/entrar', [
        'email' => 'lembra@avalia.local',
        'senha' => 'senha-valida-123',
        'lembrar' => '1',
    ])->assertRedirect(route('painel'))->assertCookie(auth('staff')->getRecallerName());

    // Sem o token gravado o cookie de lembranca nao vale nada na volta.
    expect($staff->fresh()->remember_token)->not->toBeNull();
});

it('nao mantem conectado quando nao marca lembrar', function () {
    $cliente = Cliente::factory()->create(['email' => 'esquece@lojas.com.br']);

    $this->post('/entrar', ['email' => 'esquece@lojas.com.br', 'senha' => 'senha-valida-123'])
        ->assertRedirect(route('empresa.painel'));

    expect($cliente->fresh()->remember_token)->toBeNull();
});

it('nao revela se o e-mail existe', function () {
    Staff::factory()->create(['email' => 'existe@avalia.local']);

    $comEmailReal = $this->post('/entrar', ['email' => 'existe@avalia.local', 'senha' => 'errada']);
    $comEmailFalso = $this->post('/entrar', ['email' => 'naoexiste@avalia.local', 'senha' => 'errada']);

    expect($comEmailReal->getSession()->get('errors')->first('email'))
        ->toBe($comEmailFalso->getSession()->get('errors')->first('email'));
});

it('troca o id da sessao ao entrar', function () {
    Staff::factory()->create(['email' => 'fix@avalia.local']);

    $this->get('/entrar');
    $antes = session()->getId();

    $this->post('/entrar', ['email' => 'fix@avalia.local', 'senha' => 'senha-valida-123']);

    expect(session()->getId())->not->toBe($antes);
});

/*
|--------------------------------------------------------------------------
| Quem pode entrar
|--------------------------------------------------------------------------
*/

it('barra staff desativado mesmo com a senha certa', function () {
    Staff::factory()->inativo()->create(['email' => 'saiu@avalia.local']);

    $this->post('/entrar', ['email' => 'saiu@avalia.local', 'senha' => 'senha-valida-123'])
        ->assertSessionHasErrors('email');

    expect(auth('staff')->check())->toBeFalse();
});

it('barra cliente inativo', function () {
    Cliente::factory()->inativo()->create(['email' => 'encerrado@lojas.com.br']);

    $this->post('/entrar', ['email' => 'encerrado@lojas.com.br', 'senha' => 'senha-valida-123'])
        ->assertSessionHasErrors('email');

    expect(auth('empresa')->check())->toBeFalse();
});

it('deixa inadimplente entrar para poder regularizar, mas nao consultar', function () {
    $cliente = Cliente::factory()->inadimplente()->create(['email' => 'devendo@lojas.com.br']);

    $this->post('/entrar', ['email' => 'devendo@lojas.com.br', 'senha' => 'senha-valida-123'])
        ->assertRedirect(route('empresa.painel'));

    expect($cliente->podeEntrar())->toBeTrue()
        ->and($cliente->podeConsultar())->toBeFalse()
        ->and($cliente->motivoSuspensao())->toContain('fatura em aberto');
});

/*
|--------------------------------------------------------------------------
| Fronteira entre os guards
|--------------------------------------------------------------------------
*/

it('nao deixa empresa abrir a area de gestao', function () {
    $cliente = Cliente::factory()->create();

    $this->actingAs($cliente, 'empresa')->get('/painel')->assertRedirect(route('entrar'));
});

it('nao deixa staff abrir a area da empresa', function () {
    $staff = Staff::factory()->admin()->create();

    $this->actingAs($staff, 'staff')->get('/empresa')->assertRedirect(route('entrar'));
});

it('mostra a apresentacao ao visitante em vez do login', function () {
    // A raiz do dominio e a pagina publica: quem chega sem sessao ve o que a
    // Avalia faz, com o login a um clique. Cair direto no formulario de senha
    // dizia "isto nao e para voce" a quem estava avaliando o produto.
    $this->get('/')->assertOk()
        ->assertSee('Entrar')
        ->assertSee('Quero contratar');
});

it('manda visitante do painel para a tela de entrada', function () {
    $this->get('/painel')->assertRedirect(route('entrar'));
});

it('leva cada sessao da raiz para o proprio painel', function () {
    $staff = Staff::factory()->admin()->create();
    $this->actingAs($staff, 'staff')->get('/')->assertRedirect(route('painel'));

    $this->flushSession();
    app('auth')->forgetGuards();

    $cliente = Cliente::factory()->create();
    $this->actingAs($cliente, 'empresa')->get('/')->assertRedirect(route('empresa.painel'));
});

/*
|--------------------------------------------------------------------------
| Revogacao de sessao
|--------------------------------------------------------------------------
*/

it('derruba a sessao quando a versao muda', function () {
    $staff = Staff::factory()->admin()->create(['email' => 'rev@avalia.local']);

    $this->post('/entrar', ['email' => 'rev@avalia.local', 'senha' => 'senha-valida-123']);
    $this->get('/painel')->assertOk();

    $staff->revogaSessoes();

    $this->get('/painel')->assertRedirect(route('entrar'));
    expect(auth('staff')->check())->toBeFalse();
});

it('derruba a sessao quando a conta e desativada', function () {
    $staff = Staff::factory()->admin()->create(['email' => 'des@avalia.local']);

    $this->post('/entrar', ['email' => 'des@avalia.local', 'senha' => 'senha-valida-123']);
    $this->get('/painel')->assertOk();

    $staff->update(['ativo' => false]);

    $this->get('/painel')->assertRedirect(route('entrar'));
});

it('sai e invalida a sessao', function () {
    $staff = Staff::factory()->admin()->create();

    $this->actingAs($staff, 'staff')->post('/sair')->assertRedirect(route('entrar'));

    expect(auth('staff')->check())->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Protecao contra forca bruta
|--------------------------------------------------------------------------
*/

it('tolera as primeiras falhas e depois castiga', function () {
    Staff::factory()->create(['email' => 'alvo@avalia.local']);
    $protecao = app(ProtecaoLogin::class);
    $req = request();

    for ($i = 0; $i < ProtecaoLogin::LIMITE; $i++) {
        $protecao->falhou('alvo@avalia.local', $req);
    }
    expect($protecao->bloqueadoPor('alvo@avalia.local', $req))->toBeNull();

    $protecao->falhou('alvo@avalia.local', $req);
    expect($protecao->bloqueadoPor('alvo@avalia.local', $req))->not->toBeNull();
});

it('dobra o castigo a cada falha, ate o teto', function () {
    $protecao = app(ProtecaoLogin::class);
    $req = request();

    for ($i = 0; $i < ProtecaoLogin::LIMITE + 1; $i++) {
        $protecao->falhou('escada@avalia.local', $req);
    }
    $primeiro = $protecao->bloqueadoPor('escada@avalia.local', $req);

    $protecao->falhou('escada@avalia.local', $req);
    $segundo = $protecao->bloqueadoPor('escada@avalia.local', $req);

    expect($segundo)->toBeGreaterThan($primeiro);

    for ($i = 0; $i < 20; $i++) {
        $protecao->falhou('escada@avalia.local', $req);
    }
    expect($protecao->bloqueadoPor('escada@avalia.local', $req))
        ->toBeLessThanOrEqual(ProtecaoLogin::TETO_SEGUNDOS);
});

it('barra o login enquanto esta de castigo, mesmo com a senha certa', function () {
    Staff::factory()->create(['email' => 'castigo@avalia.local']);
    $protecao = app(ProtecaoLogin::class);

    for ($i = 0; $i < ProtecaoLogin::LIMITE + 1; $i++) {
        $protecao->falhou('castigo@avalia.local', request());
    }

    $this->post('/entrar', ['email' => 'castigo@avalia.local', 'senha' => 'senha-valida-123'])
        ->assertSessionHasErrors('email');

    expect(auth('staff')->check())->toBeFalse();
});

it('limpa o castigo quando o login acerta', function () {
    Staff::factory()->create(['email' => 'limpa@avalia.local']);
    $protecao = app(ProtecaoLogin::class);

    $protecao->falhou('limpa@avalia.local', request());
    expect($protecao->falhasDaConta('limpa@avalia.local'))->toBe(1);

    $this->post('/entrar', ['email' => 'limpa@avalia.local', 'senha' => 'senha-valida-123']);

    expect($protecao->falhasDaConta('limpa@avalia.local'))->toBe(0);
});

it('conta a origem separado da conta', function () {
    $protecao = app(ProtecaoLogin::class);

    // Varredura: muitas contas diferentes a partir da mesma origem.
    for ($i = 0; $i <= ProtecaoLogin::LIMITE; $i++) {
        $protecao->falhou("vitima{$i}@avalia.local", request());
    }

    // Conta nunca tentada, mas a origem ja esta queimada.
    expect($protecao->bloqueadoPor('intocada@avalia.local', request()))->not->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Senha
|--------------------------------------------------------------------------
*/

it('nunca guarda a senha em texto puro', function () {
    $staff = Staff::factory()->create(['senha' => 'senha-valida-123']);

    expect($staff->senha)->not->toBe('senha-valida-123')
        ->and($staff->senha)->toStartWith('$2y$');
});

it('esconde senha e versao da sessao ao serializar', function () {
    $json = Staff::factory()->create()->toArray();

    expect($json)->not->toHaveKey('senha')
        ->and($json)->not->toHaveKey('sessao_versao');
});

/*
|--------------------------------------------------------------------------
| Volta pelo cookie de lembranca
|--------------------------------------------------------------------------
*/

/** Simula o retorno com a sessao expirada e so o cookie de lembranca na mao. */
function voltaComLembranca(App\Contracts\ContaAutenticavel $conta, string $guarda): Illuminate\Testing\TestResponse
{
    test()->flushSession();
    app('auth')->forgetGuards();

    $conta = $conta->fresh();

    return test()->withCookie(
        auth($guarda)->getRecallerName(),
        $conta->getKey().'|'.$conta->getRememberToken().'|'.$conta->getAuthPassword(),
    )->get($guarda === 'staff' ? '/painel' : '/empresa');
}

it('volta a entrar pelo cookie depois de a sessao expirar', function () {
    // Sem isto o operador perde o POST que estava enviando: o middleware
    // derruba a lembranca antes de a requisicao chegar ao controller.
    $staff = Staff::factory()->admin()->create(['email' => 'volta@avalia.local']);

    $this->post('/entrar', [
        'email' => 'volta@avalia.local',
        'senha' => 'senha-valida-123',
        'lembrar' => '1',
    ])->assertRedirect(route('painel'));

    voltaComLembranca($staff, 'staff')->assertOk();

    expect(auth('staff')->check())->toBeTrue();
});

it('recarimba a sessao ao voltar pelo cookie', function () {
    $staff = Staff::factory()->admin()->create(['email' => 'carimbo@avalia.local']);

    $this->post('/entrar', [
        'email' => 'carimbo@avalia.local',
        'senha' => 'senha-valida-123',
        'lembrar' => '1',
    ]);

    voltaComLembranca($staff, 'staff')->assertOk();

    // O carimbo tem que ficar gravado, senao a proxima requisicao cai de novo.
    expect(session('versao_staff'))->toBe($staff->fresh()->sessao_versao);
});

it('a empresa tambem volta pelo cookie', function () {
    $cliente = Cliente::factory()->create(['email' => 'volta@lojas.com.br']);

    $this->post('/entrar', [
        'email' => 'volta@lojas.com.br',
        'senha' => 'senha-valida-123',
        'lembrar' => '1',
    ])->assertRedirect(route('empresa.painel'));

    voltaComLembranca($cliente, 'empresa')->assertOk();
});

it('revogar acesso invalida tambem o cookie de lembranca', function () {
    // Revogacao que deixa o cookie valendo e revogacao so no nome.
    $staff = Staff::factory()->admin()->create(['email' => 'revoga@avalia.local']);

    $this->post('/entrar', [
        'email' => 'revoga@avalia.local',
        'senha' => 'senha-valida-123',
        'lembrar' => '1',
    ]);

    $staff->refresh();
    $cookie = $staff->getKey().'|'.$staff->getRememberToken().'|'.$staff->getAuthPassword();

    $staff->revogaSessoes();

    expect($staff->fresh()->remember_token)->toBeNull();

    $this->flushSession();
    app('auth')->forgetGuards();

    $this->withCookie(auth('staff')->getRecallerName(), $cookie)
        ->get('/painel')
        ->assertRedirect(route('entrar'));
});
