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

        $pdf->marca(resource_path('marca/avaliaone.jpg'))
            ->meta('Relatório de consulta · avaliaone.com.br')
            ->espaco(6);

        $pdf->secao($consulta->servico?->nome ?? 'Consulta')
            ->linha('Documento consultado', $documento ?: 'Não informado')
            ->linha('Consultado em', $consulta->created_at->format('d/m/Y \à\s H:i'))
            ->linha('Protocolo', $consulta->referencia_externa ?? 'sem protocolo')
            ->linha('Finalidade declarada', $consulta->finalidade ?? 'Pesquisa de score de crédito');

        // Antes do resultado, porque muda como se le tudo o que vem depois.
        $indisponiveis = Laudo::fontesIndisponiveis($resposta);

        if ($indisponiveis !== []) {
            $pdf->secao('Este resultado está incompleto');

            foreach ($indisponiveis as $fonte => $motivo) {
                $pdf->linha(Laudo::nomeDaFonte($fonte), 'não respondeu');
            }

            $pdf->espaco(6)->paragrafo(
                'O que segue veio das bases que responderam. A ausência de uma ocorrência '
                .'aqui pode ser efeito da base que faltou, e não prova que ela não exista.',
            );
        }

        foreach (Laudo::blocos($resposta) as $bloco) {
            $pdf->secao($bloco['titulo']);

            foreach ($bloco['linhas'] as $linha) {
                $pdf->linha($linha['rotulo'], $linha['valor']);
            }
        }

        $ausentes = Laudo::ausentes($resposta);

        if ($ausentes !== []) {
            $pdf->secao('O que esta consulta não contempla')
                ->paragrafo(
                    'Este produto não retornou informações de '.self::lista($ausentes).'. '
                    .'A ausência aqui não significa ausência de ocorrências: significa que esta '
                    .'consulta não pesquisou essas bases. Para incluí-las, contrate o produto '
                    .'correspondente com a Avalia.',
                );
        }

        // As ressalvas fecham o documento, em corpo menor. E o lugar em que se
        // procura por elas em qualquer relatorio do mercado, e o tamanho diz a
        // hierarquia: precisam estar e precisam ser encontraveis, mas nao podem
        // disputar a leitura com o resultado.
        $pdf->secao('Informações importantes');

        foreach (Laudo::ressalvas($documento) as $ressalva) {
            $pdf->nota($ressalva);
        }

        return $pdf->bytes();
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
