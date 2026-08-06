<?php

namespace App\Support;

use App\Models\Cliente;
use App\Models\Consulta;
use App\Models\Fatura;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * As duas listas que valem uma ligacao hoje.
 *
 * Empresa a caminho da suspensao e empresa que parou de consultar sao os dois
 * sinais que aparecem antes do problema. O primeiro custa cobranca, o segundo
 * custa o contrato inteiro, e os dois so servem enquanto ha tempo de agir: no
 * dia em que a suspensao acontece, a lista deixa de ser aviso e vira relatorio.
 *
 * Recebem um Builder ja recortado, como em FiltroConsultas: quem chama e que
 * decide se ve a operacao inteira ou uma carteira. Decidir visibilidade aqui
 * dentro deixaria a regra a um argumento de distancia de mostrar o cliente de
 * outro vendedor.
 */
final class Alertas
{
    /** Sem consulta ha este tanto de dias, a empresa entra na lista. */
    public const DIAS_SEM_CONSULTAR = 30;

    /**
     * Empresas com fatura vencida que ainda nao foram suspensas.
     *
     * A suspensao e automatica e acontece dias depois do vencimento. Entre um
     * momento e outro existe a janela em que uma ligacao ainda resolve, e e
     * exatamente essa janela que a lista mostra, ordenada por quem tem menos
     * tempo sobrando.
     *
     * @param  Builder<Fatura>  $faturas  ja recortado por quem pode ver
     * @return Collection<int, array{fatura: Fatura, dias: int}>
     */
    public static function aCaminhoDaSuspensao(Builder $faturas, ?\DateTimeInterface $agora = null): Collection
    {
        $agora = $agora ?? now();

        return $faturas
            ->with('cliente')
            ->where('situacao_pagamento', Fatura::PAGAMENTO_VENCIDO)
            ->get()
            // O prazo depende do vencimento, que e calculado a partir da
            // competencia, e nao de uma coluna: filtrar em SQL exigiria repetir
            // a regra do vencimento na consulta e deixa-la para divergir.
            ->filter(fn (Fatura $fatura) => $fatura->cliente?->situacao === 'ativo')
            ->map(fn (Fatura $fatura) => [
                'fatura' => $fatura,
                'dias' => (int) ceil(
                    ($fatura->vencimento()->addDays(Fatura::DIAS_ATE_BLOQUEIO)->getTimestamp() - $agora->getTimestamp()) / 86_400
                ),
            ])
            ->filter(fn (array $linha) => $linha['dias'] >= 0)
            ->sortBy('dias')
            ->values();
    }

    /**
     * Empresas ativas que nao consultam ha um tempo.
     *
     * Cliente que para de usar nao reclama: ele cancela na renovacao. Por isso a
     * lista existe, e por isso entra tambem quem nunca consultou, que e o caso
     * mais urgente de todos: contrato assinado e nunca usado nao vira renovacao.
     *
     * @param  Builder<Cliente>  $clientes  ja recortado por quem pode ver
     * @return Collection<int, array{cliente: Cliente, ultima: \Illuminate\Support\Carbon|null}>
     */
    public static function pararamDeConsultar(Builder $clientes, ?int $dias = null): Collection
    {
        $dias = $dias ?? self::DIAS_SEM_CONSULTAR;
        $corte = now()->subDays($dias);

        $ativos = $clientes->where('situacao', 'ativo')->orderBy('razao_social')->get();

        $ultimas = Consulta::query()
            ->whereIn('cliente_id', $ativos->pluck('id'))
            ->selectRaw('cliente_id, max(created_at) as ultima')
            ->groupBy('cliente_id')
            ->pluck('ultima', 'cliente_id');

        return $ativos
            ->map(fn (Cliente $cliente) => [
                'cliente' => $cliente,
                'ultima' => isset($ultimas[$cliente->id]) ? \Illuminate\Support\Carbon::parse($ultimas[$cliente->id]) : null,
            ])
            ->filter(fn (array $linha) => $linha['ultima'] === null || $linha['ultima']->lt($corte))
            ->sortBy(fn (array $linha) => $linha['ultima']?->getTimestamp() ?? 0)
            ->values();
    }
}
