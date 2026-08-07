<?php

namespace App\Actions\Consumo;

use App\Models\Cliente;
use App\Models\Consulta;
use App\Models\Fatura;
use App\Models\Servico;
use Illuminate\Support\Facades\DB;

/**
 * Registra consultas de uma empresa, congelando preco e custo.
 *
 * O preco sai do catalogo do plano contratado, na faixa contratada, e e COPIADO
 * para a consulta. Depois disso a linha nao depende mais do catalogo: reajuste
 * de amanha nao mexe no que foi consultado hoje.
 *
 * A porta de entrada da regra de acesso tambem e aqui, e nao na tela: empresa
 * inadimplente ou bloqueada nao consulta, e servico que aguarda liberacao nao
 * sai para o fornecedor.
 */
class RegistrarConsulta
{
    /** @return array{erro: string|null, consultas: int, total_cents: int} */
    public function __invoke(Cliente $cliente, Servico $servico, int $quantidade = 1): array
    {
        $quantidade = max(1, $quantidade);

        if (! $cliente->podeConsultar()) {
            return $this->falha($cliente->motivoSuspensao() ?? 'Empresa sem acesso a consultas.');
        }

        if (! $cliente->plano) {
            return $this->falha('Empresa sem plano contratado.');
        }

        if (! $cliente->documentosObrigatoriosAceitos()) {
            return $this->falha('Existem documentos obrigatórios pendentes de aceite.');
        }

        if (! $servico->disponivel()) {
            return $this->falha("Servico '{$servico->nome}' nao esta liberado para consulta.");
        }

        $preco = $cliente->plano->catalogo
            ?->precos()
            ->where('servico_id', $servico->id)
            ->where('consumo_minimo_cents', $cliente->plano->faixaDePrecoCents())
            ->first();

        if (! $preco) {
            return $this->falha("Servico '{$servico->nome}' nao tem preco na faixa do plano.");
        }

        $competencia = Consulta::competenciaDe();

        // Fatura fechada nao recebe consulta nova: ela ja virou cobranca, e
        // aceitar mais uma linha mudaria um numero que o cliente ja recebeu.
        $fechada = Fatura::where('cliente_id', $cliente->id)
            ->where('competencia', $competencia)
            ->exists();

        if ($fechada) {
            return $this->falha("A competencia {$competencia} ja foi fechada.");
        }

        DB::transaction(function () use ($cliente, $servico, $preco, $competencia, $quantidade) {
            Consulta::insert(array_fill(0, $quantidade, [
                'cliente_id' => $cliente->id,
                'servico_id' => $servico->id,
                'competencia' => $competencia,
                'preco_cents' => $preco->preco_cents,
                'custo_cents' => $preco->custo_cents,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        });

        return [
            'erro' => null,
            'consultas' => $quantidade,
            'total_cents' => $preco->preco_cents * $quantidade,
        ];
    }

    /** @return array{erro: string, consultas: int, total_cents: int} */
    private function falha(string $mensagem): array
    {
        return ['erro' => $mensagem, 'consultas' => 0, 'total_cents' => 0];
    }
}
