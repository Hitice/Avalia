<?php

use App\Http\Controllers\AreaClienteController;
use App\Http\Controllers\AuditoriaController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CalculadoraController;
use App\Http\Controllers\CatalogoController;
use App\Http\Controllers\EmpresaController;
use App\Http\Controllers\FinanceiroController;
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

    // Empresas contratantes e o consumo delas. Consultas sao registradas pelas
    // integrações com os fornecedores, nunca manualmente pela gestão.
    Route::middleware('admin')->prefix('empresas')->name('empresas.')->group(function () {
        Route::get('/', [EmpresaController::class, 'index'])->name('index');
        Route::get('/nova', [EmpresaController::class, 'criar'])->name('criar');
        Route::post('/', [EmpresaController::class, 'salvar'])->name('salvar');
        Route::get('/{empresa}', [EmpresaController::class, 'ficha'])->name('ficha');
        Route::get('/{empresa}/editar', [EmpresaController::class, 'editar'])->name('editar');
        Route::put('/{empresa}', [EmpresaController::class, 'atualizar'])->name('atualizar');
        Route::post('/{empresa}/fechar', [EmpresaController::class, 'fechar'])->name('fechar');
    });

    // As faturas de todas as empresas. A baixa registrada aqui e a mesma que o
    // provedor de cobranca dispara por webhook: uma acao so, para baixa manual
    // e automatica nao divergirem.
    Route::middleware('admin')->prefix('financeiro')->name('financeiro.')->group(function () {
        Route::get('/', [FinanceiroController::class, 'index'])->name('index');
        Route::post('/{fatura}/liquidar', [FinanceiroController::class, 'liquidar'])->name('liquidar');
    });

    // Trilha de auditoria, so leitura: trilha que a tela edita nao e trilha.
    Route::get('/auditoria', AuditoriaController::class)->middleware('admin')->name('auditoria');

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

        // Simulador de contrato. GET e sem gravar nada: o endereco carrega o
        // cenario, entao a simulacao vira link em vez de captura de tela.
        Route::get('/calculadora', CalculadoraController::class)->name('calculadora');

        // Parametros comerciais em pagina propria: mexem no catalogo inteiro e
        // nao tem lugar no meio da matriz que se consulta todo dia.
        Route::get('/parametros', [CatalogoController::class, 'parametros'])->name('parametros');
        Route::put('/parametros/{catalogo}', [CatalogoController::class, 'salvarParametros'])->name('parametros.salvar');

        // Modulo inteiro numa planilha de tres abas, e de volta.
        Route::get('/planilha', [PlanilhaController::class, 'exportar'])->name('planilha.exportar');
        Route::post('/planilha', [PlanilhaController::class, 'importar'])->name('planilha.importar');

        Route::prefix('servicos')->name('servicos.')->group(function () {
            Route::get('/', [ServicoController::class, 'index'])->name('index');
            Route::get('/novo', [ServicoController::class, 'criar'])->name('criar');
            Route::post('/', [ServicoController::class, 'salvar'])->name('salvar');
            Route::get('/{servico}', [ServicoController::class, 'editar'])->name('editar');
            Route::patch('/{servico}/situacao', [ServicoController::class, 'alternar'])->name('alternar');
            Route::put('/{servico}', [ServicoController::class, 'atualizar'])->name('atualizar');
        });
    });
});

/*
|--------------------------------------------------------------------------
| Área do cliente
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:empresa', 'sessao:empresa'])
    ->prefix('empresa')
    ->name('empresa.')
    ->group(function () {
        Route::get('/', [AreaClienteController::class, 'painel'])->name('painel');
        Route::post('/documentos/{documento}/aceite', [AreaClienteController::class, 'aceitar'])->name('documentos.aceitar');
    });
