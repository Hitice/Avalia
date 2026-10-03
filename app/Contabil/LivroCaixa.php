<?php

namespace App\Contabil;

use App\Models\ContaFinanceira;
use App\Models\LancamentoFinanceiro;
use App\Models\PartidaFinanceira;
use Illuminate\Support\Collection;

/**
 * O livro-caixa: o que entrou e saiu do dinheiro da casa, com saldo linha a
 * linha. Le do razao (as pernas nas contas de caixa) e nao do documento, como
 * todo relatorio. Clientes a receber e ativo, mas nao e dinheiro: fica fora.
 */
final class LivroCaixa
{
    /** Codigos de ativo que nao sao dinheiro em conta. */
    private const NAO_E_DINHEIRO = ['clientes-a-receber'];

    public static function ehDinheiro(ContaFinanceira $conta): bool
    {
        return $conta->grupo === 'ativo' && ! in_array($conta->codigo, self::NAO_E_DINHEIRO, true);
    }

    /**
     * Os movimentos do mes, do mais recente ao mais antigo, cada um com entrada,
     * saida e o saldo depois dele; mais os totais.
     *
     * @param  Collection<int, LancamentoFinanceiro>  $doMes  com partidas.conta
     * @return array{movimentos: Collection<int, array<string, mixed>>, entradas: int, saidas: int, anterior: int}
     */
    public static function doMes(Collection $doMes, string $competencia): array
    {
        $saldo = self::saldoAntesDe($competencia);
        $anterior = $saldo;
        $entradas = $saidas = 0;
        $movimentos = collect();

        // Estorno e original no mesmo mes nao sao dinheiro que entrou e saiu: sao
        // a mesma venda corrigida, e a conta nao viu nada passar. O relancamento
        // das plaquinhas inflava entradas e saidas em pares que se anulavam.
        $ids = $doMes->pluck('id');
        $desfeitos = $doMes->filter(fn (LancamentoFinanceiro $l) => $l->estorna_id !== null && $ids->contains($l->estorna_id));
        $fora = $desfeitos->pluck('id')->merge($desfeitos->pluck('estorna_id'));

        foreach ($doMes->reject(fn (LancamentoFinanceiro $l) => $fora->contains($l->id))->sortBy([['ocorrido_em', 'asc'], ['id', 'asc']]) as $lancamento) {
            $cents = self::movimento($lancamento);

            if ($cents === 0) {
                continue;
            }

            $saldo += $cents;
            $cents > 0 ? $entradas += $cents : $saidas -= $cents;

            $movimentos->push([
                'lancamento' => $lancamento,
                'categoria' => self::categoria($lancamento),
                'entrada' => max(0, $cents),
                'saida' => max(0, -$cents),
                'saldo' => $saldo,
            ]);
        }

        return ['movimentos' => $movimentos->reverse()->values(), 'entradas' => $entradas, 'saidas' => $saidas, 'anterior' => $anterior];
    }

    /**
     * Receita e despesa do mes por conta, para dizer com o que se gastou.
     *
     * @return Collection<int, array{nome: string, grupo: string, cents: int}>
     */
    public static function porCategoria(Collection $doMes): Collection
    {
        return $doMes->flatMap(fn (LancamentoFinanceiro $l) => $l->partidas)
            ->filter(fn (PartidaFinanceira $p) => in_array($p->conta->grupo, ['receita', 'despesa'], true))
            ->groupBy('conta_id')
            ->map(fn (Collection $partidas) => [
                'nome' => $partidas->first()->conta->nome,
                'grupo' => $partidas->first()->conta->grupo,
                'cents' => (int) $partidas->sum(fn (PartidaFinanceira $p) => $p->conta->grupo === 'despesa' ? $p->valor_cents : -$p->valor_cents),
            ])
            ->filter(fn (array $linha) => $linha['cents'] !== 0)
            ->sortBy([['grupo', 'desc'], ['cents', 'desc']])
            ->values();
    }

    /** Quanto o lancamento moveu no dinheiro: positivo entrou, negativo saiu, zero nao passou pelo caixa. */
    private static function movimento(LancamentoFinanceiro $lancamento): int
    {
        return (int) $lancamento->partidas
            ->filter(fn (PartidaFinanceira $p) => self::ehDinheiro($p->conta))
            ->sum('valor_cents');
    }

    /** A conta de resultado (ou a outra ponta) que explica o movimento. */
    private static function categoria(LancamentoFinanceiro $lancamento): string
    {
        $outra = $lancamento->partidas->first(fn (PartidaFinanceira $p) => ! self::ehDinheiro($p->conta));

        return $outra?->conta->nome ?? '';
    }

    /** O dinheiro em conta hoje: toda perna nas contas de dinheiro, de sempre. */
    public static function saldoAtual(): int
    {
        return (int) PartidaFinanceira::whereIn('conta_id', self::contasDeDinheiro())->sum('valor_cents');
    }

    private static function saldoAntesDe(string $competencia): int
    {
        return (int) PartidaFinanceira::whereIn('conta_id', self::contasDeDinheiro())
            ->whereHas('lancamento', fn ($q) => $q->where('competencia', '<', $competencia))
            ->sum('valor_cents');
    }

    private static function contasDeDinheiro(): Collection
    {
        return ContaFinanceira::where('grupo', 'ativo')->whereNotIn('codigo', self::NAO_E_DINHEIRO)->pluck('id');
    }
}
