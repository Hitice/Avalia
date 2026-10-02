<?php

namespace App\Actions\Etiquetas;

use App\Actions\Socios\EstornarLancamento;
use App\Contabil\Lancar;
use App\Exceptions\Recusa;
use App\Models\Etiqueta;
use App\Models\LancamentoFinanceiro;
use App\Support\Auditar;
use App\Support\Dinheiro;
use App\Support\SociosDaPlaquinha;
use Illuminate\Support\Facades\DB;

/**
 * Corrige o valor de uma venda de plaquinha ja registrada.
 *
 * `VenderEtiqueta` congela preco e custo na PRIMEIRA venda, e continua assim: e a
 * regra que impede reajuste de hoje mudar cobranca de ontem. O que faltava era a
 * porta explicita para o caso legitimo, o preco negociado diferente ou digitado
 * errado. Antes o campo aceitava o novo valor, a tela dizia "apontada com
 * sucesso" e nada era gravado.
 *
 * Mexer no valor mexe na COMISSAO, entao isto e acao de administracao, pela mesma
 * razao de cancelar venda.
 *
 * E mexe no razao: estorna o lancamento da venda e lanca o valor novo. Trocar a
 * coluna sem tocar no razao faria o painel e o extrato discordarem, que e a
 * divergencia que este sistema existe para nao ter.
 */
class CorrigirValorDaVenda
{
    public function __construct(
        private readonly EstornarLancamento $estornar,
        private readonly Lancar $lancar,
        private readonly VendaNoRazao $contabil,
    ) {}

    public function __invoke(Etiqueta $etiqueta, ?int $novoValorCents): Etiqueta
    {
        if ($etiqueta->vendida_em === null) {
            throw new Recusa('Esta plaquinha não tem venda registrada. Aponte o destino para registrá-la.');
        }

        if ($novoValorCents === null || $novoValorCents < 1) {
            throw new Recusa('Informe o valor da venda, maior que zero.');
        }

        $anterior = (int) $etiqueta->valor_cents;

        if ($anterior === $novoValorCents) {
            throw new Recusa('O valor já é '.Dinheiro::brl($novoValorCents).'.');
        }

        return DB::transaction(function () use ($etiqueta, $anterior, $novoValorCents) {
            $lancamento = LancamentoFinanceiro::where('origem_tipo', 'etiqueta')
                ->where('origem_id', $etiqueta->id)
                ->first();

            // Estorna ANTES de trocar a coluna: as pernas do estorno saem do
            // lancamento guardado, e nao do valor novo.
            if ($lancamento && ! $lancamento->estornado()) {
                ($this->estornar)($lancamento, 'Valor da venda da plaquinha '.$etiqueta->codigo.' corrigido');
            }

            $etiqueta->update(['valor_cents' => $novoValorCents]);

            Auditar::registrar('etiquetas.valor.corrigido', $etiqueta, [
                'de' => $anterior,
                'para' => $novoValorCents,
            ]);

            // O valor novo entra sem origem: a origem `etiqueta` continua com o
            // lancamento original, que fica no extrato ao lado do estorno dele.
            if ($lancamento) {
                $socios = SociosDaPlaquinha::resolver()['ids'];

                ($this->lancar)(
                    $this->contabil->partidas($etiqueta->fresh(), $socios),
                    [
                        'natureza' => 'receita',
                        'descricao' => 'Venda corrigida da plaquinha '.$etiqueta->codigo,
                        'competencia' => now()->format('Y-m'),
                        'ocorrido_em' => now()->toDateString(),
                    ],
                );
            }

            return $etiqueta->fresh();
        });
    }
}
