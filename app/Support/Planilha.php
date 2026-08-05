<?php

namespace App\Support;

use RuntimeException;
use ZipArchive;

/**
 * Escreve e le XLSX sem biblioteca externa.
 *
 * Um xlsx e um zip de XML. Escrever o minimo necessario custa menos que
 * carregar uma dependencia inteira para gerar tres abas, e o projeto ja faz o
 * mesmo com o DOCX do PDD.
 *
 * Numero vai como numero, e nao como texto: o Excel precisa poder somar a
 * coluna. Por isso o valor no XML usa ponto decimal, que e o formato do
 * arquivo; quem mostra virgula e o Excel, conforme o idioma de quem abre.
 */
final class Planilha
{
    /**
     * @param  array<string, array{0: list<string>, 1: list<list<string|int|float|null>>}>  $abas
     *                                                                                             nome da aba => [cabecalho, linhas]
     */
    public static function xlsx(array $abas): string
    {
        $caminho = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive;

        if ($zip->open($caminho, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Nao foi possivel montar a planilha.');
        }

        $nomes = array_keys($abas);

        $zip->addFromString('[Content_Types].xml', self::tipos(count($nomes)));
        $zip->addFromString('_rels/.rels', self::relacaoRaiz());
        $zip->addFromString('xl/workbook.xml', self::pasta($nomes));
        $zip->addFromString('xl/_rels/workbook.xml.rels', self::relacaoPasta(count($nomes)));

        foreach (array_values($abas) as $indice => [$cabecalho, $linhas]) {
            $zip->addFromString('xl/worksheets/sheet'.($indice + 1).'.xml', self::aba($cabecalho, $linhas));
        }

        $zip->close();

        $conteudo = file_get_contents($caminho);
        unlink($caminho);

        return $conteudo;
    }

    /**
     * Le a primeira aba de um xlsx, ou um csv, e devolve as linhas.
     *
     * O Excel guarda texto numa tabela separada e referencia por indice, entao
     * a leitura resolve sharedStrings antes de montar a linha.
     *
     * @return list<list<string>>
     */
    public static function ler(string $caminho): array
    {
        return str_ends_with(strtolower($caminho), '.csv') || ! self::ehZip($caminho)
            ? self::lerCsv($caminho)
            : self::lerXlsx($caminho);
    }

    /** @return list<list<string>> */
    private static function lerCsv(string $caminho): array
    {
        $linhas = [];
        $arquivo = fopen($caminho, 'r');

        // Excel em portugues salva CSV com ponto e virgula.
        while (($campos = fgetcsv($arquivo, 0, ';')) !== false) {
            $linhas[] = array_map(fn ($c) => trim((string) $c), $campos);
        }

        fclose($arquivo);

        return $linhas;
    }

    /** @return list<list<string>> */
    private static function lerXlsx(string $caminho): array
    {
        $zip = new ZipArchive;

        if ($zip->open($caminho) !== true) {
            throw new RuntimeException('Arquivo ilegivel: envie o xlsx exportado ou um csv.');
        }

        $textos = self::textosCompartilhados($zip->getFromName('xl/sharedStrings.xml') ?: '');
        $planilha = $zip->getFromName('xl/worksheets/sheet1.xml') ?: '';
        $zip->close();

        $linhas = [];

        preg_match_all('/<row[^>]*>(.*?)<\/row>/s', $planilha, $blocos);

        foreach ($blocos[1] as $bloco) {
            preg_match_all('/<c[^>]*r="([A-Z]+)\d+"([^>]*)>(.*?)<\/c>/s', $bloco, $celulas, PREG_SET_ORDER);
            $linha = [];

            foreach ($celulas as $celula) {
                $coluna = self::indiceDaColuna($celula[1]);
                $tipo = preg_match('/t="([^"]+)"/', $celula[2], $t) ? $t[1] : 'n';
                $valor = preg_match('/<v>(.*?)<\/v>/s', $celula[3], $v) ? $v[1] : '';

                if ($tipo === 's') {
                    $valor = $textos[(int) $valor] ?? '';
                } elseif ($tipo === 'inlineStr') {
                    $valor = preg_match('/<t[^>]*>(.*?)<\/t>/s', $celula[3], $s) ? $s[1] : '';
                }

                $linha[$coluna] = trim(html_entity_decode($valor, ENT_QUOTES | ENT_XML1, 'UTF-8'));
            }

            if ($linha !== []) {
                $linhas[] = array_map(fn ($i) => $linha[$i] ?? '', range(0, max(array_keys($linha))));
            }
        }

        return $linhas;
    }

    /** @return list<string> */
    private static function textosCompartilhados(string $xml): array
    {
        preg_match_all('/<si>(.*?)<\/si>/s', $xml, $itens);

        return array_map(function (string $item) {
            preg_match_all('/<t[^>]*>(.*?)<\/t>/s', $item, $partes);

            return html_entity_decode(implode('', $partes[1]), ENT_QUOTES | ENT_XML1, 'UTF-8');
        }, $itens[1]);
    }

    private static function ehZip(string $caminho): bool
    {
        $arquivo = fopen($caminho, 'rb');
        $assinatura = fread($arquivo, 2);
        fclose($arquivo);

        return $assinatura === 'PK';
    }

    private static function indiceDaColuna(string $letras): int
    {
        $indice = 0;

        foreach (str_split($letras) as $letra) {
            $indice = $indice * 26 + (ord($letra) - 64);
        }

        return $indice - 1;
    }

    private static function letraDaColuna(int $indice): string
    {
        $letras = '';

        for ($n = $indice + 1; $n > 0; $n = intdiv($n - 1, 26)) {
            $letras = chr(65 + (($n - 1) % 26)).$letras;
        }

        return $letras;
    }

    /** @param list<list<string|int|float|null>> $linhas */
    private static function aba(array $cabecalho, array $linhas): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';

        foreach (array_merge([$cabecalho], $linhas) as $numero => $linha) {
            $xml .= '<row r="'.($numero + 1).'">';

            foreach (array_values($linha) as $coluna => $valor) {
                $ref = self::letraDaColuna($coluna).($numero + 1);

                if ($valor === null || $valor === '') {
                    continue;
                }

                $xml .= is_int($valor) || is_float($valor)
                    ? '<c r="'.$ref.'"><v>'.$valor.'</v></c>'
                    : '<c r="'.$ref.'" t="inlineStr"><is><t xml:space="preserve">'
                        .htmlspecialchars((string) $valor, ENT_QUOTES | ENT_XML1, 'UTF-8').'</t></is></c>';
            }

            $xml .= '</row>';
        }

        return $xml.'</sheetData></worksheet>';
    }

    private static function tipos(int $abas): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>';

        for ($i = 1; $i <= $abas; $i++) {
            $xml .= '<Override PartName="/xl/worksheets/sheet'.$i.'.xml" '
                .'ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return $xml.'</Types>';
    }

    private static function relacaoRaiz(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Target="xl/workbook.xml" '
            .'Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument"/>'
            .'</Relationships>';
    }

    /** @param list<string> $nomes */
    private static function pasta(array $nomes): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';

        foreach ($nomes as $indice => $nome) {
            $xml .= '<sheet name="'.htmlspecialchars($nome, ENT_QUOTES | ENT_XML1, 'UTF-8')
                .'" sheetId="'.($indice + 1).'" r:id="rId'.($indice + 1).'"/>';
        }

        return $xml.'</sheets></workbook>';
    }

    private static function relacaoPasta(int $abas): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';

        for ($i = 1; $i <= $abas; $i++) {
            $xml .= '<Relationship Id="rId'.$i.'" Target="worksheets/sheet'.$i.'.xml" '
                .'Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet"/>';
        }

        return $xml.'</Relationships>';
    }
}
