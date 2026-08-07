<?php

namespace App\Support;

use App\Models\Consulta;

/**
 * O resultado de uma consulta em PDF, para compartilhar.
 *
 * E o unico jeito aprovado de o resultado sair da tela: arquivo entregue em
 * mao (ou anexado), nunca dado pessoal em URL de conversa. O rodape carrega o
 * protocolo, entao qualquer duvida futura cita um numero que o suporte acha.
 */
final class ConsultaPdf
{
    public static function resultado(Consulta $consulta): string
    {
        $pdf = (new Pdf)
            ->rodape('Avalia · avaliaone.com.br · protocolo '.($consulta->referencia_externa ?? 's/n'))
            ->titulo($consulta->servico?->nome ?? 'Consulta')
            ->meta('Consultado em '.$consulta->created_at->format('d/m/Y H:i')
                .($consulta->referencia_externa ? ' · protocolo '.$consulta->referencia_externa : ''))
            ->meta('Documento consultado: '.Documento::mascarar($consulta->documento))
            ->espaco(8);

        foreach ((array) $consulta->resposta as $campo => $valor) {
            if (! is_scalar($valor)) {
                $valor = json_encode($valor, JSON_UNESCAPED_UNICODE);
            }

            if (is_bool($valor)) {
                $valor = $valor ? 'Sim' : 'Não';
            }

            if (str_ends_with($campo, '_cents')) {
                $valor = Dinheiro::brl((int) $valor);
            }

            $pdf->paragrafo(ucfirst(str_replace('_', ' ', $campo)).': '.$valor);
        }

        $pdf->espaco(12)
            ->paragrafo('Resultado de pesquisa de score de crédito, para uso exclusivo em decisão de negócio do destinatário. É vedado o repasse.');

        return $pdf->bytes();
    }
}
