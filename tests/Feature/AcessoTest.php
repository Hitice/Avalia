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
    // A raiz do dominio e o site da casa: quem chega sem sessao ve o que a
    // Avalia faz, com a porta de entrada a um clique. Cair direto no
    // formulario de senha dizia "isto nao e para voce" a quem estava
    // avaliando a empresa.
    $this->get('/')->assertOk()
        ->assertSee('Área do produtor')
        ->assertSee(route('area'));
});

it('manda visitante do painel para a tela de entrada', function () {
    $this->get('/painel')->assertRedirect(route('entrar'));
});

it('oferece a cada sessao o atalho para o proprio painel', function () {
    // O atalho mora na area do produtor, e nao na raiz: a raiz e o site da
    // empresa, e quem esta logado tambem precisa poder le-lo. Atalho, e nao
    // redirect, porque a mesma pessoa pode operar os dois negocios com contas
    // diferentes: mandar direto para um painel esconderia a outra entrada.
    $staff = Staff::factory()->admin()->create();
    $this->actingAs($staff, 'staff')->get(route('area'))
        ->assertOk()
        ->assertSee(route('painel'));

    $this->flushSession();
    app('auth')->forgetGuards();

    $cliente = Cliente::factory()->create();
    $this->actingAs($cliente, 'empresa')->get(route('area'))
        ->assertOk()
        ->assertSee(route('empresa.painel'));
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

it('sai e invalida a sessao, e volta para a porta do dominio', function () {
    // Home, e nao a tela de entrada: quem sai quase nunca quer entrar de novo
    // agora, e voltar ao login parece que a saida falhou.
    $staff = Staff::factory()->admin()->create();

    $this->actingAs($staff, 'staff')->post('/sair')->assertRedirect(route('inicio'));

    expect(auth('staff')->check())->toBeFalse();
});

it('deixa sair qualquer conta, inclusive a que o CRM nao conhece', function () {
    // Antes, com auth:staff,empresa na rota, o produtor que clicasse em Sair
    // era mandado para a tela de entrada do CRM e continuava logado.
    $produtor = App\Models\Produtor::create([
        'nome' => 'Escola Costa',
        'documento' => '12345678909',
        'whatsapp' => '34999112233',
        'email' => 'costa@escola.com.br',
        'situacao' => 'aprovado',
    ]);

    $this->actingAs($produtor, 'produtor')->post('/sair')->assertRedirect(route('inicio'));

    expect(auth('produtor')->check())->toBeFalse();
});

it('deixa sair quem ja nem sessao tem', function () {
    // Exigir sessao valida para sair cria o caso em que quem esta com a sessao
    // meio quebrada nao consegue se livrar dela.
    $this->post('/sair')->assertRedirect(route('inicio'));
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

it('poe o olho de mostrar em toda tela que pede senha', function () {
    // Senha digitada as cegas e erro de digitacao que so aparece na mensagem
    // de recusa, e quem toma "senha invalida" duas vezes acha que esqueceu a
    // senha. O componente existe para o bloco nao precisar ser copiado.
    $staff = Staff::factory()->admin()->create();

    $telas = [
        $this->get(route('entrar'))->getContent(),
        $this->get(route('digitais.index'))->getContent(),
        $this->actingAs($staff, 'staff')->withSession(['versao_staff' => $staff->sessao_versao])
            ->get(route('perfil'))->getContent(),
    ];

    foreach ($telas as $tela => $html) {
        expect(str_contains($html, 'x-data="{ visivel: false }"'))
            ->toBeTrue("a tela {$tela} não tem o olho de mostrar senha");
    }
});

it('nao deixa campo de senha sem o olho em nenhuma view', function () {
    // Campo escrito a mao passa batido por qualquer teste de tela: este varre
    // os arquivos e cobra o componente.
    $soltos = [];

    foreach (Illuminate\Support\Facades\File::allFiles(resource_path('views')) as $arquivo) {
        if (str_starts_with($arquivo->getRelativePathname(), 'mail')
            || $arquivo->getRelativePathname() === 'components/avalia/senha.blade.php') {
            continue;
        }

        if (str_contains($arquivo->getContents(), 'type="password"')) {
            $soltos[] = $arquivo->getRelativePathname();
        }
    }

    expect($soltos)->toBeEmpty('campo de senha sem x-avalia.senha: '.implode(', ', $soltos));
});
