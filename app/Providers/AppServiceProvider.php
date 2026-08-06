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
        $this->app->bind(\App\Contracts\ConectorBureau::class, function () {
            $conectores = [
                'simulado' => \App\Services\Conectores\ConectorSimulado::class,
            ];

            $escolhido = (string) config('services.bureau.conector', 'simulado');

            return $this->app->make($conectores[$escolhido] ?? $conectores['simulado']);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
