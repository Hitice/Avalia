<?php

namespace App\Actions\Planilha;

use App\Models\Fatura;
use App\Support\Documento;
use App\Support\Planilha;
use App\Support\Rotulos;
use Illuminate\Support\Collection;

/**
 * As faturas do recorte numa planilha, para conciliar fora do sistema.
 *
 * Esta e a unica exportacao do projeto que leva numero interno, e a diferenca
 * e o destinatario: ela nao sai da administracao. E a planilha que vai para o
 * contador e para a conciliacao bancaria, e sem custo e comissao ela nao serve
 * para nenhuma das duas coisas.
 *
 * Por isso o nome do arquivo diz "interno" e a exportacao entra na trilha. Quem
 * exporta precisa saber, ao salvar, que aquele arquivo nao pode ser encaminhado
 * a cliente nem a vendedor (PDD.md, secao 6).
 */
class MontarPlanilhaFaturas
{
    /** @param  Collection<int, Fatura>  $faturas */
    public function __invoke(Collection $faturas): string
    {
        return Planilha::xlsx([
            'Faturas' => [
                [
                    'Competência', 'Cliente', 'CNPJ', 'Situação', 'Vencimento',
                    'Mensalidade', 'Consumo faturado', 'Total',
                    'Custo do fornecedor', 'Imposto', 'Lucro',
                    'Vendedor', 'Comissão', 'Comissão liberada', 'Liquidada em',
                ],
                $faturas->map(fn (Fatura $fatura) => [
                    $fatura->competenciaRotulo(),
                    $fatura->cliente?->razao_social ?? '',
                    Documento::formatarCnpj($fatura->cliente?->cnpj),
                    Rotulos::fatura($fatura->situacao_pagamento),
                    $fatura->vencimento()->format('d/m/Y'),
                    self::reais($fatura->mensalidade_cents),
                    self::reais($fatura->consumo_faturado_cents),
                    self::reais($fatura->total_cents),
                    self::reais($fatura->custo_cents),
                    self::reais($fatura->imposto_cents),
                    self::reais($fatura->lucro_cents),
                    $fatura->vendedor?->nome ?? '',
                    self::reais($fatura->comissao_cents),
                    $fatura->comissao_liberada_em ? 'Sim' : 'Não',
                    $fatura->liquidada_em?->format('d/m/Y') ?? '',
                ])->values()->all(),
            ],
        ]);
    }

    /** Centavos viram numero com duas casas, para o Excel somar a coluna. */
    public static function reais(?int $centavos): ?float
    {
        return $centavos === null ? null : round($centavos / 100, 2);
    }
}
