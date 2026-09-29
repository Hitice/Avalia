<?php

namespace App\Actions\Socios;

use App\Exceptions\Recusa;
use App\Models\LancamentoFinanceiro;
use App\Support\Auditar;
use Illuminate\Support\Facades\DB;

/**
 * Apaga um lancamento digitado por engano.
 *
 * Razao contabil nao apaga lancamento, corrige com o contrario. A regra existe
 * para que saldo ja conferido nao mude sozinho, e ela continua valendo: o
 * estorno e o caminho normal.
 *
 * Mas ela protege lancamento que teve tempo de ser visto. Apagar o que se
 * acabou de digitar errado e outra coisa, e obrigar um estorno ali deixa duas
 * linhas no extrato para sempre por causa de um clique no seletor errado. O que
 * o mercado faz e a mesma distincao: apaga enquanto o lancamento e rascunho ou
 * esta no periodo aberto, e estorna depois que ele fecha.
 *
 * Aqui a linha e esta, e cada pedaco dela existe por um motivo:
 *
 *   competencia CORRENTE    mes anterior pode ja ter sido conferido
 *   sem ORIGEM              receita vinda de fatura nao se apaga pela tela; a
 *                           origem e unica, e apagar o lancamento devolveria a
 *                           fatura ao estado de nao reconhecida em silencio
 *   sem ESTORNO envolvido   o par estorno mais original conta uma historia, e
 *                           apagar metade dela deixa a outra sem sentido
 *
 * A trilha guarda o que o razao perde: valor, natureza e descricao entram na
 * auditoria, entao "sumiu um lancamento" continua tendo resposta.
 */
class ExcluirLancamento
{
    public function __invoke(LancamentoFinanceiro $lancamento): void
    {
        $this->conferir($lancamento);

        DB::transaction(function () use ($lancamento) {
            Auditar::registrar('socios.lancamento.excluido', null, [
                'natureza' => $lancamento->natureza->value,
                'descricao' => $lancamento->descricao,
                'competencia' => $lancamento->competencia,
                'valor_cents' => $lancamento->valorCents(),
            ]);

            // As partidas caem por cascade da chave estrangeira.
            $lancamento->delete();
        });
    }

    private function conferir(LancamentoFinanceiro $lancamento): void
    {
        if ($lancamento->origem_tipo !== null) {
            throw new Recusa(
                'Este lançamento veio de uma fatura e não se apaga por aqui. '
                .'Para tirá-lo da conta, estorne.'
            );
        }

        if ($lancamento->estorna_id !== null) {
            throw new Recusa('Estorno não se apaga: ele e o original contam a mesma história.');
        }

        if ($lancamento->estornado()) {
            throw new Recusa('Este lançamento já foi estornado. O par fica no extrato.');
        }

        if ($lancamento->competencia !== now()->format('Y-m')) {
            throw new Recusa(
                'Só dá para apagar lançamento da competência atual. '
                .'Mês anterior pode já ter sido conferido: use o estorno.'
            );
        }
    }
}
