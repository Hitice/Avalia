<?php

namespace App\Services\Conectores;

use App\Models\Servico;
use App\Support\RespostaConsulta;

/**
 * Consulta todas as bases de um servico e junta o que voltou.
 *
 * Um servico do catalogo pode puxar de mais de uma base ("Base I e III"), e
 * base e fornecedor diferente. Ate aqui a consulta falhava inteira quando UMA
 * delas nao respondia, e isso e o pior comportamento possivel: o cliente fica
 * sem a informacao que estava disponivel por causa da que nao estava.
 *
 * Entao o resultado e parcial de proposito. Vale o que veio, e o que faltou e
 * dito com todas as letras no laudo. Um relatorio incompleto e util; um
 * relatorio que nao sai nao e util para ninguem.
 *
 * A consulta so falha inteira quando NENHUMA base responde. Nesse caso nao ha
 * o que entregar, e cobrar seria cobrar por nada.
 *
 * O campo `fornecedor` do servico aceita varios, separados por virgula, na
 * ordem em que se consulta:
 *
 *     serasa,boa-vista
 */
class ConsultarFontes
{
    public function __construct(private readonly EscolherConector $escolher) {}

    /**
     * @return array{
     *     resposta: RespostaConsulta,
     *     fontes: list<string>,
     *     indisponiveis: array<string, string>
     * }
     */
    public function __invoke(Servico $servico, string $documento, string $finalidade): array
    {
        $fontes = $this->fontesDe($servico);

        $dados = [];
        $protocolo = null;
        $duracao = 0;
        $atendidas = [];
        $indisponiveis = [];

        foreach ($fontes as $fonte) {
            // Servico com VARIAS fontes: a que nao existe ou esta desligada
            // vira base em branco no laudo, e nao fallback. Cair no conector
            // geral aqui consultaria a mesma base duas vezes e esconderia do
            // laudo que a fonte prometida nao veio. Com fonte unica a cascata
            // continua valendo, porque ali fallback e a diferenca entre
            // atender e nao atender.
            $conhecida = $fonte === 'simulado'
                || (isset(EscolherConector::CONECTORES[$fonte]) && \App\Models\Conexao::ativaDe($fonte));

            if (count($fontes) > 1 && ! $conhecida) {
                $indisponiveis[$fonte] = 'A base ainda não está conectada à plataforma.';

                continue;
            }

            $conector = $this->escolher->conector($fonte);
            $resposta = $conector->consultar($servico, $documento, $finalidade);
            $duracao += $resposta->duracaoMs;

            if (! $resposta->sucesso) {
                // O motivo vai junto: "Base III não respondeu" sem dizer por que
                // manda o operador abrir chamado para descobrir o que a tela
                // podia ter contado.
                $indisponiveis[$conector->nome()] = $resposta->erro ?? 'não respondeu';

                continue;
            }

            $atendidas[] = $conector->nome();
            $dados = $this->juntar($dados, $resposta->dados);
            $protocolo ??= $resposta->referenciaExterna;
        }

        if ($atendidas === []) {
            // Nenhuma respondeu: nao ha resultado parcial, ha resultado nenhum.
            return [
                'resposta' => RespostaConsulta::falha(
                    $indisponiveis === [] ? 'Nenhuma base respondeu.' : reset($indisponiveis),
                    null,
                    $duracao,
                ),
                'fontes' => [],
                'indisponiveis' => $indisponiveis,
            ];
        }

        return [
            'resposta' => RespostaConsulta::sucesso($dados, $protocolo, $duracao),
            'fontes' => $atendidas,
            'indisponiveis' => $indisponiveis,
        ];
    }

    /**
     * As bases do servico, na ordem de consulta.
     *
     * Sem nenhuma declarada, vale a escolha geral: e o comportamento de antes,
     * e servico novo nao pode nascer mudo.
     *
     * @return list<string>
     */
    public function fontesDe(Servico $servico): array
    {
        $declaradas = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) $servico->fornecedor),
        )));

        return $declaradas !== [] ? $declaradas : [EscolherConector::global()];
    }

    /**
     * Junta o resultado de duas bases.
     *
     * Quem chegou primeiro fica: as bases sao consultadas na ordem declarada, e
     * essa ordem e a preferencia de quem montou o servico. A segunda base
     * COMPLETA o que faltou, e nao sobrescreve o que ja veio, senao a ordem
     * declarada nao significaria nada.
     *
     * @param  array<string, mixed>  $ate_agora
     * @param  array<string, mixed>  $novo
     * @return array<string, mixed>
     */
    private function juntar(array $ate_agora, array $novo): array
    {
        foreach ($novo as $chave => $valor) {
            if ($valor === null || $valor === '' || $valor === []) {
                continue;
            }

            // "informacoes_adicionais" acumula de todas as bases: e o deposito
            // do que o mapa ainda nao reconhece, e descartar o da segunda base
            // perderia justamente o que se quer descobrir.
            if ($chave === 'informacoes_adicionais') {
                $ate_agora[$chave] = array_merge((array) ($ate_agora[$chave] ?? []), (array) $valor);

                continue;
            }

            $ate_agora[$chave] ??= $valor;
        }

        return $ate_agora;
    }
}
