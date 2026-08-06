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

it('aceita driver de e-mail em arquivo enquanto nada e enviado', function () {
    // O driver `log` grava a mensagem em arquivo em vez de enviar. Isso so
    // esconde alguma coisa quando existe e-mail a esconder, e hoje a aplicacao
    // nao envia nenhum. Mesma leitura de intencao usada para a fila; o teste de
    // guarda logo abaixo derruba a suite no dia em que o primeiro import de
    // Mail aparecer, e ai este item volta a reprovar sozinho.
    app()['env'] = 'production';
    config(['app.debug' => false, 'app.url' => 'https://avalia.com.br', 'session.secure' => true]);
    config(['mail.default' => 'log']);

    $this->artisan('avalia:ambiente')->expectsOutputToContain('nada é enviado');
});

it('nao deixa passar despercebido o primeiro e-mail enviado', function () {
    // Guarda o pressuposto do teste acima, como o da fila.
    $comEnvio = [];

    $imports = ['use Illuminate\Support\Facades\\'.'Mail;', 'use Illuminate\Contracts\Mail\\'.'Mailable;'];
    $verificador = app_path('Console/Commands/ConferirAmbiente.php');

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path())) as $arquivo) {
        if ($arquivo->getExtension() !== 'php' || $arquivo->getPathname() === $verificador) {
            continue;
        }

        $conteudo = file_get_contents($arquivo->getPathname());

        foreach ($imports as $import) {
            if (str_contains($conteudo, $import)) {
                $comEnvio[] = $arquivo->getFilename();
            }
        }
    }

    expect($comEnvio)->toBeEmpty(
        'a aplicacao passou a enviar e-mail: '.implode(', ', $comEnvio).
        '. O driver "log" em producao deixou de ser aceitavel; configure um provedor de envio.',
    );
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

it('aceita fila sincrona enquanto a aplicacao nao enfileira nada', function () {
    // Em hospedagem compartilhada `sync` e a unica opcao: ela nao mantem
    // processo de pe. Como nenhuma acao da aplicacao vai para a fila, isso nao
    // custa nada, e reprovar aqui seria alarme sem defeito.
    app()['env'] = 'production';
    config([
        'app.debug' => false, 'app.url' => 'https://avalia.com.br',
        'session.secure' => true, 'mail.default' => 'smtp',
        'queue.default' => 'sync',
    ]);

    $this->artisan('avalia:ambiente')->expectsOutputToContain('nada é enfileirado');
});

it('nao deixa passar despercebido o dia em que algo for enfileirado', function () {
    // Guarda o pressuposto do teste acima. No momento em que a primeira classe
    // implementar ShouldQueue, a fila sincrona deixa de servir e a hospedagem
    // compartilhada tambem: este teste cai e obriga a decisao.
    $comFila = [];

    // O import, e nao a palavra: ela aparece no proprio comando que faz esta
    // varredura, que ficaria se acusando para sempre.
    $procurado = 'use Illuminate\Contracts\Queue\\'.'ShouldQueue;';
    $verificador = app_path('Console/Commands/ConferirAmbiente.php');

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path())) as $arquivo) {
        if ($arquivo->getExtension() !== 'php' || $arquivo->getPathname() === $verificador) {
            continue;
        }

        if (str_contains(file_get_contents($arquivo->getPathname()), $procurado)) {
            $comFila[] = $arquivo->getFilename();
        }
    }

    expect($comFila)->toBeEmpty(
        'passou a existir trabalho enfileirado: '.implode(', ', $comFila).
        '. A fila sincrona nao serve mais, e a hospedagem compartilhada tambem nao.',
    );
});

it('recusa exportar de um banco que nao seja postgres', function () {
    // A suite roda em SQLite. Exportar dali geraria um arquivo com a cara de um
    // backup e sem as sequencias, que so o Postgres tem.
    $this->artisan('avalia:exportar')->assertFailed();
});
