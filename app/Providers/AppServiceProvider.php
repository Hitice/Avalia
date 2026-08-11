<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        /*
         * Quem atende as consultas.
         *
         * Sem conector real configurado, vale o simulado: assim a cadeia
         * inteira, do clique do cliente ate a fatura, roda em homologacao antes
         * de existir contrato e credencial.
         *
         * A escolha e por configuracao e nao por ambiente. Amarrar ao ambiente
         * faria producao decidir sozinha usar dado falso no dia em que a
         * credencial faltasse, e ninguem perceberia.
         */
        $this->app->bind(\App\Contracts\ConectorBureau::class, fn () => app(
            \App\Services\Conectores\EscolherConector::CONECTORES[
                \App\Services\Conectores\EscolherConector::global()
            ] ?? \App\Services\Conectores\ConectorSimulado::class,
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
