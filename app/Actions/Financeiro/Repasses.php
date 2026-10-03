<?php

namespace App\Actions\Financeiro;

use App\Actions\Etiquetas\VendaNoRazao;
use App\Enums\NaturezaLancamento;
use App\Models\Consulta;
use App\Models\ContaAPagar;
use App\Models\Etiqueta;
use App\Models\Fatura;
use App\Models\LancamentoFinanceiro;
use App\Models\Socio;
use App\Models\Staff;
use App\Support\RepartePlaquinha;
use App\Support\SociosDaPlaquinha;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** O que a sexta-feira paga, lido de uma vez: comissoes dos dois produtos, pro-labore e contas. */
final class Repasses
{
    /** @return Collection<int, array{id: int, nome: string, pix: ?string, placas: int, cents: int}> */
    public static function comissoesSales(): Collection
    {
        $socios = SociosDaPlaquinha::resolver()['ids'];
        $porVendedor = [];

        foreach (Etiqueta::comissaoEmAberto()->whereNotNull('vendedor_id')->with('vendedor:id,nome,pix_chave')->get() as $venda) {
            $cents = VendaNoRazao::reparte($venda, $socios)['comissao'];

            if ($cents <= 0) {
                continue;
            }

            $id = (int) $venda->vendedor_id;
            $porVendedor[$id] ??= ['id' => $id, 'nome' => $venda->vendedor?->nome ?? 'Conta removida', 'pix' => $venda->vendedor?->pix_chave, 'placas' => 0, 'cents' => 0];
            $porVendedor[$id]['placas']++;
            $porVendedor[$id]['cents'] += $cents;
        }

        return collect($porVendedor)->sortByDesc('cents')->values();
    }

    /**
     * As comissoes de placa ja pagas, um lote por clique de Pagar: o lote e o
     * que tem o mesmo carimbo de hora e o mesmo vendedor.
     *
     * @return Collection<int, array{nome: string, quando: \Illuminate\Support\Carbon, placas: int, cents: int}>
     */
    public static function comissoesSalesPagas(int $limite = 24): Collection
    {
        $socios = SociosDaPlaquinha::resolver()['ids'];

        return Etiqueta::whereNotNull('comissao_paga_em')->with('vendedor:id,nome')
            ->orderByDesc('comissao_paga_em')->get()
            ->groupBy(fn (Etiqueta $e) => $e->vendedor_id.'|'.$e->comissao_paga_em->format('Y-m-d H:i:s'))
            ->map(fn (Collection $lote) => [
                'nome' => $lote->first()->vendedor?->nome ?? 'Conta removida',
                'quando' => $lote->first()->comissao_paga_em,
                'placas' => $lote->count(),
                'cents' => (int) $lote->sum(fn (Etiqueta $e) => VendaNoRazao::reparte($e, $socios)['comissao']),
            ])
            ->values()->take($limite);
    }

    /** @return Collection<int, array{id: int, nome: string, pix: ?string, faturas: int, liberado: int, demonstracoes: int, cents: int}> */
    public static function comissoesOne(): Collection
    {
        $liberadas = Fatura::whereNotNull('comissao_liberada_em')->whereNull('comissao_paga_em')->whereNotNull('vendedor_id')
            ->get(['vendedor_id', 'comissao_cents'])->groupBy('vendedor_id');
        $demos = Consulta::where('situacao', Consulta::SUCESSO)->whereNull('descontada_em')->whereNotNull('vendedor_id')
            ->get(['vendedor_id', 'custo_cents'])->groupBy('vendedor_id');
        $vendedores = Staff::whereIn('id', $liberadas->keys())->get(['id', 'nome', 'pix_chave'])->keyBy('id');

        return $liberadas->map(function (Collection $faturas, $id) use ($demos, $vendedores) {
            $liberado = (int) $faturas->sum('comissao_cents');
            $desconto = (int) ($demos->get($id)?->sum('custo_cents') ?? 0);

            return [
                'id' => (int) $id,
                'nome' => $vendedores->get($id)?->nome ?? 'Conta removida',
                'pix' => $vendedores->get($id)?->pix_chave,
                'faturas' => $faturas->count(),
                'liberado' => $liberado,
                'demonstracoes' => $desconto,
                'cents' => max(0, $liberado - $desconto),
            ];
        })->sortByDesc('cents')->values();
    }

    /**
     * O pro-labore de cada socio no mes: a parte dele no lucro das plaquinhas,
     * ja com a retencao, menos o que ja saiu como pro-labore na competencia.
     *
     * @return Collection<int, array{staff: Staff, socio: ?Socio, parte: int, prolabore: int, lancado: int, sugerido: int}>
     */
    public static function prolabore(Carbon $mes): Collection
    {
        $socios = SociosDaPlaquinha::resolver();
        $lucro = 0;

        foreach (Etiqueta::vendidasEntre($mes->copy()->startOfMonth(), $mes->copy()->endOfMonth())->get(['valor_cents', 'custo_cents', 'vendedor_id']) as $venda) {
            $lucro += VendaNoRazao::reparte($venda, $socios['ids'])['lucro'];
        }

        $partes = RepartePlaquinha::dividir($lucro, count($socios['ids']));
        $pct = (int) config('etiquetas.retencao_pct');
        $cadastro = Socio::where('ativo', true)->get()->keyBy('staff_id');

        return $socios['contas']->map(function (Staff $staff, int $posicao) use ($partes, $pct, $cadastro, $mes) {
            $parte = $partes[$posicao] ?? 0;
            $prolabore = RepartePlaquinha::retencao($parte, $pct)['prolabore'];
            $lancado = (int) LancamentoFinanceiro::daCompetencia($mes->format('Y-m'))
                ->where('natureza', NaturezaLancamento::Prolabore->value)->where('contraparte', $staff->nome)
                ->whereNull('estorna_id')->whereDoesntHave('estornos')
                ->with('partidas')->get()->sum(fn ($l) => $l->valorCents());

            return [
                'staff' => $staff,
                'socio' => $cadastro->get($staff->id),
                'parte' => $parte,
                'prolabore' => $prolabore,
                'lancado' => $lancado,
                'sugerido' => max(0, $prolabore - $lancado),
            ];
        });
    }

    /** @return Collection<int, ContaAPagar> */
    public static function contasAte(Carbon $data): Collection
    {
        return ContaAPagar::emAberto()->where('vence_em', '<=', $data->toDateString())->with('categoria')->orderBy('vence_em')->get();
    }
}
