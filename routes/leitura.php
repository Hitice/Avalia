<?php

use App\Http\Controllers\RedirecionadorController;
use App\Support\CodigoCurto;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Leitura de plaquinha
|--------------------------------------------------------------------------
|
| Fica fora de `routes/web.php` por um motivo de custo, e nao de organizacao.
|
| Toda rota do grupo `web` inicia sessao, e iniciar sessao GRAVA um arquivo de
| sessao a cada requisicao. Isto aqui responde a um desconhecido encostando o
| celular numa placa: nao ha nada para lembrar dele entre uma leitura e outra,
| e criar um arquivo por freguês que passou na frente de uma loja encheria o
| disco da hospedagem compartilhada com lixo que ninguem le.
|
| Sem sessao, sem cookie e sem CSRF, sobra o essencial: achar o codigo e
| responder. Os middlewares globais (cabecalhos de seguranca, manutencao)
| continuam valendo, porque valem para tudo.
|
| O endereco e o contrato mais duradouro do sistema. Plaquinha impressa hoje
| precisa abrir em 2036: nada aqui muda de forma, nunca.
|
*/

Route::get('/q/{codigo}', RedirecionadorController::class)
    // Larga de proposito, e apertada depois por CodigoCurto::normalizar. Quem
    // digita o codigo a mao troca 1 por I e 0 por O, e a rota precisa deixar
    // entrar para a normalizacao poder consertar em vez de devolver 404.
    ->where('codigo', CodigoCurto::REGEX_ROTA)
    ->name('q');

/*
 * A MESMA rota, com o Q maiusculo.
 *
 * Nao e redundancia: o QR grava a URL inteira em maiusculas para caber no modo
 * alfanumerico, que e bem mais compacto que o modo byte, e o navegador so
 * normaliza esquema e dominio antes de pedir. O CAMINHO chega como esta
 * impresso, `/Q/K7M2PX`, e o casamento de rota do Laravel diferencia
 * maiuscula de minuscula.
 *
 * Sem esta linha, toda plaquinha impressa devolveria 404 ao ser lida, e a
 * descoberta viria pela primeira caixa de acrilico ja cortada.
 *
 * Tem nome, apesar de ninguem a gerar: rota sem nome fica de fora da conferencia
 * que exige auth em tudo, e porta publica que escapa da conferencia e porta
 * publica que ninguem revisou. Quem monta endereco usa a rota `q` e sobe para
 * maiusculo depois.
 */
Route::get('/Q/{codigo}', RedirecionadorController::class)
    ->where('codigo', CodigoCurto::REGEX_ROTA)
    ->name('q.maiusculo');
