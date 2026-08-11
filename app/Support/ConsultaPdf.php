<?php

namespace App\Support;

use App\Models\Consulta;

/**
 * O resultado de uma consulta em PDF.
 *
 * E o unico jeito aprovado de o resultado sair da tela: arquivo entregue em
 * mao (ou anexado), nunca dado pessoal em URL de conversa.
 *
 * O desenho segue o que o mercado faz, e cada parte tem uma razao:
 *
 * - A marca e a data no topo, porque o arquivo circula solto e precisa dizer de
 *   onde veio sem depender do e-mail que o levou.
 * - As ressalvas ANTES do conteudo, e nao em letra miuda no rodape. A primeira
 *   separa a Avalia da decisao de credito de quem consulta, e ninguem le rodape.
 * - Os blocos na ordem de quem decide: score, quem e, o que pesa contra,
 *   contexto. Quem le de cima para baixo conclui antes de acabar a pagina.
 * - O que a consulta NAO contempla, dito com todas as letras. Ausencia de
 *   pendencia e ausencia de informacao sao coisas opostas.
 * - O rodape carimba QUEM emitiu, em toda pagina. PDF vaza, e o vazado precisa
 *   apontar para quem o gerou. Custa uma linha e transforma o arquivo em
 *   evidencia contra o proprio mau uso.
 *
 * A leitura em blocos vem de App\Support\Laudo, que e a mesma fonte da tela: PDF
 * e tela que montam a ordem por conta propria divergem no primeiro campo novo.
 */
final class ConsultaPdf
{
    public static function resultado(Consulta $consulta, ?string $emitidoPor = null): string
    {
        $emissor = $emitidoPor ?? $consulta->solicitante ?? 'Avalia';
        $resposta = (array) $consulta->resposta;
        $documento = Documento::mascarar($consulta->documento);

        $pdf = (new Pdf)
            ->rodape('Emitido por '.$emissor.' em '.now()->format('d/m/Y H:i')
                .' · protocolo '.($consulta->referencia_externa ?? 's/n').' · avaliaone.com.br');

        // O nome do titular no canto oposto a marca, em negrito: e a primeira
        // coisa que se confere num laudo. Saindo do cabecalho, ele sai do
        // bloco de identificacao, senao apareceria duas vezes.
        $pdf->identidade(
            is_scalar($resposta['nome'] ?? null) ? (string) $resposta['nome'] : null,
            array_filter([
                isset($resposta['situacao_cadastral']) && is_scalar($resposta['situacao_cadastral'])
                    ? 'Situação cadastral: '.$resposta['situacao_cadastral']
                    : null,
            ]),
        );

        // Os blocos ausentes se medem ANTES de mover o nome para o cabecalho:
        // identificacao mostrada la em cima continua contemplada, e acusa-la
        // como ausente seria o laudo desmentindo a si mesmo.
        $ausentes = Laudo::ausentes($resposta);

        if (is_scalar($resposta['nome'] ?? null)) {
            unset($resposta['nome'], $resposta['situacao_cadastral']);
        }

        $pdf->marca(resource_path('marca/avaliaone.jpg'))
            ->meta('Relatório de consulta · avaliaone.com.br')
            ->espaco(6);

        $pdf->secao($consulta->servico?->nome ?? 'Consulta')
            ->linha('Documento consultado', $documento ?: 'Não informado')
            ->linha('Consultado em', $consulta->created_at->format('d/m/Y \à\s H:i'))
            ->linha('Protocolo', $consulta->referencia_externa ?? 'sem protocolo')
            ->linha('Finalidade declarada', $consulta->finalidade ?? 'Pesquisa de score de crédito');

        foreach (Laudo::blocos($resposta) as $bloco) {
            // O bloco inteiro ou nada nesta pagina: titulo orfao no pe, com as
            // linhas na pagina seguinte, e o tipo de corte que faz o leitor
            // achar que a secao esta vazia.
            $pdf->garantir(46 + min(count($bloco['linhas']), 12) * 16);
            $pdf->secao($bloco['titulo']);

            foreach ($bloco['linhas'] as $linha) {
                $pdf->linha($linha['rotulo'], $linha['valor']);
            }

            // A regua do score logo abaixo do numero: o numero sozinho obriga
            // quem le a lembrar da escala.
            if ($bloco['titulo'] === 'Score e risco' && is_numeric($resposta['score'] ?? null)) {
                $pdf->medidor((float) $resposta['score']);
            }
        }

        // As bases que o servico inclui e que NAO trouxeram dados aparecem com
        // secao propria, vazia e dizendo por que. Sumir com a secao deixaria o
        // leitor concluir que a base foi pesquisada e nada constava, que e a
        // conclusao mais cara possivel num laudo.
        // Em laudo simulado as bases em branco tambem se calam: o exercicio ja
        // se declara exercicio, e prestar contas de base ausente e papel do
        // laudo real.
        $basesSemDados = empty($resposta['simulado']) ? self::basesSemDados($consulta, $resposta) : [];

        foreach ($basesSemDados as $nome => $motivo) {
            $pdf->garantir(80);
            $pdf->secao($nome);
            $pdf->nota('Resultado incompleto nesta base: '.$motivo);
            $pdf->nota('Nenhuma informação desta base consta neste relatório. '
                .'A ausência aqui não prova ausência de ocorrências.');
        }

        if ($ausentes !== []) {
            $pdf->secao('O que esta consulta não contempla')
                ->paragrafo(
                    'Este produto não retornou informações de '.self::lista($ausentes).'. '
                    .'A ausência aqui não significa ausência de ocorrências: significa que esta '
                    .'consulta não pesquisou essas bases. Para incluí-las, contrate o produto '
                    .'correspondente com a Avalia.',
                );
        }

        // As ressalvas fecham o documento ANCORADAS no pe da ultima pagina,
        // inteiras, com a marca reduzida e a identificacao da casa: e onde todo
        // relatorio de mercado as poe, e cortar nota legal no meio de uma
        // quebra de pagina e o unico jeito de garantir que ninguem a leia.
        $pdf->fecho(
            'Informações importantes',
            Laudo::ressalvas($documento),
            'Avalia · avaliaone.com.br · relatório de consulta',
        );

        return $pdf->bytes();
    }

    /**
     * As bases do servico que nao contribuiram para este laudo.
     *
     * Vem de dois lugares: as fontes que foram consultadas e nao responderam
     * (gravadas na consulta) e o SCR, que o nome do servico promete mas ainda
     * nao tem conector. Os dois casos aparecem porque o cliente comprou um
     * servico com essas bases no nome, e o laudo precisa prestar contas do
     * nome inteiro.
     *
     * @return array<string, string> nome legivel => motivo
     */
    private static function basesSemDados(Consulta $consulta, array $resposta): array
    {
        $bases = [];

        foreach (Laudo::fontesIndisponiveis($resposta) as $fonte => $motivo) {
            $bases[Laudo::nomeDaFonte($fonte)] = $motivo;
        }

        if (stripos((string) $consulta->servico?->nome, 'SCR') !== false) {
            $bases[Laudo::nomeDaFonte('scr')] ??= 'A base ainda não está conectada à plataforma.';
        }

        return $bases;
    }

    /** "a, b e c", que e como se escreve lista em portugues. */
    private static function lista(array $itens): string
    {
        if (count($itens) === 1) {
            return $itens[0];
        }

        $ultimo = array_pop($itens);

        return implode(', ', $itens).' e '.$ultimo;
    }
}
