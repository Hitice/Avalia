<?php

namespace App\Support;

/**
 * O desenho da marca: o arco do medidor em quatro faixas de magenta, e o
 * ponteiro na faixa alta.
 *
 * Vem do arquivo que o dono desenhou no Corel (public/marca/avaliaone.svg,
 * 02/10/2026), nas coordenadas dele, sem redesenho. O mesmo tracado sai daqui
 * para o logotipo da tela, o favicon e o miolo do QR; MarcaTest cobra que as
 * tres copias sejam esta.
 *
 * O ponteiro nao tem cor propria: no desenho ele e branco sobre fundo escuro,
 * e sobre fundo claro sumiria. Quem desenha diz a cor.
 */
final class Marca
{
    /** Um quadrado em volta do arco, com o arco centrado na vertical. */
    public const CAIXA = '907.99 -360.75 1500.6 1500.6';

    /** O eixo do ponteiro, para o medidor girar. */
    public const EIXO = '1509.11 715.91';

    /** Quanto o ponteiro desenhado esta acima de "apontando para a esquerda" (nivel zero). */
    public const VARREDURA_GRAUS = 152.86;

    /**
     * O mesmo arco no tema escuro, em azul: a escala da casa, uma faixa do
     * desenho para cada par de degraus. Magenta sobre fundo escuro e so no
     * site, que vive no tema claro.
     */
    private const NOITE = [
        ['#9cb9ff', '#dde9ff'],
        ['#7592ff', '#c2d6ff'],
        ['#3641f5', '#7592ff'],
        ['#2a31d8', '#465fff'],
    ];

    /** x1, y1, x2, y2 do degrade, cor inicial, cor final e o tracado. */
    private const FAIXAS = [
        ['834.39', '611.61', '1235.85', '404.28', '#f365e8', '#ffafef',
            'M907.99 754.12c0,-189.59 67.61,-364.49 179.31,-492.36l74.95 72.02c-123.45,102.88 -195.47,254.26 -201.35,420.34l-52.91 0z'],
        ['1182.23', '397.16', '1487.66', '-60.29', '#e827d9', '#ff72ef',
            'M1087.29 261.76c126.4,-142.56 302.77,-239.56 495.3,-258.67l0 207.23c-157.26,-17.63 -308.64,29.4 -420.35,123.46l-74.95 -72.02z'],
        ['1680.7', '463.91', '2051.79', '-68.4', '#bb00bf', '#f000e4',
            'M1582.59 3.09c207.23,-19.11 408.58,51.44 567.31,182.25l-207.24 210.17c-95.53,-110.23 -223.39,-173.43 -360.08,-185.19l0 -207.23z'],
        ['1896.8', '698.12', '2454.45', '241.34', '#9900aa', '#da00d1',
            'M2149.91 185.34c160.2,141.09 258.67,345.39 258.67,568.78l-324.81 0c-4.41,-138.15 -54.38,-261.61 -141.09,-358.61l207.24 -210.17z'],
    ];

    private const PONTEIRO = [
        'M1490 686.51l429.16 -180.77c5.88,-2.94 8.33,-0.98 7.35,5.88l-398.3 218.99 -38.21 -44.1z',
        'M1572.31 715.91c0,-34.9 -28.3,-63.2 -63.2,-63.2 -34.91,0 -63.2,28.3 -63.2,63.2 0,34.9 28.29,63.2 63.2,63.2 34.9,0 63.2,-28.3 63.2,-63.2z',
    ];

    /** O par de magenta da faixa principal, o mesmo do nome escrito. */
    public static function magenta(): array
    {
        return [self::FAIXAS[2][4], self::FAIXAS[2][5]];
    }

    /** O par de azul da faixa principal, para o nome escrito no tema escuro. */
    public static function noite(): array
    {
        return self::NOITE[2];
    }

    /** O prefixo evita id repetido quando a marca aparece duas vezes na pagina. */
    public static function arco(string $prefixo = 'marca'): string
    {
        return '<defs>'.self::degrades($prefixo).'</defs>'.self::faixas($prefixo);
    }

    /** As classes nos degraus sao o gancho do tema escuro, ver regrasDaNoite(). */
    public static function degrades(string $prefixo = 'marca'): string
    {
        $defs = '';

        foreach (self::FAIXAS as $i => [$x1, $y1, $x2, $y2, $de, $para]) {
            $n = $i + 1;
            $defs .= "<linearGradient id=\"{$prefixo}-{$n}\" gradientUnits=\"userSpaceOnUse\" x1=\"{$x1}\" y1=\"{$y1}\" x2=\"{$x2}\" y2=\"{$y2}\"><stop class=\"marca-de-{$n}\" offset=\"0\" stop-color=\"{$de}\"/><stop class=\"marca-para-{$n}\" offset=\"1\" stop-color=\"{$para}\"/></linearGradient>";
        }

        return $defs;
    }

    /** $extra recebe o numero da faixa (1 a 4) e devolve atributos a mais, como o estilo que o medidor anima. */
    public static function faixas(string $prefixo = 'marca', ?callable $extra = null): string
    {
        $faixas = '';

        foreach (self::FAIXAS as $i => $faixa) {
            $n = $i + 1;
            $faixas .= '<path d="'.$faixa[6].'" fill="url(#'.$prefixo.'-'.$n.')"'.($extra ? ' '.$extra($n) : '').'/>';
        }

        return $faixas;
    }

    /** As regras que pintam os degraus de azul, para app.css e para o favicon. */
    public static function regrasDaNoite(string $prefixo): string
    {
        $regras = '';

        foreach (self::NOITE as $i => [$de, $para]) {
            $n = $i + 1;
            $regras .= "{$prefixo}.marca-de-{$n}{stop-color:{$de}}{$prefixo}.marca-para-{$n}{stop-color:{$para}}";
        }

        return $regras;
    }

    public static function ponteiro(string $cor): string
    {
        return implode('', array_map(fn ($d) => "<path d=\"{$d}\" fill=\"{$cor}\"/>", self::PONTEIRO));
    }
}
