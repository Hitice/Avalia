<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * A conferencia de ambiente so vale se ela reprovar.
 *
 * Erro de configuracao nao quebra nada: a tela abre, o login funciona, e a
 * aplicacao segue servindo com o depurador ligado. Um comando que aprova tudo da
 * a mesma sensacao de seguranca que nao ter comando nenhum, e por isso o que se
 * testa aqui e a recusa.
 */
it('reprova producao com o depurador ligado', function () {
    // O depurador imprime a stack, o trecho do arquivo e o ambiente na propria
    // tela de erro de quem estiver acessando.
    app()['env'] = 'production';
    config(['app.debug' => true, 'mail.default' => 'smtp', 'app.url' => 'https://avalia.com.br']);
    config(['session.secure' => true]);

    $this->artisan('avalia:ambiente')
        ->expectsOutputToContain('Depurador desligado')
        ->assertFailed();
});

it('reprova producao com cookie de sessao fora do HTTPS', function () {
    app()['env'] = 'production';
    config(['app.debug' => false, 'mail.default' => 'smtp', 'app.url' => 'https://avalia.com.br']);
    config(['session.secure' => false]);

    $this->artisan('avalia:ambiente')->assertFailed();
});

it('reprova producao sem envio de e-mail configurado', function () {
    // O driver `log` grava a mensagem em arquivo em vez de enviar. Recuperacao
    // de senha e aviso de vencimento nao saem, e nenhum erro aparece.
    app()['env'] = 'production';
    config(['app.debug' => false, 'app.url' => 'https://avalia.com.br', 'session.secure' => true]);
    config(['mail.default' => 'log']);

    $this->artisan('avalia:ambiente')->assertFailed();
});

it('reprova endereco que aponta para dentro de public', function () {
    // Raiz do servidor na pasta do projeto deixa .env e storage acessiveis.
    app()['env'] = 'production';
    config([
        'app.debug' => false, 'mail.default' => 'smtp', 'session.secure' => true,
        'app.url' => 'https://avalia.com.br/public',
    ]);

    $this->artisan('avalia:ambiente')->assertFailed();
});

it('aprova todo o resto quando so falta o banco certo', function () {
    // A suite nao tem Postgres, entao um ambiente de producao completo nao pode
    // ser montado aqui. O que da para provar, e e o que importa, e que com tudo
    // ajustado sobra exatamente um reprovado: o driver.
    app()['env'] = 'production';
    config([
        'app.debug' => false,
        'app.url' => 'https://avalia.com.br',
        'session.secure' => true,
        'session.http_only' => true,
        'session.same_site' => 'strict',
        'mail.default' => 'smtp',
        'queue.default' => 'database',
    ]);

    $this->artisan('avalia:ambiente')
        ->expectsOutputToContain('1 item(ns) impedem')
        ->assertFailed();
});

it('nao cobra em desenvolvimento o que so vale em producao', function () {
    // Depurador ligado na maquina de quem programa e o esperado. Reprovar aqui
    // faria a conferencia ser ignorada justamente onde ela deveria ser rodada.
    config(['app.debug' => true, 'mail.default' => 'log']);

    $this->artisan('avalia:ambiente')
        ->expectsOutputToContain('só em produção')
        ->assertSuccessful();
});

it('recusa exportar de um banco que nao seja postgres', function () {
    // A suite roda em SQLite. Exportar dali geraria um arquivo com a cara de um
    // backup e sem as sequencias, que so o Postgres tem.
    $this->artisan('avalia:exportar')->assertFailed();
});
