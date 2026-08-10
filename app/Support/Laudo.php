<?php

namespace App\Support;

/**
 * A forma canonica do resultado de uma consulta.
 *
 * O problema que isto resolve foi o Marcos quem nomeou: cada fornecedor entrega
 * uma coisa diferente, e cada produto do mesmo fornecedor entrega um recorte
 * diferente ainda. Sem uma forma comum, a tela e o PDF viravam despejo de
 * campos crus, em ordem aleatoria, com nome de chave de API.
 *
 * Entao o laudo tem BLOCOS, em ordem fixa, e a ordem e a de quem decide credito:
 * primeiro a decisao (score), depois quem e (identificacao), depois o que pesa
 * contra (restricoes, da mais grave para a menos), e por fim o contexto. Quem le
 * o laudo de cima para baixo chega a uma conclusao antes de acabar a pagina.
 *
 * O que o produto NAO traz e dito com todas as letras, como o mercado faz.
 * Ausencia de pendencia e ausencia de informacao sao coisas opostas, e um laudo
 * que cala sobre a segunda deixa o cliente concluir a primeira.
 *
 * Campo que nenhum bloco reconhece nao se perde: vai para o fim, com o nome
 * legivel possivel. E assim que o mapa cresce, com resposta real de fornecedor
 * e nao com suposicao.
 */
final class Laudo
{
    /**
     * Os blocos, na ordem em que se le.
     *
     * @var array<string, array{titulo: string, campos: array<string, string>}>
     */
    public const BLOCOS = [
        'decisao' => [
            'titulo' => 'Score e risco',
            'campos' => [
                'score' => 'Score',
                'modelo_do_score' => 'Modelo do score',
                'faixa_do_score' => 'Faixa',
                'probabilidade_de_inadimplencia' => 'Probabilidade de inadimplência',
            ],
        ],
        'identificacao' => [
            'titulo' => 'Identificação',
            'campos' => [
                'nome' => 'Nome',
                'situacao_cadastral' => 'Situação cadastral',
                'data_de_nascimento' => 'Data de nascimento',
                'data_de_fundacao' => 'Data de fundação',
                'atividade_principal' => 'Atividade principal',
            ],
        ],
        'restricoes' => [
            'titulo' => 'Restrições',
            'campos' => [
                'recuperacao_judicial' => 'Recuperação judicial',
                'falencia' => 'Falência',
                'acoes_judiciais' => 'Ações judiciais',
                'protestos' => 'Protestos',
                'pendencias_financeiras' => 'Pendências financeiras',
                'pendencias_comerciais' => 'Pendências comerciais',
                'pendencias_internas' => 'Pendências internas',
                'pendencias' => 'Pendências',
                'cheques_sem_fundo' => 'Cheques sem fundo',
                'dividas_vencidas' => 'Dívidas vencidas',
                'valor_total_das_restricoes_cents' => 'Valor total das restrições',
            ],
        ],
        'contexto' => [
            'titulo' => 'Contexto',
            'campos' => [
                'consultas_recentes' => 'Consultas recentes ao documento',
                'participacoes_societarias' => 'Participações societárias',
                'documentos_extraviados' => 'Documentos roubados, furtados ou extraviados',
                'ultima_atualizacao' => 'Última atualização do cadastro',
            ],
        ],
    ];

    /**
     * Chaves de controle: existem no laudo mas nao sao informacao do titular.
     *
     * @var list<string>
     */
    private const CONTROLE = ['laudo', 'fornecido_em', 'informacoes_adicionais', 'erro'];

    /**
     * O laudo organizado em blocos, pronto para a tela e para o PDF.
     *
     * Os dois consomem daqui de proposito: PDF e tela que montam a leitura por
     * conta propria divergem no primeiro campo novo, e o cliente liga
     * perguntando por que o papel diz uma coisa e a tela diz outra.
     *
     * @param  array<string, mixed>  $resposta
     * @return list<array{titulo: string, linhas: list<array{rotulo: string, valor: string}>}>
     */
    public static function blocos(array $resposta): array
    {
        $blocos = [];

        foreach (self::BLOCOS as $bloco) {
            $linhas = [];

            foreach ($bloco['campos'] as $chave => $rotulo) {
                if (! array_key_exists($chave, $resposta) || $resposta[$chave] === null || $resposta[$chave] === '') {
                    continue;
                }

                $linhas[] = ['rotulo' => $rotulo, 'valor' => self::valor($chave, $resposta[$chave])];
            }

            if ($linhas !== []) {
                $blocos[] = ['titulo' => $bloco['titulo'], 'linhas' => $linhas];
            }
        }

        $extras = self::extras($resposta);

        if ($extras !== []) {
            $blocos[] = ['titulo' => 'Outras informações', 'linhas' => $extras];
        }

        return $blocos;
    }

