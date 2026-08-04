<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CatalogoController;
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

    // Catalogo mostra preco de venda, custo do fornecedor e margem. Vendedor
    // nao entra: `admin` fecha a porta alem do `auth:staff` do grupo.
    Route::middleware('admin')->prefix('catalogo')->name('catalogo.')->group(function () {
        Route::get('/', [CatalogoController::class, 'index'])->name('index');
        Route::get('/{versao}', [CatalogoController::class, 'versao'])->name('versao');
        Route::post('/{versao}/ativar', [CatalogoController::class, 'ativar'])->name('ativar');
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
