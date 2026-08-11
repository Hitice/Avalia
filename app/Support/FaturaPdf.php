<?php

namespace App\Support;

use App\Models\Consulta;
use App\Models\Fatura;

/**
 * O demonstrativo da fatura, para o cliente imprimir ou arquivar.
 *
 * Nao e nota fiscal e nao e boleto: e a composicao do que foi cobrado, serviço
 * a serviço, no formato que o financeiro do cliente arquiva e anexa ao
 * pagamento. A tela ja mostra isso quando alguem abre a linha, mas quem paga
 * costuma ser outra pessoa, e essa pessoa pede um arquivo.
 *
 * O que NUNCA entra aqui: custo do fornecedor, lucro, margem e comissao. Sao
 * numeros internos, e este arquivo sai da nossa mao para a do cliente
 * (PDD.md, secao 6).
 */
final class FaturaPdf
{
    /**
     * O arquivo pronto para download, com nome que se acha na pasta depois.
     *
     * Mora aqui, e nao nos dois controllers, porque o nome do arquivo e parte
     * do documento: cliente e atendimento precisam citar o mesmo arquivo.
     */
    public static function resposta(Fatura $fatura): \Illuminate\Http\Response
    {
        $nome = 'fatura-'.$fatura->competencia.'-'.$fatura->id.'.pdf';

        return response(self::demonstrativo($fatura), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$nome.'"',
        ]);
    }

    public static function demonstrativo(Fatura $fatura): string
    {
        $empresa = $fatura->cliente;

        $pdf = (new Pdf)
            ->rodape('Avalia · avaliaone.com.br · documento gerado em '.now()->format('d/m/Y H:i'))
            ->marca(resource_path('marca/avaliaone.jpg'))
            ->titulo('Demonstrativo da fatura')
            // O CNPJ so entra quando existe: rotulo sem numero na frente e o
            // tipo de detalhe que faz o financeiro do cliente desconfiar do
            // documento inteiro.
            ->meta(trim($empresa->razao_social
                .($empresa->cnpj ? ' · CNPJ '.Documento::formatarCnpj($empresa->cnpj) : '')))
            ->meta('Competência '.$fatura->competenciaRotulo()
                .' · vencimento em '.$fatura->vencimento()->format('d/m/Y')
                .' · '.Rotulos::fatura($fatura->situacao_pagamento));

        // O resumo antes do detalhe, como o mercado escreve laudo: quem confere
        // quer saber quantas e quanto antes de olhar linha por linha.
        $consultas = (int) $fatura->itens->sum('quantidade');

        $pdf->secao('Resumo do período')
            ->linha('Consultas concluídas', (string) $consultas)
            ->linha('Serviços utilizados', (string) $fatura->itens->count())
            ->linha('Vencimento', $fatura->vencimento()->format('d/m/Y'))
            ->linha('Total', $fatura->totalRotulo(), true);

        $pdf->secao('Composição');
        $pdf->linha('Mensalidade do plano', Dinheiro::brl($fatura->mensalidade_cents));

        foreach ($fatura->itens as $item) {
            $detalhe = $item->quantidade.' '.($item->quantidade === 1 ? 'consulta' : 'consultas');

            if ($item->quantidade_franquia > 0) {
                $detalhe .= ', '.$item->quantidade_franquia.' na franquia';
            }

            $pdf->linha($item->servico_nome.' · '.$detalhe, Dinheiro::brl($item->valor_excedente_cents));
        }

        // O complemento so aparece quando existe, e com o nome que explica o
        // que ele e: cobrar sem dizer o motivo e o que gera chamado.
        if ($fatura->pagouSemUsarCents() > 0) {
            $pdf->linha(
                'Complemento até o consumo mínimo contratado',
                Dinheiro::brl($fatura->pagouSemUsarCents()),
            );
        }

        $pdf->divisoria()->linha('Total', $fatura->totalRotulo(), true);

        if ($fatura->estaLiquidada()) {
            $pdf->espaco(10)->paragrafo(
                'Pagamento confirmado em '.$fatura->liquidada_em?->format('d/m/Y').'.',
            );
        }

        // O bloco de ressalvas, no mesmo lugar em que o mercado o coloca: antes
        // do fim e em corpo de texto, e nao em letra miuda no rodape. A
        // excludente de responsabilidade sobre a decisao de credito e a mais
        // importante delas, e e a primeira.
        $pdf->fecho(
            'Informações importantes',
            Laudo::ressalvasDaFatura($consultas, Consulta::DIAS_DE_RETENCAO),
            'Avalia · avaliaone.com.br · demonstrativo de fatura',
        );

        return $pdf->bytes();
    }
}
