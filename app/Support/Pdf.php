<?php

namespace App\Support;

/**
 * Gerador de PDF sem dependencia externa, irmao da Planilha.
 *
 * A hospedagem desabilita proc_open, entao qualquer conversor que chame um
 * navegador ou binario externo esta fora. E biblioteca de composer para isto
 * seria a primeira dependencia de arquivo do projeto, que ate aqui gera XLSX a
 * mao. Um PDF de texto corrido e um formato pequeno: objetos, paginas, duas
 * fontes padrao e streams de texto.
 *
 * O que este gerador faz, e so o que ele faz: texto em Helvetica (normal e
 * negrito), quebra de linha por largura real dos caracteres, paginacao com
 * rodape. E o suficiente para documento juridico de texto, que e o caso de
 * uso. Imagem, tabela e fonte propria ficam de fora ate existir necessidade.
 *
 * Acentos: as fontes padrao do PDF usam WinAnsi (CP1252), que cobre o
 * portugues inteiro. O texto e convertido na saida; para MEDIR largura, o
 * caractere acentuado usa a largura do caractere base, que em Helvetica e a
 * mesma na pratica.
 */
final class Pdf
{
    private const LARGURA = 595.28;   // A4 em pontos

    private const ALTURA = 841.89;

    private const MARGEM = 56.0;

    private const RODAPE_ALTURA = 30.0;

    /** Larguras Helvetica em milesimos de em, do espaco (32) ao til (126). */
    private const LARGURAS = [
        278, 278, 355, 556, 556, 889, 667, 191, 333, 333, 389, 584, 278, 333, 278, 278,
        556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 278, 278, 584, 584, 584, 556,
        1015, 667, 667, 722, 722, 667, 611, 778, 722, 278, 500, 667, 556, 833, 722, 778,
        667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 278, 278, 278, 469, 556,
        333, 556, 556, 500, 556, 556, 278, 556, 556, 222, 222, 500, 222, 833, 556, 556,
        556, 556, 333, 500, 278, 556, 500, 722, 500, 500, 500, 334, 260, 334, 584,
    ];

    /** @var list<string> conteudo de cada pagina, em operadores de texto */
    private array $paginas = [];

    private string $atual = '';

    private float $y;

    private string $rodape = '';

    /**
     * A marca desenhada no topo, se houver.
     *
     * @var array{dados: string, largura: int, altura: int}|null
     */
    private ?array $marca = null;

    public function __construct()
    {
        $this->y = self::ALTURA - self::MARGEM;
    }

    /** Linha discreta repetida ao pe de toda pagina, junto do numero. */
    public function rodape(string $texto): static
    {
        $this->rodape = $texto;

        return $this;
    }

    /**
     * A marca no topo da pagina, a partir de um JPEG.
     *
     * JPEG, e nao PNG, porque o PDF embute JPEG como esta (DCTDecode): sao
     * poucas linhas de dicionario e o arquivo entra intacto. PNG exigiria
     * refazer a compressao com preditor, que e trabalho de biblioteca e nao
     * cabe num gerador que existe justamente para nao ter dependencia.
     *
     * A largura e em pontos; a altura sai da proporcao real do arquivo, para a
     * marca nunca sair esticada.
     */
    public function marca(string $caminho, float $largura = 118.0): static
    {
        $dados = @file_get_contents($caminho);
        $tamanho = $dados === false ? false : @getimagesizefromstring($dados);

        // Sem a imagem o documento continua valido, e o texto seguinte ja
        // identifica a Avalia. Documento que deixa de sair por causa de um
        // arquivo de logo seria pior do que um documento sem logo.
        if ($dados === false || $tamanho === false || ($tamanho[2] ?? null) !== IMAGETYPE_JPEG) {
            return $this;
        }

        $altura = $largura * $tamanho[1] / $tamanho[0];
        $this->marca = ['dados' => $dados, 'largura' => (int) $tamanho[0], 'altura' => (int) $tamanho[1]];

        $this->y -= $altura;
        $this->atual .= sprintf(
            'q %.2F 0 0 %.2F %.2F %.2F cm /Marca Do Q
',
            $largura, $altura, self::MARGEM, $this->y,
        );

        return $this->espaco(16);
    }

    public function titulo(string $texto): static
    {
        $this->escrever($texto, 16, true, 0.11);
        $this->espaco(6);

        return $this;
    }

    /** Linha de contexto sob o titulo: versao, data, identificacao. */
    public function meta(string $texto): static
    {
        $this->escrever($texto, 9, false, 0.45);

        return $this;
    }

    /** Azul da marca (brand-800), para as barras de secao. */
    private const AZUL = [0.145, 0.176, 0.682];

