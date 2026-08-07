<?php

use App\Http\Controllers\AreaClienteController;
use App\Http\Controllers\AuditoriaController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RecuperacaoController;
use App\Http\Controllers\Auth\SenhaController;
use App\Http\Controllers\CalculadoraController;
use App\Http\Controllers\CampanhaController;
use App\Http\Controllers\CarteiraController;
use App\Http\Controllers\CatalogoController;
use App\Http\Controllers\ConexaoController;
use App\Http\Controllers\ConsultaController;
use App\Http\Controllers\DocumentoController;
use App\Http\Controllers\EmpresaController;
use App\Http\Controllers\EquipeController;
use App\Http\Controllers\FinanceiroController;
use App\Http\Controllers\InicioController;
use App\Http\Controllers\InteresseController;
use App\Http\Controllers\PainelController;
use App\Http\Controllers\PlanilhaController;
use App\Http\Controllers\PlanoController;
use App\Http\Controllers\ServicoController;
use App\Http\Controllers\WebhookAsaasController;
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

/*
 * A porta do dominio e uma apresentacao, nao o login.
 *
 * Quem digita o endereco sem estar logado e quase sempre alguem avaliando o
 * produto: cliente indicado, contador do cliente, curioso. Cair direto num
 * formulario de senha diz "isto nao e para voce". A pagina inicial diz o que a
 * Avalia faz e para onde ir. Quem ja tem sessao nem a ve: segue para o proprio
 * painel.
 */
Route::get('/', InicioController::class)->name('inicio');

// Pedido de contato da campanha. Teto por origem apertado: e o unico POST
// publico que grava no banco, e formulario aberto e ima de robo.
Route::post('/interesse', [InteresseController::class, 'salvar'])
    ->middleware('throttle:10,1')
    ->name('interesse.salvar');

Route::middleware('guest:staff,empresa')->group(function () {
    Route::get('/entrar', [LoginController::class, 'mostrar'])->name('entrar');

    // Esqueci minha senha: reusa o convite de acesso. Resposta identica exista
    // ou nao a conta, para nao virar verificador de quem e cliente.
    Route::get('/esqueci', [RecuperacaoController::class, 'mostrar'])->name('senha.esqueci');
    Route::post('/esqueci', [RecuperacaoController::class, 'enviar'])
        ->middleware('throttle:5,1')
        ->name('senha.esqueci.enviar');
    // O bloqueio progressivo por conta ja existe; este teto e por origem, e
    // fecha a tentativa em massa contra muitas contas diferentes.
    Route::post('/entrar', [LoginController::class, 'entrar'])
        ->middleware('throttle:20,1')
        ->name('entrar.enviar');
});

/*
 * Token atual da sessao, para o formulario de entrada se renovar sozinho.
 *
 * Nao vaza nada: o token ja esta no HTML da propria pagina que chama isto, e o
 * navegador impede que outra origem leia a resposta. O ganho e que a aba
 * esquecida aberta para de expirar antes de ser usada, porque cada chamada
 * renova o token E toca a sessao.
 */
Route::get('/token', fn () => response()->json(['token' => csrf_token()]))
    ->middleware('throttle:60,1')
    ->name('token');

/*
 * Definicao de senha pelo link do convite. Publica porque quem chega ainda nao
 * tem como se autenticar; fechada pela assinatura temporaria (link forjado ou
 * vencido nem chega ao controller) e pelo carimbo, que mata o link usado.
 */
Route::middleware(['signed', 'throttle:10,1'])->group(function () {
    Route::get('/acesso/senha/{guarda}/{id}', [SenhaController::class, 'mostrar'])->name('senha.definir');
    Route::post('/acesso/senha/{guarda}/{id}', [SenhaController::class, 'salvar'])->name('senha.salvar');
});

Route::post('/sair', [LoginController::class, 'sair'])
    ->name('sair')
    ->middleware('auth:staff,empresa');

Route::post('/webhooks/asaas', WebhookAsaasController::class)
    ->middleware('throttle:120,1')
    ->name('webhooks.asaas');

