<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// O webhook liquida na hora; esta rotina cobre vencimentos e bloqueios mesmo
// quando o provedor não envia nenhum novo evento depois do vencimento.
Schedule::call(function () {
    $atualizar = app(App\Actions\Financeiro\AtualizarInadimplencia::class);
    $atualizar();
})
    // O nome e obrigatorio para travar a sobreposicao: sem ele o Laravel nao
    // tem chave de cadeado e lanca LogicException ao montar o agendador, o que
    // derruba a aplicacao inteira e nao so a rotina.
    ->name('financeiro:inadimplencia')
    ->dailyAt('00:05')
    ->withoutOverlapping();
