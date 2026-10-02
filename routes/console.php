<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Batida do cron: prova viva de que o agendador esta rodando.
 *
 * O cron falha em silencio absoluto: nada quebra, nada aparece, so deixa de
 * acontecer. Este arquivo com carimbo e o jeito de qualquer diagnostico
 * responder na hora "o cron esta vivo?" olhando uma linha.
 *
 * De 5 em 5 minutos, e nao de minuto em minuto. Medido em 01/10/2026: o
 * `schedule:run` por minuto deixava o site em 503 por 4 a 6 segundos a cada
 * minuto, sempre entre os segundos 05 e 13. O boot do PHP em CLI consome o
 * limite de processo da hospedagem compartilhada, e o LiteSpeed recusa a
 * requisicao do visitante enquanto isso. Nenhuma tarefa daqui precisa de
 * resolucao de um minuto, e todas as diarias caem em minuto multiplo de 5.
 */
Schedule::call(function () {
    file_put_contents(storage_path('logs/cron-batida.txt'), now()->toDateTimeString());
})
    ->name('cron:batida')
    ->everyFiveMinutes();

/*
 * Aquecimento do servidor web.
 *
 * Na hospedagem compartilhada o LiteSpeed derruba o processo PHP depois de
 * minutos ocioso, e o primeiro clique seguinte paga 3 a 5 segundos de
 * subida; era o "logout lento" relatado, que a medicao quente nao reproduzia
 * (tudo abaixo de 0,7 s). O toque de 5 em 5 minutos mantem o processo de pe.
 * Falha aqui e silencio: aquecimento nao pode virar alerta.
 */
Schedule::call(function () {
    try {
        // 3s e nao 10: enquanto espera, o processo do cron ocupa um slot e a
        // propria requisicao precisa de outro. Se o site nao responde em 3s,
        // insistir so rouba lugar de quem esta na loja lendo uma placa.
        \Illuminate\Support\Facades\Http::timeout(3)->get(config('app.url'));
    } catch (\Throwable) {
        // Sem log: o proximo toque tenta de novo.
    }
})
    ->name('site:aquecer')
    ->everyFiveMinutes();

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

// Lembrete de vencimento, ate 3 dias antes do dia 10. As 09:00 e nao de
// madrugada: e-mail de dinheiro chegando junto com o expediente e lido;
// chegando as 03:00, ja esta soterrado quando o dia comeca.
Schedule::call(function () {
    app(App\Actions\Financeiro\AvisarVencimentoProximo::class)();
})
    ->name('financeiro:aviso-vencimento')
    ->dailyAt('09:00')
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

/*
 * Conferencia diaria de integridade.
 *
 * Divergencia entre consulta, fatura e cobranca e silenciosa: nada quebra e
 * nenhuma tela mostra erro. Sem esta rotina, o problema aparece semanas depois
 * no extrato de alguem.
 */
Schedule::command('avalia:conferir')
    ->name('financeiro:conferencia')
    ->dailyAt('04:00')
    ->withoutOverlapping();

/*
 * Copia diaria do banco.
 *
 * Em servidor proprio o backup deixa de ser do provedor e passa a ser nosso.
 * Roda as 02:00, antes do expurgo das 03:00: assim a copia do dia ainda tem a
 * resposta que o expurgo vai apagar, e um pedido de titular respondido tarde
 * ainda encontra o dado.
 *
 * A copia fica no disco e TAMBEM sai por e-mail comprimida (--enviar): a
 * caixa do dominio e o destino externo, fora desta maquina. Cobre engano
 * humano e perda do servidor.
 */
Schedule::command('avalia:exportar --enviar')
    ->name('banco:copia')
    ->dailyAt('02:00')
    ->withoutOverlapping();