/*
|--------------------------------------------------------------------------
| Area de gestao (staff: admin e vendedor)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:staff', 'sessao:staff'])->group(function () {
    // Em /painel, e nao em /: a raiz e a pagina publica de apresentacao, e o
    // nome de rota continua o mesmo, entao nenhum redirect mudou.
    Route::get('/painel', PainelController::class)->name('painel');

    // Empresas contratantes e o consumo delas. Consultas sao registradas pelas
    // integrações com os fornecedores, nunca manualmente pela gestão.
    Route::prefix('empresas')->name('empresas.')->group(function () {
        // Cadastro aberto ao vendedor: quem fecha a venda tem os dados na mao.
        // O controller forca a carteira e preserva a situacao, entao ele nao
        // pega cliente de outro nem desfaz bloqueio por inadimplencia.
        Route::get('/nova', [EmpresaController::class, 'criar'])->name('criar');
        Route::post('/', [EmpresaController::class, 'salvar'])->name('salvar');
        Route::get('/{empresa}/editar', [EmpresaController::class, 'editar'])->name('editar');
        Route::put('/{empresa}', [EmpresaController::class, 'atualizar'])->name('atualizar');
        // Redefinicao de senha por e-mail, respeitando a carteira: o vendedor
        // so reenvia para empresa que e dele.
        Route::post('/{empresa}/convite', [EmpresaController::class, 'convite'])->name('convite');

        // Remover e tirar de circulacao, nao apagar: consulta, fatura e trilha
        // apontam para a empresa. A administracao continua vendo e restaura.
        Route::delete('/{empresa}', [EmpresaController::class, 'remover'])->name('remover');

        // Lista e ficha mostram custo, imposto e lucro por fatura.
        Route::middleware('admin')->group(function () {
            Route::get('/', [EmpresaController::class, 'index'])->name('index');
            Route::get('/{empresa}', [EmpresaController::class, 'ficha'])->name('ficha');
            // Fechar competencia emite cobranca: e decisao financeira.
            Route::post('/{empresa}/fechar', [EmpresaController::class, 'fechar'])
                ->middleware('financeiro')->name('fechar');
            Route::post('/{empresa}/restaurar', [EmpresaController::class, 'restaurar'])->name('restaurar');
            // Definitivo so para removida sem historico: cadastro de teste.
            Route::delete('/{empresa}/excluir', [EmpresaController::class, 'excluir'])->name('excluir');

            // Operadores: as pessoas que consultam em nome da empresa, cada
            // uma com conta propria. So a administracao cria e desativa.
            Route::post('/{empresa}/operadores', [EmpresaController::class, 'operadorSalvar'])->name('operadores.salvar');
            Route::post('/{empresa}/operadores/{operador}/convite', [EmpresaController::class, 'operadorConvite'])->name('operadores.convite');
            Route::post('/{empresa}/operadores/{operador}/alternar', [EmpresaController::class, 'operadorAlternar'])->name('operadores.alternar');
        });
    });

    // Consultas de todas as empresas, para a operacao acompanhar volume e
    // resposta do fornecedor. So leitura, e so admin: a lista do vendedor e
    // /carteira/consultas, restrita as empresas dele.
    Route::get('/consultas', ConsultaController::class)->middleware('admin')->name('consultas');

    // A fila de pedidos de contato vive no painel da administracao; atender e
    // tirar da fila, e a trilha guarda quem atendeu.
    Route::post('/interessados/{interessado}/atendido', [InteresseController::class, 'atender'])
        ->middleware('admin')
        ->name('interessados.atendido');

    // A carteira do vendedor. Sem `admin` no meio: sao as telas de gestao que
    // ele abre, e nenhuma mostra custo, lucro nem margem. A carteira e sempre a
    // de quem esta logado, entao nao ha URL que peca a de outro.
    Route::prefix('carteira')->group(function () {
        Route::get('/', [CarteiraController::class, 'index'])->name('carteira');
        Route::get('/consultas', [CarteiraController::class, 'consultas'])->name('carteira.consultas');

        // Demonstracao: a consulta que fecha venda, no ambiente do vendedor.
        // Preco zero, custo descontado da comissao, teto diario proprio.
        Route::get('/consultar', [CarteiraController::class, 'consultar'])->name('carteira.consultar');
        Route::post('/consultar', [CarteiraController::class, 'executarDemonstracao'])
            ->middleware('throttle:15,1')
            ->name('carteira.consultar.executar');
        Route::get('/demonstracoes/{consulta}', [CarteiraController::class, 'verDemonstracao'])->name('carteira.demonstracoes.ver');
        // Ficha da empresa na visao do vendedor: preco de venda, nada interno.
        Route::get('/empresas/{empresa}', [CarteiraController::class, 'empresa'])->name('carteira.empresa');
        Route::get('/demonstracoes/{consulta}/pdf', [CarteiraController::class, 'demonstracaoPdf'])->name('carteira.demonstracoes.pdf');
        Route::get('/servicos', [CarteiraController::class, 'servicos'])->name('carteira.servicos');
        Route::get('/simulacao', [CarteiraController::class, 'simulacao'])->name('carteira.simulacao');
    });

    // As faturas de todas as empresas. A baixa registrada aqui e a mesma que o
    // provedor de cobranca dispara por webhook: uma acao so, para baixa manual
    // e automatica nao divergirem.
    Route::middleware(['admin', 'financeiro'])->prefix('financeiro')->name('financeiro.')->group(function () {
        Route::get('/', [FinanceiroController::class, 'index'])->name('index');
        Route::post('/{fatura}/liquidar', [FinanceiroController::class, 'liquidar'])->name('liquidar');
        Route::post('/{fatura}/estornar', [FinanceiroController::class, 'estornar'])->name('estornar');
    });

    // Quem trabalha na Avalia. E aqui que se define a comissao de cada
    // vendedor, que vale do proximo fechamento em diante.
    Route::middleware('admin')->prefix('equipe')->name('equipe.')->group(function () {
        Route::get('/', [EquipeController::class, 'index'])->name('index');
        Route::get('/nova', [EquipeController::class, 'criar'])->name('criar');
        Route::post('/', [EquipeController::class, 'salvar'])->name('salvar');
        Route::get('/{membro}', [EquipeController::class, 'editar'])->name('editar');
        Route::put('/{membro}', [EquipeController::class, 'atualizar'])->name('atualizar');
        // Redefinicao de senha por e-mail: gera link novo de convite. Definida
        // a senha, todos os links anteriores morrem juntos.
        Route::post('/{membro}/convite', [EquipeController::class, 'convite'])->name('convite');
        // Remover e tirar de circulacao, nao apagar: fatura e carteira apontam
        // para a pessoa. Restaurar desfaz.
        Route::delete('/{membro}', [EquipeController::class, 'remover'])->name('remover');
        Route::post('/{id}/restaurar', [EquipeController::class, 'restaurar'])->name('restaurar');
        Route::delete('/{id}/excluir', [EquipeController::class, 'excluir'])->name('excluir');
    });

    // Trilha de auditoria, so leitura: trilha que a tela edita nao e trilha.
    Route::get('/auditoria', AuditoriaController::class)->middleware('admin')->name('auditoria');

    Route::middleware('admin')->prefix('documentos')->name('documentos.')->group(function () {
        Route::get('/', [DocumentoController::class, 'index'])->name('index');
        Route::get('/novo', [DocumentoController::class, 'criar'])->name('criar');
        Route::get('/{documento}/pdf', [DocumentoController::class, 'pdf'])->name('pdf');
        Route::post('/', [DocumentoController::class, 'salvar'])->name('salvar');
    });

    // Credenciais dos servicos externos, criptografadas no banco: trocar uma
    // chave de API nao pode depender de acesso SSH ao servidor.
    Route::middleware('admin')->prefix('conexoes')->name('conexoes.')->group(function () {
        Route::get('/', [ConexaoController::class, 'index'])->name('index');
        Route::put('/{fornecedor}', [ConexaoController::class, 'atualizar'])->name('atualizar');
        Route::post('/{fornecedor}/alternar', [ConexaoController::class, 'alternar'])->name('alternar');
        Route::post('/{fornecedor}/testar', [ConexaoController::class, 'testar'])->name('testar');
    });

    Route::middleware('admin')->prefix('campanhas')->name('campanhas.')->group(function () {
        Route::get('/', [CampanhaController::class, 'index'])->name('index');
        Route::get('/nova', [CampanhaController::class, 'criar'])->name('criar');
        Route::post('/', [CampanhaController::class, 'salvar'])->name('salvar');
        // A vigente aparece na pagina publica: encerrar e um clique.
        Route::post('/{campanha}/alternar', [CampanhaController::class, 'alternar'])->name('alternar');
    });

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
        // Um assunto por tela, e nao uma pagina unica com tudo empilhado: quem
        // entra para pagar a fatura nao passa pelo formulario de consulta.
        Route::get('/', [AreaClienteController::class, 'painel'])->name('painel');
        Route::get('/consultar', [AreaClienteController::class, 'consultar'])->name('consultar');
        Route::get('/consultas', [AreaClienteController::class, 'consultas'])->name('consultas');
        Route::get('/faturas', [AreaClienteController::class, 'faturas'])->name('faturas');

        // Simulador da fatura: GET, nada gravado, o cenario vira link.
        Route::get('/simulador', [AreaClienteController::class, 'simulador'])->name('simulador');

        Route::get('/documentos', [AreaClienteController::class, 'documentos'])->name('documentos');
        Route::post('/documentos/{documento}/aceite', [AreaClienteController::class, 'aceitar'])->name('documentos.aceitar');
        Route::get('/documentos/{documento}/pdf', [AreaClienteController::class, 'documentoPdf'])->name('documentos.pdf');
        Route::get('/documentos/{documento}/comprovante', [AreaClienteController::class, 'comprovantePdf'])->name('documentos.comprovante');

        // A consulta em si. E a empresa que pede, porque e ela que consome.
        // Teto por origem, alem do teto diario por empresa: um vaza acesso,
        // o outro vaza laco de programa.
        Route::post('/consultas', [AreaClienteController::class, 'executar'])
            ->middleware('throttle:30,1')
            ->name('consultas.executar');
        Route::get('/consultas/{consulta}', [AreaClienteController::class, 'verConsulta'])->name('consultas.ver');
    });
