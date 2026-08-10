<?php

namespace App\Actions\Catalogo;

use App\Models\Catalogo;
use App\Models\Preco;
use App\Support\Auditar;
use App\Support\Margem;
use Illuminate\Support\Facades\DB;

/**
 * Leva ao preco alvo os precos que rendem menos que a margem da faixa.
 *
 * A margem alvo e uma escada: a faixa sem minimo entrega a margem cheia, e
 * cada faixa seguinte cede um degrau. E o desconto por consumo dito pelo lado
 * da margem, que e o lado que a operacao precisa defender. O alvo de cada
 * faixa sai do catalogo, entao mudar a politica e mexer em dois campos, e nao
 * reprecificar servico por servico.
 *
 * SO SOBE. Preco acima do alvo fica onde esta, e a razao e comercial: o alvo e
 * o minimo que a casa aceita, nao a media que ela persegue. Servico que rende
 * 59% esta pagando pelos que rendem 20%, e derrubar todo mundo para o alvo
 * seria abrir mao de dinheiro que ja esta na mesa sem ninguem ter pedido.
 *
 * Nunca roda sozinha. Reajuste e decisao comercial, e ninguem pode descobrir
 * um depois que o cliente reclamou.
 *
 * So o preco de venda muda. Custo do fornecedor e contrato dele, e nao se
 * conserta mexendo no nosso lado.
 */
class AjustarPrecosAoAlvo
{
    /**
     * Os precos que rendem menos que a margem alvo da propria faixa.
     *
     * @return \Illuminate\Support\Collection<int, array{preco: Preco, alvo: int, alvoBps: int}>
     */
    public static function abaixoDoAlvo(Catalogo $catalogo): \Illuminate\Support\Collection
    {
        $precos = $catalogo->precos()->with('servico')->get();
        $alvosBps = $catalogo->margemAlvoPorFaixa(Catalogo::faixasDe($precos));

        return $precos
            ->map(function (Preco $preco) use ($catalogo, $alvosBps) {
                $alvoBps = $alvosBps[$preco->consumo_minimo_cents] ?? 0;

                return [
                    'preco' => $preco,
                    'alvoBps' => $alvoBps,
                    'alvo' => Margem::precoAlvoCents(
                        $preco->custo_cents, $catalogo->imposto_bps, $catalogo->comissaoBps(), $alvoBps,
                    ),
                ];
            })
            // Sem custo cadastrado nao ha alvo conhecido, e chutar um seria
            // inventar reajuste onde talvez nao haja problema nenhum.
            ->filter(fn (array $linha) => $linha['alvo'] !== null && $linha['preco']->preco_cents < $linha['alvo'])
            ->values();
    }

    /** @return int quantos precos foram ajustados */
    public function __invoke(Catalogo $catalogo): int
    {
        $abaixo = self::abaixoDoAlvo($catalogo);

        if ($abaixo->isEmpty()) {
            return 0;
        }

        DB::transaction(function () use ($abaixo, $catalogo) {
            foreach ($abaixo as $linha) {
                $preco = $linha['preco'];
                $antes = $preco->preco_cents;

                $preco->update(['preco_cents' => $linha['alvo']]);

                // Uma entrada por preco, e nao uma por lote: quem for conferir
                // um servico depois procura por ele, nao pela operacao.
                Auditar::registrar('preco.alvo', $preco, [
                    'servico' => $preco->servico?->codigo,
                    'faixa_cents' => $preco->consumo_minimo_cents,
                    'de_cents' => $antes,
                    'para_cents' => $linha['alvo'],
                    'margem_alvo_bps' => $linha['alvoBps'],
                ]);
            }

            $catalogo->touch();
        });

        return $abaixo->count();
    }
}
