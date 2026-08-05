<?php

namespace App\Actions\Planilha;

use App\Models\Catalogo;
use App\Models\Plano;
use App\Models\Servico;
use App\Support\Margem;
use App\Support\Planilha;
use Illuminate\Support\Collection;

/**
 * Monta a planilha do modulo: catalogo, planos e servicos em tres abas.
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
        return Planilha::xlsx([
            'Catalogo' => $this->catalogo(Catalogo::vigente()),
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
        $comissao = $catalogo->comissaoBps();

        $cabecalho = array_merge(
            ['Código', 'Serviço', 'Categoria', 'Situação', 'Custo'],
            array_map(self::tituloDaFaixa(...), $faixas),
            ['Margem menor faixa (%)', 'Margem maior faixa (%)'],
        );

        $linhas = $catalogo->precos()
            ->with('servico')
            ->whereHas('servico', fn ($q) => $q->where('ativo', true))
            ->get()
            ->groupBy('servico_id')
            ->map(function (Collection $precos) use ($faixas, $catalogo, $comissao) {
                $servico = $precos->first()->servico;
                $porFaixa = $precos->keyBy('consumo_minimo_cents');

                $margem = fn (int $faixa) => ($p = $porFaixa->get($faixa))
                    ? Margem::pct($p->preco_cents, $p->custo_cents, $catalogo->imposto_bps, $comissao)
                    : null;

                return array_merge(
                    [
                        $servico->codigo,
                        $servico->nome,
                        $servico->rotuloCategoria(),
                        self::situacao($servico),
                        self::reais($precos->first()->custo_cents),
                    ],
                    array_map(fn (int $f) => self::reais($porFaixa->get($f)?->preco_cents), $faixas),
                    [$margem($faixas[0] ?? 0), $margem($faixas[count($faixas) - 1] ?? 0)],
                );
            })
            ->values()
            ->all();

        return [$cabecalho, $linhas];
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
            ])
            ->all();

        return [['Código', 'Serviço', 'Categoria', 'Situação'], $linhas];
    }
}
