<?php

use App\Support\Diretiva;

/*
|--------------------------------------------------------------------------
| Catraca
|--------------------------------------------------------------------------
|
| Os dois limites abaixo sao o estado medido do repositorio, e existem para
| descer. Quem limpar um arquivo baixa o teto na mesma mudanca; quem sujar ve o
| teste falhar antes de publicar.
|
| Nao viram meta: nao ha prazo para chegar em 15% e em 70%. Ha a proibicao de
| voltar. Documento nao consegue cobrar isso, porque ninguem reabre documento
| antes de escrever a proxima classe.
*/

const TETO_DE_COMENTARIO = 30.39;
const PISO_DE_ADERENCIA = 60.0;

$raiz = dirname(__DIR__, 2);

it('nao deixa o comentario crescer sobre o codigo', function () use ($raiz) {
    $medida = Diretiva::densidadeDeComentario($raiz.'/app');

    expect($medida['densidade'])->toBeLessThanOrEqual(
        TETO_DE_COMENTARIO,
        "Comentario em {$medida['densidade']}% de app/ ({$medida['comentario']} de {$medida['linhas']} linhas), "
        .'acima do teto de '.TETO_DE_COMENTARIO.'%. Comentario explica por que; o que ja esta na linha abaixo.',
    );
});

it('nao deixa a tela escapar do vocabulario da casa', function () use ($raiz) {
    $medida = Diretiva::aderenciaAoTema($raiz.'/resources/views');

    expect($medida['aderencia'])->toBeGreaterThanOrEqual(
        PISO_DE_ADERENCIA,
        "Aderencia em {$medida['aderencia']}% ({$medida['da_casa']} usos do tema contra {$medida['aparencia_crua']} "
        .'de aparencia escrita a mao), abaixo do piso de '.PISO_DE_ADERENCIA.'%. '
        .'Cor, borda, arredondamento e sombra saem de @utility em resources/css/app.css.',
    );
});

it('mantem um unico razao contabil', function () use ($raiz) {
    // Tres razoes coexistem no banco: lancamentos_360, lancamentos_financeiros
    // com partidas_financeiras, e faturas com itens_fatura. Cada um responde uma
    // pergunta diferente sobre o mesmo dinheiro, e as respostas divergem. O
    // plano consolida os tres em um razao com subrazoes. Ate lá, nao nasce um
    // quarto.
    $razoes = collect(glob($raiz.'/database/migrations/*.php'))
        ->map(fn ($caminho) => (string) file_get_contents($caminho))
        ->flatMap(function ($conteudo) {
            preg_match_all("/Schema::create\('([a-z_0-9]+)'/", $conteudo, $achados);

            return $achados[1];
        })
        ->filter(fn ($tabela) => str_contains($tabela, 'lancamento') || str_contains($tabela, 'partida'))
        ->unique()
        ->sort()
        ->values();

    expect($razoes->all())->toBe(
        ['lancamentos_360', 'lancamentos_financeiros', 'partidas_financeiras'],
        'Nasceu um razao novo. Dinheiro entra no razao que existe, como subrazao, e nao em tabela propria.',
    );
});

it('mantem o razao sem saber de produto nenhum', function () use ($raiz) {
    /*
     * A fronteira que decide se o financeiro pode um dia sair daqui.
     *
     * `app/Contabil/` sabe de conta, partida e competencia. Quem sabe o que e
     * uma plaquinha, uma fatura ou um pedido e o PRODUTO, e e ele que traduz a
     * venda em pernas e chama o razao. Na direcao contraria, cada produto novo
     * engorda o razao, e separa-lo depois deixa de ser empacotamento e vira
     * reescrita.
     *
     * Ja foi quebrada uma vez: `VendaDeEtiqueta` e `SociosDaPlaquinha` moravam
     * aqui, importando `App\Models\Etiqueta` e lendo `config('etiquetas')`.
     */
    $proibidos = ['Etiqueta', 'Fatura', 'Consulta', 'Cliente', 'Pedido360', 'Parcela360', 'Produtor', 'Lead'];

    $vazamentos = [];

    foreach (glob($raiz.'/app/Contabil/*.php') as $caminho) {
        $fonte = (string) file_get_contents($caminho);

        foreach ($proibidos as $modelo) {
            if (str_contains($fonte, 'App\\Models\\'.$modelo)) {
                $vazamentos[] = basename($caminho).' usa '.$modelo;
            }
        }

        foreach (['etiquetas.', 'cobranca.', 'catalogo.'] as $config) {
            if (str_contains($fonte, "config('".$config)) {
                $vazamentos[] = basename($caminho)." le config('".$config."')";
            }
        }
    }

    expect($vazamentos)->toBe(
        [],
        'O razao passou a conhecer produto: '.implode('; ', $vazamentos)
        .'. A traducao do evento em pernas mora no produto, e nao em app/Contabil.',
    );
});
