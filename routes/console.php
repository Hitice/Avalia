<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Vencer e bloquear dependem da passagem do tempo, e não de alguém agir: sem
// esta rotina diária, uma fatura só mudaria de situação no dia em que alguém
// abrisse a tela.
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

Schedule::call(function () {
    $fechar = app(App\Actions\Financeiro\FecharCompetenciasVencidas::class);
    $fechar();
})
    ->name('financeiro:fechamento')
    ->dailyAt('00:15')
    ->withoutOverlapping();

/*
 * Retencao da resposta do bureau, 180 dias. Vencido o prazo, some o dado
 * pessoal de terceiro e fica o que explica a cobranca. Depende do tempo
 * passar, e nao de alguem lembrar.
 */
Schedule::call(function () {
    app(App\Actions\Consumo\ExpurgarRespostas::class)();
})
    ->name('consultas:expurgo')
    ->dailyAt('03:00')
    ->withoutOverlapping();