    /**
     * Titulo de secao numa barra azul, texto branco.
     *
     * A barra e o separador visual do documento: quem folheia acha as secoes
     * pela cor antes de ler qualquer palavra, que e como os relatorios de
     * mercado se navegam. E como a barra abre uma secao, ela nunca fica orfa
     * no pe da pagina: sem espaco para ela mais duas linhas, quebra antes.
     */
    public function secao(string $texto): static
    {
        $this->garantir(70);
        $this->espaco(12);

        $altura = 20.0;
        $this->y -= $altura;

        $this->atual .= sprintf(
            'q %.3F %.3F %.3F rg %.2F %.2F %.2F %.2F re f Q
',
            self::AZUL[0], self::AZUL[1], self::AZUL[2],
            self::MARGEM, $this->y, self::LARGURA - 2 * self::MARGEM, $altura,
        );

        // O texto centrado na barra, em branco.
        $this->atual .= sprintf(
            'BT /F2 11.00 Tf 1 1 1 rg %.2F %.2F Td (%s) Tj ET
',
            self::MARGEM + 8, $this->y + 6, $this->escapar($texto),
        );

        $this->espaco(8);

        return $this;
    }

    /**
     * Garante espaco vertical na pagina, quebrando antes se preciso.
     *
     * E o que impede um bloco de sair cortado no meio: quem vai escrever um
     * conjunto que deve ficar junto pede o tamanho antes, e o corte acontece
     * ENTRE blocos, nunca dentro.
     */
    public function garantir(float $pontos): static
    {
        if ($this->y - min($pontos, 600) < self::MARGEM + self::RODAPE_ALTURA) {
            $this->fecharPagina();
            $this->y = self::ALTURA - self::MARGEM;
        }

        return $this;
    }

    public function paragrafo(string $texto): static
    {
        $this->escrever($texto, 10.5, false, 0.2);
        $this->espaco(7);

        return $this;
    }

    /**
     * Corpo menor, para o rodape legal do documento.
     *
     * Menor de proposito e sem prejuizo de leitura: 8,5pt ainda se le no papel,
     * e o tamanho comunica hierarquia. A ressalva precisa estar la e precisa
     * ser encontravel, mas nao pode competir com o resultado, que e o que a
     * pessoa abriu o documento para ver.
     */
    public function nota(string $texto): static
    {
        $this->escrever($texto, 8.5, false, 0.42);
        $this->espaco(4);

        return $this;
    }

    /**
     * Rotulo a esquerda e valor encostado na margem direita, na mesma linha.
     *
     * E o unico formato de tabela que este gerador conhece, e existe porque
     * documento de dinheiro se le pela coluna de valores: rotulo e valor no
     * mesmo paragrafo obrigam quem confere a procurar o numero no meio do
     * texto. Rotulo comprido quebra e o valor acompanha a primeira linha.
     */
    public function linha(string $rotulo, string $valor, bool $destaque = false): static
    {
        $tamanho = $destaque ? 11.5 : 10.5;
        $cinza = $destaque ? 0.11 : 0.3;
        $entrelinha = $tamanho * 1.45;

        // O valor tem prioridade: o rotulo quebra no espaco que sobrar dele.
        $larguraValor = $this->largura($valor, $tamanho, $destaque);
        $larguraRotulo = self::LARGURA - 2 * self::MARGEM - $larguraValor - 18;

        $linhas = $this->quebrar($rotulo, $tamanho, false, max(60.0, $larguraRotulo));

        foreach ($linhas as $i => $texto) {
            if ($this->y - $entrelinha < self::MARGEM + self::RODAPE_ALTURA) {
                $this->fecharPagina();
                $this->y = self::ALTURA - self::MARGEM;
            }

            $this->y -= $entrelinha;
            $this->texto($texto, self::MARGEM, $tamanho, $destaque, $cinza);

            // O valor sai uma vez so, alinhado a direita na primeira linha.
            if ($i === 0) {
                $this->texto(
                    $valor,
                    self::LARGURA - self::MARGEM - $larguraValor,
                    $tamanho, $destaque, $cinza,
                );
            }
        }

        return $this;
    }

    /** Risco horizontal fino, para fechar um bloco de valores. */
    public function divisoria(): static
    {
        $this->y -= 6;
        $this->atual .= sprintf(
            "0.82 0.82 0.82 RG 0.6 w %.2F %.2F m %.2F %.2F l S\n",
            self::MARGEM, $this->y, self::LARGURA - self::MARGEM, $this->y,
        );
        $this->y -= 2;

        return $this;
    }

    public function espaco(float $pontos): static
    {
        $this->y -= $pontos;

        return $this;
    }

