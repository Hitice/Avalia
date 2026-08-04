<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

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
        /*
         * Token CSRF expirado devolve uma tela "Page Expired" que nao diz nada
         * a quem esta tentando entrar. Acontece na situacao mais banal: deixar
         * a tela de login aberta por mais tempo que SESSION_LIFETIME e so
         * entao preencher.
         *
         * Em vez do erro cru, manda de volta ao formulario explicando. Nao e
         * cosmetico: sem explicacao o operador tenta de novo no mesmo
         * formulario velho e toma 419 outra vez.
         */
        $expirou = function (Request $request) {
            $mensagem = 'A pagina ficou aberta tempo demais e o formulario expirou. Tente de novo.';

            $logado = Auth::guard('staff')->check() || Auth::guard('empresa')->check();

            return $logado
                ? back()->withInput($request->except(['senha', '_token']))->with('erro', $mensagem)
                : redirect()->route('entrar')->with('erro', $mensagem);
        };

        // O Laravel converte TokenMismatchException em HttpException 419 antes
        // de consultar os renderizadores, entao o gancho util e o status, nao o
        // tipo original nunca chega aqui. Os dois ficam registrados para o dia
        // em que essa ordem mudar.
        $exceptions->render(fn (TokenMismatchException $e, Request $request) => $expirou($request));

        $exceptions->render(fn (HttpExceptionInterface $e, Request $request) => $e->getStatusCode() === 419
            ? $expirou($request)
            : null);
    })->create();
