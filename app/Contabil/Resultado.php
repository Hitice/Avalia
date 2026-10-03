<?php

namespace App\Contabil;

use App\Support\Empresa;
use Illuminate\Support\Facades\DB;

/**
 * O resultado da casa, lido do razao e nao recalculado dos documentos.
 *
 * Responde o que nenhuma tela respondia: quanto cada produto deu no mes, lado a
 * lado.
 */
final class Resultado
{
    /**
     * Os produtos, na ordem em que aparecem, e o sufixo dos codigos de conta.
     *
     * @return array<string, string>
     */
    public static function produtos(): array
    {
        return [
            'one' => Empresa::marcaCredito(),
            'gestor' => Empresa::marcaCobranca(),
            'plaquinha' => 'QR dinâmico',
        ];
    }

    /**
     * Saldo de cada conta, com o sinal que a pessoa espera ler.
     *
     * @return array<string, int> codigo => centavos
     */
    public static function saldos(?string $competencia = null): array
    {
        $consulta = DB::table('partidas_financeiras as p')
            ->join('lancamentos_financeiros as l', 'l.id', '=', 'p.lancamento_id')
            ->join('contas_financeiras as c', 'c.id', '=', 'p.conta_id')
            ->groupBy('c.codigo', 'c.grupo')
            ->select('c.codigo', 'c.grupo', DB::raw('SUM(p.valor_cents) as soma'));

        // Sem competencia, o saldo de sempre: caixa e o que ha hoje.
        if ($competencia !== null) {
            $consulta->where('l.competencia', $competencia);
        }

        $saldos = [];

        foreach ($consulta->get() as $linha) {
            $soma = (int) $linha->soma;

            // A mesma regra de sinal de `ContaFinanceira::saldoCents()`.
            $saldos[$linha->codigo] = in_array($linha->grupo, ['ativo', 'despesa'], true) ? $soma : -$soma;
        }

        return $saldos;
    }

    /**
     * Receita, custo, comissao e lucro de cada produto na competencia.
     *
     * @return list<array{chave: string, nome: string, receita: int, custo: int, comissao: int, lucro: int, lancado: bool}>
     */
    public static function porProduto(string $competencia): array
    {
        $saldos = self::saldos($competencia);
        $comissao = $saldos['comissao'] ?? 0;

        // Conta unica porque hoje so a plaquinha lanca. Quando One e Gestor
        // lancarem, ela se divide em `comissao:<produto>` e esta linha sai.
        $linhas = [];

        foreach (self::produtos() as $chave => $nome) {
            $receita = $saldos["receita:{$chave}"] ?? 0;
            $custo = $saldos["custo:{$chave}"] ?? 0;
            $daComissao = $chave === 'plaquinha' ? $comissao : 0;

            $linhas[] = [
                'chave' => $chave,
                'nome' => $nome,
                'receita' => $receita,
                'custo' => $custo,
                'comissao' => $daComissao,
                'lucro' => $receita - $custo - $daComissao,

                // Vazio e marcado, nao escondido: zero sem aviso e lido como
                // "nao vendeu", que e outra coisa.
                'lancado' => $receita !== 0 || $custo !== 0,
            ];
        }

        return $linhas;
    }
}
