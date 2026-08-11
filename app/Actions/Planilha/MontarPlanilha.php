<?php

namespace App\Actions\Planilha;

use App\Models\Catalogo;
use App\Models\Plano;
use App\Models\Servico;
use App\Support\Margem;
use App\Support\Planilha;
use Illuminate\Support\Collection;

/**
 * Monta a planilha do modulo, com as mesmas abas que a tela tem.
 *
 * A tela de Tabelas mostra o mesmo catalogo sob tres visoes (preco de venda,
 * custo do fornecedor, margem) e a planilha passa a espelhar isso: quem abre o
 * arquivo procura o que acabou de ver, e uma unica aba misturando tudo obrigava
 * a remontar a leitura no Excel.
 *
 * A aba de preco de venda vem primeiro e continua sendo a que a importacao le,
 * porque e nela que se reprecifica. As de custo e margem sao leitura: escrever
 * margem a mao nao faz sentido, ela e consequencia do preco.
 *
 * Os parametros ganham aba propria porque toda a coluna de margem depende
 * deles. Numero de margem sem o imposto e o alvo que o produziram nao se
 * confere: quem recebe a planilha por e-mail nao tem a tela ao lado.
 *
 * Uma planilha so, e nao um arquivo por tela: quem negocia preco com o
 * fornecedor precisa ver custo, catalogo e plano lado a lado.
 *
 * Servico pausado fica de fora. A planilha existe para negociar e reprecificar
 * o que a Avalia vende hoje, e linha de servico fora de venda so atrapalha a
 * leitura. Servico que aguarda liberacao continua aparecendo: ele esta em
 * negociacao, e e justamente sobre ele que se discute preco.
 *
 * Deixar de exportar nao apaga nada: a importacao so mexe nas linhas que
 * recebe, entao o preco do pausado continua guardado esperando ele voltar.
 */
class MontarPlanilha
{
    public function __invoke(): string
    {
        $catalogo = Catalogo::vigente();

        return Planilha::xlsx([
            // A primeira aba e a que a importacao le. Mexer nessa ordem sem
            // olhar o ImportarPlanilha quebra a volta em silencio.
            'Preco de venda' => $this->catalogo($catalogo),
            'Custo do fornecedor' => $this->custos($catalogo),
            'Margem' => $this->margens($catalogo),
            'Parametros' => $this->parametros($catalogo),
            'Planos' => $this->planos(),
            'Servicos' => $this->servicos(),
        ]);
    }

    /** Titulo da coluna de faixa, escrito para pessoa ler. */
    public static function tituloDaFaixa(int $faixa): string
    {
        return $faixa === 0 ? 'Sem mínimo' : 'Faixa '.number_format($faixa / 100, 2, ',', '.');
    }

    /**
     * Forma canonica de um titulo de coluna, usada so para casar escrita com
     * leitura.
     *
     * O cabecalho e escrito para pessoa ("Código", "Sem mínimo") e lido para
     * maquina. Sem esta normalizacao, acentuar o cabecalho quebraria a
     * importacao em silencio, e ninguem descobriria ate reimportar.
     */
    public static function chaveDaColuna(?string $titulo): string
    {
        $texto = mb_strtolower(trim(str_replace("\u{00A0}", ' ', (string) $titulo)));

        return strtr($texto, [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c',
        ]);
    }

    public static function situacao(Servico $servico): string
    {
        return match (true) {
            ! $servico->ativo => 'Pausado',
            $servico->exige_liberacao => 'Aguarda liberação',
            default => 'Disponível',
        };
    }

    /** Centavos viram numero com duas casas, para o Excel poder somar a coluna. */
    public static function reais(?int $centavos): ?float
    {
        return $centavos === null ? null : round($centavos / 100, 2);
    }

