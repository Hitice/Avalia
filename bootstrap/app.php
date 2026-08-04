<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'sessao' => App\Http\Middleware\ConfereSessao::class,
            'admin' => App\Http\Middleware\SomenteAdmin::class,
        ]);

        // Visitante sem sessao vai para /entrar, e nao para a rota 'login'
        // que o Laravel assume por padrao e que aqui nao existe.
        $middleware->redirectGuestsTo(fn () => route('entrar'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
