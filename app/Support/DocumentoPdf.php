<?php

namespace App\Support;

use App\Models\AceiteDocumento;
use App\Models\DocumentoLegal;

/**
 * Monta os dois PDFs do modulo de documentos.
 *
 * O documento em si, para leitura e arquivo, e o comprovante de aceite, que e
 * a evidencia: quem aceitou, o que exatamente (hash), quando e de onde. Os
 * dois carregam o hash do conteudo no rodape, entao qualquer copia impressa e
 * conferivel contra o banco.
 */
final class DocumentoPdf
{
    public static function documento(DocumentoLegal $documento): string
    {
        $pdf = (new Pdf)
            ->rodape(sprintf(
                'Avalia · %s · versão %s · íntegra sha256 %s',
                $documento->tipo, $documento->versao, substr($documento->hashConteudo(), 0, 16),
            ))
            ->titulo($documento->titulo)
            ->meta(sprintf('Versão %s · vigente · avaliaone.com.br', $documento->versao))
            ->espaco(8);

        self::conteudo($pdf, $documento->conteudo);

        return $pdf->bytes();
    }

    public static function comprovante(AceiteDocumento $aceite): string
    {
        $documento = $aceite->documento;
        $empresa = $aceite->cliente;

        $pdf = (new Pdf)
            ->rodape(sprintf(
                'Avalia · comprovante de aceite · íntegra sha256 %s',
                substr($aceite->hash_conteudo, 0, 16),
            ))
            ->titulo('Comprovante de aceite')
            ->meta($documento->titulo.' · versão '.$aceite->versao)
            ->espaco(10)
            ->secao('Quem aceitou')
            ->paragrafo(sprintf('%s, CNPJ %s.', $empresa->razao_social, $empresa->cnpjRotulo()))
            ->paragrafo('Responsável declarado: '.($aceite->responsavel ?: 'não informado').'.')
            ->secao('Quando e de onde')
            ->paragrafo('Aceito em '.$aceite->aceito_em->format('d/m/Y \à\s H:i:s').' (horário de Brasília).')
            ->paragrafo(sprintf(
                'Endereço de origem %s. Navegador: %s.',
                $aceite->ip_address ?: 'não registrado',
                $aceite->user_agent ? mb_strimwidth($aceite->user_agent, 0, 140, '...') : 'não registrado',
            ))
            ->secao('O que foi aceito')
            ->paragrafo(sprintf(
                'A íntegra aceita corresponde ao resumo criptográfico sha256 %s, conferido no ato do aceite contra a versão vigente.',
                $aceite->hash_conteudo,
            ))
            ->espaco(10)
            ->secao('Íntegra do documento aceito');

        self::conteudo($pdf, $documento->conteudo);

        return $pdf->bytes();
    }

    /** O conteudo dos documentos e texto com secoes marcadas por "## ". */
    private static function conteudo(Pdf $pdf, string $conteudo): void
    {
        $paragrafo = [];

        $despeja = function () use (&$paragrafo, $pdf) {
            if ($paragrafo !== []) {
                $pdf->paragrafo(implode(' ', $paragrafo));
                $paragrafo = [];
            }
        };

        foreach (preg_split('/\R/', $conteudo) ?: [] as $linha) {
            $linha = trim($linha);

            if ($linha === '') {
                $despeja();
            } elseif (str_starts_with($linha, '## ')) {
                $despeja();
                $pdf->secao(substr($linha, 3));
            } else {
                $paragrafo[] = $linha;
            }
        }

        $despeja();
    }
}