    /** @return array{0: list<string>, 1: list<list<string|int|float|null>>} */
    private function catalogo(?Catalogo $catalogo): array
    {
        if (! $catalogo) {
            return [['Código'], []];
        }

        $faixas = $catalogo->faixas();

        // Custo fica junto porque a importacao grava os dois de uma vez: quem
        // renegocia com o fornecedor mexe no custo e no preco na mesma linha.
        // As colunas de margem sairam daqui e viraram aba, para o arquivo ter
        // a mesma divisao da tela.
        $cabecalho = array_merge(
            ['Código', 'Serviço', 'Categoria', 'Situação', 'Fornecedor', 'Produto no fornecedor', 'Custo'],
            array_map(self::tituloDaFaixa(...), $faixas),
        );

        $linhas = $catalogo->precos()
            ->with('servico')
            ->whereHas('servico', fn ($q) => $q->where('ativo', true))
            ->get()
            ->groupBy('servico_id')
            ->map(function (Collection $precos) use ($faixas) {
                $servico = $precos->first()->servico;
                $porFaixa = $precos->keyBy('consumo_minimo_cents');

                return array_merge(
                    [
                        $servico->codigo,
                        $servico->nome,
                        $servico->rotuloCategoria(),
                        self::situacao($servico),
                        // De quem vem a linha, e qual produto pedir a ele. Sem
                        // os dois a consulta real nao sai, e preenche-los aqui
                        // evita abrir uma tela por servico.
                        $servico->fornecedor ?? '',
                        $servico->codigo_fornecedor ?? '',
                        self::reais($precos->first()->custo_cents),
                    ],
                    array_map(fn (int $f) => self::reais($porFaixa->get($f)?->preco_cents), $faixas),
                );
            })
            ->values()
            ->all();

        return [$cabecalho, $linhas];
    }

    /**
     * A aba de custo: o que a Avalia paga e quanto disso ocupa cada faixa.
     *
     * A porcentagem existe porque o custo em reais nao diz nada sozinho. Custo
     * de R$ 1,50 e barato num servico de R$ 6,00 e insustentavel num de R$ 1,80,
     * e e essa proporcao que se leva para negociar com o fornecedor.
     *
     * @return array{0: list<string>, 1: list<list<string|int|float|null>>}
     */
    private function custos(?Catalogo $catalogo): array
    {
        if (! $catalogo) {
            return [['Código'], []];
        }

        $faixas = $catalogo->faixas();

        $cabecalho = array_merge(
            ['Código', 'Serviço', 'Categoria', 'Custo'],
            array_map(fn (int $f) => self::tituloDaFaixa($f).' (% do preço)', $faixas),
        );

        $linhas = $this->porServico($catalogo, function (Servico $servico, Collection $porFaixa) use ($faixas) {
            $custo = $porFaixa->first()->custo_cents;

            return array_merge(
                [$servico->codigo, $servico->nome, $servico->rotuloCategoria(), self::reais($custo)],
                array_map(function (int $f) use ($porFaixa, $custo) {
                    $preco = $porFaixa->get($f)?->preco_cents;

                    return $custo === null || ! $preco ? null : round($custo * 100 / $preco, 1);
                }, $faixas),
            );
        });

        return [$cabecalho, $linhas];
    }

    /**
     * A aba de margem: o que sobra em cada faixa, e quanto falta para o alvo.
     *
     * A coluna do alvo vai ao lado de proposito. Margem sozinha e um numero que
     * cada leitor compara com um alvo diferente, guardado na cabeca de quem
     * definiu; com o alvo na linha, a conversa e sobre a diferenca.
     *
     * @return array{0: list<string>, 1: list<list<string|int|float|null>>}
     */
    private function margens(?Catalogo $catalogo): array
    {
        if (! $catalogo) {
            return [['Código'], []];
        }

        $faixas = $catalogo->faixas();
        $comissao = $catalogo->comissaoBps();
        $alvos = $catalogo->margemAlvoPorFaixa($faixas);

        $cabecalho = array_merge(
            ['Código', 'Serviço', 'Categoria'],
            array_map(fn (int $f) => self::tituloDaFaixa($f).' (%)', $faixas),
            ['Abaixo do alvo'],
        );

        $linhas = $this->porServico($catalogo, function (Servico $servico, Collection $porFaixa) use ($faixas, $catalogo, $comissao, $alvos) {
            $pct = fn (int $f) => ($p = $porFaixa->get($f))
                ? Margem::pct($p->preco_cents, $p->custo_cents, $catalogo->imposto_bps, $comissao)
                : null;

            $furadas = 0;

            foreach ($faixas as $faixa) {
                $atual = $pct($faixa);

                if ($atual !== null && $atual < ($alvos[$faixa] ?? 0) / 100) {
                    $furadas++;
                }
            }

            return array_merge(
                [$servico->codigo, $servico->nome, $servico->rotuloCategoria()],
                array_map($pct, $faixas),
                [$furadas === 0 ? '' : $furadas.' '.($furadas === 1 ? 'faixa' : 'faixas')],
            );
        });

        // A escada do alvo fecha a aba, para a coluna de margem ter contra o
        // que ser lida sem abrir o sistema.
        $linhas[] = array_merge(
            ['', 'MARGEM ALVO DA FAIXA', ''],
            array_map(fn (int $f) => round(($alvos[$f] ?? 0) / 100, 1), $faixas),
            [''],
        );

        return [$cabecalho, $linhas];
    }

