<?php

use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Modulo Acesso
|--------------------------------------------------------------------------
|
| Porta unica de entrada: um formulario resolve as duas naturezas de conta.
| Ja as areas protegidas sao declaradas separadamente — nenhuma rota de gestao
| aceita o guard `empresa`, e nenhuma rota da empresa aceita `staff`.
|
*/

Route::middleware('guest:staff,empresa')->group(function () {
    Route::get('/entrar', [LoginController::class, 'mostrar'])->name('entrar');
    Route::post('/entrar', [LoginController::class, 'entrar'])->name('entrar.enviar');
});

Route::post('/sair', [LoginController::class, 'sair'])
    ->name('sair')
    ->middleware('auth:staff,empresa');

/*
|--------------------------------------------------------------------------
| Area de gestao (staff: admin e vendedor)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:staff', 'sessao:staff'])->group(function () {
    Route::view('/', 'paginas.painel')->name('painel');

    // Vitrine do TailAdmin, util enquanto os modulos vao sendo construidos:
    // mostra que componente ja existe pronto antes de eu escrever um novo.
    Route::prefix('kit')->name('kit.')->group(function () {
        Route::view('/', 'pages.dashboard.ecommerce')->name('painel');
        Route::view('/formularios', 'pages.form.form-elements')->name('formularios');
        Route::view('/tabelas', 'pages.tables.basic-tables')->name('tabelas');
        Route::view('/alertas', 'pages.ui-elements.alerts')->name('alertas');
        Route::view('/botoes', 'pages.ui-elements.buttons')->name('botoes');
        Route::view('/selos', 'pages.ui-elements.badges', ['title' => 'Badges'])->name('selos');
        Route::view('/grafico-linha', 'pages.chart.line-chart')->name('grafico-linha');
        Route::view('/grafico-barra', 'pages.chart.bar-chart')->name('grafico-barra');
    });
});

/*
|--------------------------------------------------------------------------
| Area da empresa (cliente contratante)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:empresa', 'sessao:empresa'])
    ->prefix('empresa')
    ->name('empresa.')
    ->group(function () {
        Route::view('/', 'paginas.empresa.painel')->name('painel');
    });