    /**
     * O fecho do documento: as notas legais ancoradas no PE da ultima pagina,
     * com a marca reduzida e a identificacao da casa.
     *
     * Ancorado, e nao "depois do conteudo": nota legal que flutua logo abaixo
     * do ultimo bloco fica no meio da pagina, e o leitor de relatorio procura
     * por ela no rodape, porque e onde todo relatorio de mercado a poe. O
     * bloco e medido antes; se nao couber inteiro no espaco que sobrou, vai
     * INTEIRO para a pagina seguinte, nunca cortado no meio.
     *
     * @param  list<string>  $notas
     */
    public function fecho(string $titulo, array $notas, string $assinatura = ''): static
    {
        $larguraUtil = self::LARGURA - 2 * self::MARGEM;

        // Mede tudo primeiro: barra (28), cada nota (linhas de 8,5pt + folga),
        // e a faixa da assinatura com a marca reduzida (34).
        $altura = 40.0;

        foreach ($notas as $nota) {
            $altura += count($this->quebrar($nota, 8.5, false, $larguraUtil)) * 8.5 * 1.45 + 4;
        }

        if ($assinatura !== '' || $this->marca) {
            $altura += 40;
        }

        if ($this->y - $altura < self::MARGEM + self::RODAPE_ALTURA) {
            $this->fecharPagina();
            $this->y = self::ALTURA - self::MARGEM;
        }

        // Desce ate o ponto em que o bloco TERMINA rente ao rodape.
        $this->y = self::MARGEM + self::RODAPE_ALTURA + $altura;

        $this->secaoSemQuebra($titulo);

        foreach ($notas as $nota) {
            $this->escrever($nota, 8.5, false, 0.42);
            $this->espaco(4);
        }

        // A faixa final: marca pequena a esquerda, identificacao ao lado.
        if ($this->marca) {
            $larguraMarca = 54.0;
            $alturaMarca = $larguraMarca * $this->marca['altura'] / $this->marca['largura'];
            $this->espaco(10);
            $this->y -= $alturaMarca;

            $this->atual .= sprintf(
                'q %.2F 0 0 %.2F %.2F %.2F cm /Marca Do Q
',
                $larguraMarca, $alturaMarca, self::MARGEM, $this->y,
            );

            if ($assinatura !== '') {
                $this->atual .= sprintf(
                    'BT /F1 8 Tf 0.45 0.45 0.45 rg %.2F %.2F Td (%s) Tj ET
',
                    self::MARGEM + $larguraMarca + 10, $this->y + $alturaMarca / 2 - 3,
                    $this->escapar($assinatura),
                );
            }
        } elseif ($assinatura !== '') {
            $this->espaco(10);
            $this->escrever($assinatura, 8, false, 0.45);
        }

        return $this;
    }

    /** A mesma barra azul da secao, sem o teste de quebra: o fecho ja mediu. */
    private function secaoSemQuebra(string $texto): void
    {
        $altura = 20.0;
        $this->y -= $altura;

        $this->atual .= sprintf(
            'q %.3F %.3F %.3F rg %.2F %.2F %.2F %.2F re f Q
',
            self::AZUL[0], self::AZUL[1], self::AZUL[2],
            self::MARGEM, $this->y, self::LARGURA - 2 * self::MARGEM, $altura,
        );
        $this->atual .= sprintf(
            'BT /F2 11.00 Tf 1 1 1 rg %.2F %.2F Td (%s) Tj ET
',
            self::MARGEM + 8, $this->y + 6, $this->escapar($texto),
        );
        $this->espaco(8);
    }

