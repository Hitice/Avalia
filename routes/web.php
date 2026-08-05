<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CatalogoController;
use App\Http\Controllers\PlanilhaController;
use App\Http\Controllers\PlanoController;
use App\Http\Controllers\ServicoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Modulo Acesso
|--------------------------------------------------------------------------
|
| Porta unica de entrada: um formulario resolve as duas naturezas de conta.
| Ja as areas protegidas sao declaradas separadamente: nenhuma rota de gestao
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
        // A porta do modulo e a lista de planos: e o que a operacao usa todo
        // dia. Versao de catalogo se mexe uma vez por reajuste.
        Route::get('/', [PlanoController::class, 'index'])->name('index');

        Route::prefix('planos')->name('planos.')->group(function () {
            Route::get('/novo', [PlanoController::class, 'criar'])->name('criar');
            Route::post('/', [PlanoController::class, 'salvar'])->name('salvar');
            Route::get('/{plano}', [PlanoController::class, 'editar'])->name('editar');
            Route::put('/{plano}', [PlanoController::class, 'atualizar'])->name('atualizar');
            Route::put('/{plano}/franquias', [PlanoController::class, 'franquias'])->name('franquias');
        });

        // A tabela de precos abre direto: e um catalogo so, sem lista de
        // versoes no meio do caminho.
        Route::get('/tabela', [CatalogoController::class, 'tabela'])->name('tabela');
        Route::put('/tabela/{catalogo}/precos', [CatalogoController::class, 'precos'])->name('precos');
        Route::put('/tabela/{catalogo}/custos', [CatalogoController::class, 'custos'])->name('custos');
        Route::put('/tabela/{catalogo}/parametros', [CatalogoController::class, 'parametros'])->name('parametros');
        Route::post('/tabela/{catalogo}/precificar', [CatalogoController::class, 'precificar'])->name('precificar');
        Route::post('/tabela/{catalogo}/reajustar', [CatalogoController::class, 'reajustar'])->name('reajustar');

        // Modulo inteiro numa planilha de tres abas, e de volta.
        Route::get('/planilha', [PlanilhaController::class, 'exportar'])->name('planilha.exportar');
        Route::post('/planilha', [PlanilhaController::class, 'importar'])->name('planilha.importar');

        Route::prefix('servicos')->name('servicos.')->group(function () {
            Route::get('/', [ServicoController::class, 'index'])->name('index');
            Route::get('/novo', [ServicoController::class, 'criar'])->name('criar');
            Route::post('/', [ServicoController::class, 'salvar'])->name('salvar');
            Route::get('/{servico}', [ServicoController::class, 'editar'])->name('editar');
            Route::put('/{servico}', [ServicoController::class, 'atualizar'])->name('atualizar');
        });
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
