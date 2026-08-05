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
     * Largura extra do cabecalho, em caracteres, para o botao de filtro do
     * Excel nao cobrir a ultima letra do titulo.
     */
    private const FOLGA_DO_FILTRO = 5;

    /** Indices de cellXfs em styles.xml. Mudou a ordem la, muda aqui. */
    private const ESTILO_PADRAO = 0;

    private const ESTILO_CABECALHO = 1;

    private const ESTILO_DECIMAL = 2;

    private const ESTILO_INTEIRO = 3;

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
        $zip->addFromString('xl/styles.xml', self::estilos());

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
        $ultimaColuna = self::letraDaColuna(max(0, count($cabecalho) - 1));
        $ultimaLinha = count($linhas) + 1;
        $faixa = 'A1:'.$ultimaColuna.$ultimaLinha;

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<dimension ref="'.$faixa.'"/>'
            // Cabecalho congelado: rolar 43 servicos sem perder de vista qual
            // coluna e qual faixa.
            .'<sheetViews><sheetView workbookViewId="0">'
            .'<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>'
            .'<selection pane="bottomLeft" activeCell="A2" sqref="A2"/>'
            .'</sheetView></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="15"/>'
            .self::colunas($cabecalho, $linhas)
            .'<sheetData>';

        foreach (array_merge([$cabecalho], $linhas) as $numero => $linha) {
            $ehCabecalho = $numero === 0;

            $xml .= $ehCabecalho
                ? '<row r="1" ht="24" customHeight="1">'
                : '<row r="'.($numero + 1).'">';

            foreach (array_values($linha) as $coluna => $valor) {
                $ref = self::letraDaColuna($coluna).($numero + 1);

                if ($valor === null || $valor === '') {
                    continue;
                }

                if (is_int($valor) || is_float($valor)) {
                    // Dinheiro e margem chegam como float e ganham duas casas;
                    // contagem e inteiro e nao ganha casa decimal nenhuma.
                    $estilo = is_float($valor) ? self::ESTILO_DECIMAL : self::ESTILO_INTEIRO;
                    $xml .= '<c r="'.$ref.'" s="'.$estilo.'"><v>'.$valor.'</v></c>';

                    continue;
                }

                $xml .= '<c r="'.$ref.'" s="'.($ehCabecalho ? self::ESTILO_CABECALHO : self::ESTILO_PADRAO).'" t="inlineStr">'
                    .'<is><t xml:space="preserve">'
                    .htmlspecialchars((string) $valor, ENT_QUOTES | ENT_XML1, 'UTF-8')
                    .'</t></is></c>';
            }

            $xml .= '</row>';
        }

        // O autoFilter vai depois do sheetData: e a ordem que o formato exige.
        return $xml.'</sheetData><autoFilter ref="'.$faixa.'"/></worksheet>';
    }

    /**
     * Largura de cada coluna, medida pelo conteudo mais longo dela.
     *
     * Sem isso o Excel abre tudo com 8,43 de largura e "margem maior faixa"
     * aparece cortado, ou pior, o numero vira "#####". A unidade e largura de
     * caractere, entao contar caractere e a medida certa.
     *
     * @param  list<string>  $cabecalho
     * @param  list<list<string|int|float|null>>  $linhas
     */
    private static function colunas(array $cabecalho, array $linhas): string
    {
        if ($cabecalho === []) {
            return '';
        }

        $xml = '<cols>';

        foreach (array_keys($cabecalho) as $coluna) {
            // O botao de filtro fica dentro da celula do cabecalho e cobre o
            // fim do texto. Por isso o titulo pede folga maior que o dado.
            $maior = mb_strlen((string) $cabecalho[$coluna]) + self::FOLGA_DO_FILTRO;

            foreach ($linhas as $linha) {
                $valor = array_values($linha)[$coluna] ?? null;

                $texto = is_float($valor)
                    ? number_format($valor, 2, ',', '.')
                    : (string) $valor;

                $maior = max($maior, mb_strlen($texto) + 2);
            }

            // Teto para que uma observacao longa nao empurre as outras colunas
            // para fora da tela.
            $largura = min(52, max(12, $maior));

            $xml .= '<col min="'.($coluna + 1).'" max="'.($coluna + 1).'" width="'.$largura.'" customWidth="1"/>';
        }

        return $xml.'</cols>';
    }

    /**
     * Estilos do arquivo, na ordem em que cellXfs os referencia.
     *
     * Os dois primeiros fills sao obrigatorios pelo formato ("none" e
     * "gray125") mesmo sem uso: o Excel recusa a planilha se faltarem.
     */
    private static function estilos(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<numFmts count="1"><numFmt numFmtId="164" formatCode="#,##0.00"/></numFmts>'
            .'<fonts count="2">'
            .'<font><sz val="11"/><color theme="1"/><name val="Calibri"/><family val="2"/></font>'
            .'<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/><family val="2"/></font>'
            .'</fonts>'
            .'<fills count="3">'
            .'<fill><patternFill patternType="none"/></fill>'
            .'<fill><patternFill patternType="gray125"/></fill>'
            // Azul institucional, mais fechado que o indigo da tela: em
            // planilha de credito que circula com fornecedor e contador, o tom
            // escuro le melhor impresso e nao briga com o branco do cabecalho.
            .'<fill><patternFill patternType="solid"><fgColor rgb="FF1F4E79"/><bgColor indexed="64"/></patternFill></fill>'
            .'</fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="4">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" '
            .'applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            .'<xf numFmtId="3" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            .'</cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
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

        $xml .= '<Override PartName="/xl/styles.xml" '
            .'ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';

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

        // Vem depois das abas para nao disputar rId com elas.
        $xml .= '<Relationship Id="rId'.($abas + 1).'" Target="styles.xml" '
            .'Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles"/>';

        return $xml.'</Relationships>';
    }
}