    /**
     * Os parametros que produziram a coluna de margem.
     *
     * Sem eles a planilha nao se confere: quem recebe o arquivo por e-mail nao
     * tem a tela ao lado para saber com que imposto e que alvo aquela margem
     * foi calculada.
     *
     * @return array{0: list<string>, 1: list<list<string|int|float|null>>}
     */
    private function parametros(?Catalogo $catalogo): array
    {
        if (! $catalogo) {
            return [['Parâmetro', 'Valor'], []];
        }

        return [
            ['Parâmetro', 'Valor'],
            [
                ['Imposto sobre a venda', $catalogo->impostoRotulo()],
                ['Comissão do vendedor sobre o lucro', self::pontos($catalogo->comissaoBps())],
                ['Margem alvo na primeira faixa', $catalogo->margemAlvoRotulo()],
                ['Degrau de margem por faixa', $catalogo->degrauRotulo()],
                ['Tabela gerada em', now()->format('d/m/Y H:i')],
            ],
        ];
    }

    private static function pontos(int $bps): string
    {
        return number_format($bps / 100, 2, ',', '.').'%';
    }

    /**
     * Uma linha por servico ativo, com os precos indexados pela faixa.
     *
     * Existe para as tres abas do catalogo montarem a linha do mesmo jeito: sem
     * isso, cada aba filtrava e agrupava por conta propria e bastava uma delas
     * esquecer o `where ativo` para as abas discordarem sobre quantos servicos
     * existem.
     *
     * @param  callable(Servico, Collection): list<string|int|float|null>  $linha
     * @return list<list<string|int|float|null>>
     */
    private function porServico(Catalogo $catalogo, callable $linha): array
    {
        return $catalogo->precos()
            ->with('servico')
            ->whereHas('servico', fn ($q) => $q->where('ativo', true))
            ->get()
            ->groupBy('servico_id')
            ->map(fn (Collection $precos) => $linha(
                $precos->first()->servico,
                $precos->keyBy('consumo_minimo_cents'),
            ))
            ->values()
            ->all();
    }

    /** @return array{0: list<string>, 1: list<list<string|int|float|null>>} */
    private function planos(): array
    {
        $linhas = Plano::withCount('franquias')
            ->orderBy('consumo_minimo_cents')
            ->get()
            ->map(fn (Plano $plano) => [
                $plano->nome,
                self::reais($plano->consumo_minimo_cents),
                self::reais($plano->mensalidade_cents),
                self::reais($plano->faturaMinimaCents()),
                $plano->ativo ? 'Ativo' : 'Inativo',
                $plano->franquias_count,
            ])
            ->all();

        return [
            ['Plano', 'Consumo mínimo', 'Mensalidade', 'Fatura mínima', 'Situação', 'Serviços com franquia'],
            $linhas,
        ];
    }

    /** @return array{0: list<string>, 1: list<list<string|int|float|null>>} */
    private function servicos(): array
    {
        // Sem contagem de precos: todo servico tem uma linha por faixa, entao a
        // coluna marcava 7 em tudo. Numero igual em toda linha nao mede nada,
        // so ocupa espaco e faz o leitor procurar sentido onde nao ha.
        $linhas = Servico::query()
            ->where('ativo', true)
            ->orderBy('categoria')
            ->orderBy('nome')
            ->get()
            ->map(fn (Servico $servico) => [
                $servico->codigo,
                $servico->nome,
                $servico->rotuloCategoria(),
                self::situacao($servico),
                $servico->fornecedor ?? '',
                $servico->codigo_fornecedor ?? '',
            ])
            ->all();

        return [['Código', 'Serviço', 'Categoria', 'Situação', 'Fornecedor', 'Produto no fornecedor'], $linhas];
    }
}