    /**
     * Os blocos que esta consulta NAO trouxe.
     *
     * Existe para o laudo dizer o que nao sabe. Ausencia de pendencia e
     * ausencia de informacao sao coisas opostas, e o laudo que cala sobre a
     * segunda deixa quem le concluir a primeira, que e o erro mais caro
     * possivel numa decisao de credito.
     *
     * @param  array<string, mixed>  $resposta
     * @return list<string>
     */
    public static function ausentes(array $resposta): array
    {
        $ausentes = [];

        foreach (self::BLOCOS as $bloco) {
            $tem = false;

            foreach (array_keys($bloco['campos']) as $chave) {
                if (array_key_exists($chave, $resposta) && $resposta[$chave] !== null && $resposta[$chave] !== '') {
                    $tem = true;

                    break;
                }
            }

            if (! $tem) {
                $ausentes[] = mb_strtolower($bloco['titulo']);
            }
        }

        return $ausentes;
    }

    /**
     * Campos que nenhum bloco reconhece, com o nome mais legivel possivel.
     *
     * @param  array<string, mixed>  $resposta
     * @return list<array{rotulo: string, valor: string}>
     */
    private static function extras(array $resposta): array
    {
        $conhecidas = array_merge(
            self::CONTROLE,
            ...array_map(fn (array $b) => array_keys($b['campos']), array_values(self::BLOCOS)),
        );

        $extras = [];

        foreach ($resposta as $chave => $valor) {
            if (in_array($chave, $conhecidas, true) || $valor === null || $valor === '') {
                continue;
            }

            $extras[] = [
                'rotulo' => ucfirst(str_replace('_', ' ', (string) $chave)),
                'valor' => self::valor((string) $chave, $valor),
            ];
        }

        return $extras;
    }

    /** O valor escrito como pessoa le: dinheiro em reais, booleano em palavra. */
    public static function valor(string $chave, mixed $valor): string
    {
        if (is_bool($valor)) {
            return $valor ? 'Sim' : 'Não';
        }

        if (str_ends_with($chave, '_cents')) {
            return Dinheiro::brl((int) $valor);
        }

        if (is_array($valor)) {
            return (string) json_encode($valor, JSON_UNESCAPED_UNICODE);
        }

        return (string) $valor;
    }

    /**
     * As ressalvas que acompanham o demonstrativo da fatura.
     *
     * A primeira e a mesma do laudo, e a repeticao e proposital. O laudo fica
     * com quem consultou; a fatura vai para a contabilidade e para o juridico
     * do cliente, e e la que a excludente precisa estar escrita quando alguem
     * for procurar meses depois. Ninguem guarda os PDFs de consulta.
     *
     * As demais respondem as perguntas que o financeiro do cliente faz ao
     * conferir a fatura: o que foi cobrado, o que nao foi, e ate quando da para
     * conferir cada linha.
     *
     * @return list<string>
     */
    public static function ressalvasDaFatura(int $consultas, int $diasDeRetencao): array
    {
        return [
            'As consultas cobradas neste demonstrativo serviram para subsidiar decisões de '
            .'crédito do contratante. A decisão de conceder ou não o crédito foi, e permanece, '
            .'de exclusiva responsabilidade de quem consultou. A Avalia responde pela entrega '
            .'da informação, não pelo uso que se faz dela.',

            'Consulta não concluída não é cobrada e não aparece aqui. '
            .($consultas > 0
                ? 'Foram '.$consultas.' '.($consultas === 1 ? 'consulta concluída' : 'consultas concluídas').' no período.'
                : 'Não houve consulta concluída no período.'),

            'O detalhe de cada consulta fica no portal por '.$diasDeRetencao.' dias a partir da '
            .'data em que foi feita. O registro da consulta em si é permanente.',

            'Demonstrativo do consumo do período, para conferência e arquivo. '
            .'Não substitui a nota fiscal nem serve como comprovante de pagamento.',
        ];
    }

    /**
     * As ressalvas que acompanham todo laudo.
     *
     * Nao e formalidade: a primeira delas e a que separa a Avalia da decisao de
     * credito de quem consulta, e a segunda e a que impede a consulta de ser
     * lida como anotacao negativa. As duas sao praxe no mercado, e a razao e a
     * mesma nos dois casos.
     *
     * @return list<string>
     */
    public static function ressalvas(string $documentoMascarado): array
    {
        // Consulta em lote nao guarda documento, e a frase precisa continuar
        // inteira: "Consulta ao documento  nas bases" denuncia o buraco.
        $alvo = $documentoMascarado === ''
            ? 'Consulta às bases contratadas.'
            : 'Consulta ao documento '.$documentoMascarado.' nas bases contratadas.';

        return [
            'A decisão de conceder ou não o crédito é de exclusiva responsabilidade de quem consulta. '
            .'As informações deste laudo servem para subsidiar essa decisão e não a substituem.',

            $alvo.' A consulta em si não '
            .'significa negócio realizado e não se confunde com anotação negativa em cadastro de inadimplentes.',

            'As informações refletem as bases no momento da consulta e podem mudar a qualquer tempo. '
            .'Uso restrito à finalidade declarada; é vedado o repasse a terceiros.',
        ];
    }
}
