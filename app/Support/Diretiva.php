<?php

namespace App\Support;

/**
 * Mede o que as diretivas internas exigem, para o teste poder cobrar.
 *
 * Diretiva escrita em documento envelhece: ninguem reabre o documento antes de
 * escrever a proxima classe. A literatura de design system chama o efeito de
 * DESIGN DRIFT, e a recomendacao nao e documentar melhor, e detectar.
 *
 * Entao a regra vive aqui como numero, e o teste falha quando o numero piora.
 * O limite comeca no valor medido hoje e so aperta: cada limpeza baixa o teto,
 * e nenhuma mudanca pode subir. E catraca, nao meta.
 */
final class Diretiva
{
    /** @return array{linhas: int, comentario: int, densidade: float} */
    public static function densidadeDeComentario(string $diretorio): array
    {
        $linhas = 0;
        $comentario = 0;

        foreach (self::arquivos($diretorio, 'php') as $caminho) {
            foreach (file($caminho, FILE_IGNORE_NEW_LINES) as $linha) {
                $limpa = trim($linha);

                if ($limpa === '') {
                    continue;
                }

                $linhas++;

                if (str_starts_with($limpa, '//') || str_starts_with($limpa, '*')
                    || str_starts_with($limpa, '/*')) {
                    $comentario++;
                }
            }
        }

        return [
            'linhas' => $linhas,
            'comentario' => $comentario,
            'densidade' => $linhas === 0 ? 0.0 : round($comentario / $linhas * 100, 2),
        ];
    }

    /**
     * Quanto da estilizacao usa o vocabulario da casa, e quanto e Tailwind cru.
     *
     * Nao e para zerar o Tailwind: layout (`flex`, `grid`, `gap`) e legitimo na
     * view. O que a conta pega e o outro caso: cor, borda, arredondamento e
     * espacamento repetidos a mao onde existe utility para isso, que e como
     * nasce a milesima combinacao unica de classe.
     */
    public static function aderenciaAoTema(string $diretorio): array
    {
        $daCasa = 0;
        $aparencia = 0;

        // Prefixos de APARENCIA. Layout fica de fora de proposito: `flex` e
        // `grid` descrevem arranjo da pagina, e nao a cara do componente.
        $cru = '/\b(bg-(?!white\b)|text-(?:gray|brand|success|error|warning)|border-(?:gray|brand)|rounded-(?:lg|xl|full)|shadow-|font-(?:medium|semibold|bold))/';

        foreach (self::arquivos($diretorio, 'blade.php') as $caminho) {
            preg_match_all('/class="([^"]*)"/', (string) file_get_contents($caminho), $achados);

            foreach ($achados[1] as $classe) {
                if (preg_match('/\b(cartao|campo|botao|tabela|etiqueta|aviso|rotulo|segmento|interruptor|ajuda-campo|erro-campo|grade-grafico|ponto-serie|serie-|titulo-|subtitulo-|flutuante|menu-item)/', $classe)) {
                    $daCasa++;
                }

                if (preg_match($cru, $classe)) {
                    $aparencia++;
                }
            }
        }

        $total = $daCasa + $aparencia;

        return [
            'da_casa' => $daCasa,
            'aparencia_crua' => $aparencia,
            'aderencia' => $total === 0 ? 100.0 : round($daCasa / $total * 100, 2),
        ];
    }

    /** @return list<string> */
    private static function arquivos(string $diretorio, string $extensao): array
    {
        $encontrados = [];

        $iterador = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($diretorio, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterador as $arquivo) {
            if ($arquivo->isFile() && str_ends_with($arquivo->getFilename(), '.'.$extensao)) {
                $encontrados[] = $arquivo->getPathname();
            }
        }

        return $encontrados;
    }
}
