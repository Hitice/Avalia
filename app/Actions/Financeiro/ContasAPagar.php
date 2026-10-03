<?php

namespace App\Actions\Financeiro;

use App\Contabil\Lancar;
use App\Contabil\Partidas;
use App\Exceptions\Recusa;
use App\Models\ContaAPagar;
use App\Models\ContaFinanceira;
use App\Support\Auditar;
use Illuminate\Support\Facades\DB;

/**
 * Uma conta a pagar tem duas linhas no razao: a provisao, quando ela nasce
 * (despesa do mes e divida com o fornecedor), e o pagamento, quando o
 * dinheiro sai. Registrar so no pagamento faria o mes fechar sem a despesa
 * que ja estava contratada.
 */
class ContasAPagar
{
    public function __construct(private Lancar $lancar) {}

    public function provisionar(array $dados): ContaAPagar
    {
        $categoria = ContaFinanceira::find($dados['categoria_id']) ?? throw new Recusa('Categoria não encontrada.');

        if ($categoria->grupo !== 'despesa') {
            throw new Recusa('A categoria precisa ser de despesa.');
        }

        if ((int) $dados['valor_cents'] <= 0) {
            throw new Recusa('O valor precisa ser maior que zero.');
        }

        return DB::transaction(function () use ($dados, $categoria) {
            $conta = ContaAPagar::create($dados + ['staff_id' => auth('staff')->id()]);

            $lancamento = ($this->lancar)(Partidas::porCodigo([$categoria->codigo => $conta->valor_cents, 'fornecedores-a-pagar' => -$conta->valor_cents]), [
                'natureza' => 'provisao',
                'descricao' => $conta->descricao,
                'contraparte' => $conta->fornecedor,
                'competencia' => now()->format('Y-m'),
                'ocorrido_em' => now()->toDateString(),
                'origem_tipo' => 'conta-a-pagar',
                'origem_id' => $conta->id,
            ]);

            $conta->update(['lancamento_id' => $lancamento->id]);
            Auditar::registrar('conta-a-pagar.criada', $conta, ['valor_cents' => $conta->valor_cents, 'vence_em' => $conta->vence_em->toDateString()]);

            return $conta;
        });
    }

    public function pagar(ContaAPagar $conta): ContaAPagar
    {
        return DB::transaction(function () use ($conta) {
            $conta = ContaAPagar::lockForUpdate()->findOrFail($conta->id);

            if ($conta->pago_em !== null) {
                throw new Recusa('Esta conta já foi paga.');
            }

            $pagamento = ($this->lancar)(Partidas::porCodigo(['fornecedores-a-pagar' => $conta->valor_cents, 'caixa' => -$conta->valor_cents]), [
                'natureza' => 'pagamento',
                'descricao' => 'Pagamento: '.$conta->descricao,
                'contraparte' => $conta->fornecedor,
                'competencia' => now()->format('Y-m'),
                'ocorrido_em' => now()->toDateString(),
                'origem_tipo' => 'conta-a-pagar-paga',
                'origem_id' => $conta->id,
            ]);

            $conta->update(['pago_em' => now(), 'pagamento_id' => $pagamento->id]);
            Auditar::registrar('conta-a-pagar.paga', $conta, ['valor_cents' => $conta->valor_cents]);

            return $conta;
        });
    }
}