    /** O arquivo pronto, byte a byte. */
    public function bytes(): string
    {
        $this->fecharPagina();

        $n = count($this->paginas);
        $objetos = [];

        // 1 catalogo, 2 arvore de paginas, 3 e 4 fontes; paginas e conteudos
        // vem depois, em pares.
        $kids = [];
        for ($i = 0; $i < $n; $i++) {
            $kids[] = (5 + $i * 2).' 0 R';
        }

        $objetos[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objetos[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.$n.' >>';
        $objetos[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objetos[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

        // A imagem entra DEPOIS das paginas para nao mexer na numeracao delas,
        // que e calculada por posicao.
        $marca = $this->marca ? 5 + $n * 2 : null;
        $recursoDaMarca = $marca ? ' /XObject << /Marca '.$marca.' 0 R >>' : '';

        foreach ($this->paginas as $i => $conteudo) {
            $rodape = $this->operadoresDoRodape($i + 1, $n);
            $stream = $conteudo.$rodape;
            $pagina = 5 + $i * 2;

            $objetos[$pagina] = '<< /Type /Page /Parent 2 0 R'
                .' /MediaBox [0 0 '.self::LARGURA.' '.self::ALTURA.']'
                .' /Resources << /Font << /F1 3 0 R /F2 4 0 R >>'.$recursoDaMarca.' >>'
                .' /Contents '.($pagina + 1).' 0 R >>';
            $objetos[$pagina + 1] = '<< /Length '.strlen($stream)." >>\nstream\n".$stream.'endstream';
        }

        if ($marca) {
            $objetos[$marca] = '<< /Type /XObject /Subtype /Image'
                .' /Width '.$this->marca['largura'].' /Height '.$this->marca['altura']
                .' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode'
                .' /Length '.strlen($this->marca['dados']).' >>
stream
'.$this->marca['dados'].'endstream';
        }

        $corpo = "%PDF-1.4\n";
        $posicoes = [];

        foreach ($objetos as $num => $objeto) {
            $posicoes[$num] = strlen($corpo);
            $corpo .= $num." 0 obj\n".$objeto."\nendobj\n";
        }

        $inicioXref = strlen($corpo);
        $total = count($objetos) + 1;

        $corpo .= "xref\n0 {$total}\n0000000000 65535 f \n";
        for ($num = 1; $num < $total; $num++) {
            $corpo .= sprintf("%010d 00000 n \n", $posicoes[$num]);
        }

        $corpo .= "trailer\n<< /Size {$total} /Root 1 0 R >>\nstartxref\n{$inicioXref}\n%%EOF";

        return $corpo;
    }

    /** Escreve um bloco com quebra automatica, paginando quando acabar o espaco. */
    private function escrever(string $texto, float $tamanho, bool $negrito, float $cinza): void
    {
        $larguraUtil = self::LARGURA - 2 * self::MARGEM;
        $entrelinha = $tamanho * 1.45;

        foreach ($this->quebrar($texto, $tamanho, $negrito, $larguraUtil) as $linha) {
            if ($this->y - $entrelinha < self::MARGEM + self::RODAPE_ALTURA) {
                $this->fecharPagina();
                $this->y = self::ALTURA - self::MARGEM;
            }

            $this->y -= $entrelinha;
            $this->texto($linha, self::MARGEM, $tamanho, $negrito, $cinza);
        }
    }

    /** Um pedaco de texto numa posicao horizontal, na altura corrente. */
    private function texto(string $texto, float $x, float $tamanho, bool $negrito, float $cinza): void
    {
        $this->atual .= sprintf(
            "BT /%s %.2F Tf %.2F %.2F %.2F rg %.2F %.2F Td (%s) Tj ET\n",
            $negrito ? 'F2' : 'F1', $tamanho, $cinza, $cinza, $cinza, $x, $this->y,
            $this->escapar($texto),
        );
    }

    /** @return list<string> */
    private function quebrar(string $texto, float $tamanho, bool $negrito, float $larguraUtil): array
    {
        $linhas = [];
        $linha = '';

        foreach (preg_split('/\s+/u', trim($texto)) ?: [] as $palavra) {
            $tentativa = $linha === '' ? $palavra : $linha.' '.$palavra;

            if ($this->largura($tentativa, $tamanho, $negrito) <= $larguraUtil || $linha === '') {
                $linha = $tentativa;

                continue;
            }

            $linhas[] = $linha;
            $linha = $palavra;
        }

        if ($linha !== '') {
            $linhas[] = $linha;
        }

        return $linhas;
    }

    private function largura(string $texto, float $tamanho, bool $negrito): float
    {
        // Para medir, o acentuado vale como o base: em Helvetica as larguras
        // coincidem. O negrito e ~6% mais largo que o normal.
        $base = iconv('UTF-8', 'ASCII//TRANSLIT', $texto) ?: $texto;
        $soma = 0;

        foreach (str_split($base) as $c) {
            $codigo = ord($c);
            $soma += self::LARGURAS[$codigo - 32] ?? 556;
        }

        return $soma / 1000 * $tamanho * ($negrito ? 1.06 : 1.0);
    }

    private function escapar(string $texto): string
    {
        $cp1252 = mb_convert_encoding($texto, 'Windows-1252', 'UTF-8');

        return strtr($cp1252, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)', "\r" => '', "\n" => '']);
    }

    private function operadoresDoRodape(int $pagina, int $total): string
    {
        $texto = trim($this->rodape.'   ·   Página '.$pagina.' de '.$total, ' ·');

        return sprintf(
            "BT /F1 8 Tf 0.55 0.55 0.55 rg %.2F %.2F Td (%s) Tj ET\n",
            self::MARGEM, self::MARGEM - 14, $this->escapar($texto),
        );
    }

    private function fecharPagina(): void
    {
        $this->paginas[] = $this->atual;
        $this->atual = '';
    }
}
