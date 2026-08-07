<?php

namespace App\Support;

use App\Models\Consulta;

/**
 * O resultado de uma consulta em PDF, para compartilhar.
 *
 * E o unico jeito aprovado de o resultado sair da tela: arquivo entregue em
 * mao (ou anexado), nunca dado pessoal em URL de conversa. O rodape carrega o
 * protocolo, entao qualquer duvida futura cita um numero que o suporte acha.
 *
 * O arquivo tambem carimba QUEM o emitiu, em toda pagina. E o que o mercado
 * faz (marca d'agua com o responsavel pela emissao) e serve a um proposito
 * simples: PDF vaza, e o vazado precisa apontar para quem o gerou. Custa uma
 * linha e transforma o arquivo em evidencia contra o proprio mau uso.
 */
final class ConsultaPdf
{
    public static function resultado(Consulta $consulta, ?string $emitidoPor = null): string
    {
        $emissor = $emitidoPor ?? $consulta->solicitante ?? 'Avalia';

        $pdf = (new Pdf)
            ->rodape('Emitido por '.$emissor.' em '.now()->format('d/m/Y H:i')
                .' · protocolo '.($consulta->referencia_externa ?? 's/n').' · avaliaone.com.br')
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
