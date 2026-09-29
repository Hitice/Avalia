<?php

namespace Database\Seeders;

use App\Models\ContaFinanceira;
use Illuminate\Database\Seeder;

/**
 * As contas da empresa. As dos socios nascem sob demanda, no primeiro
 * lancamento de cada um.
 *
 * `firstOrCreate` pelo codigo: republicar nao duplica conta nem desfaz um nome
 * corrigido na tela.
 */
class ContasFinanceirasSeeder extends Seeder
{
    public function run(): void
    {
        $contas = [
            [ContaFinanceira::CAIXA, 'Caixa', 'ativo'],
            [ContaFinanceira::RECEITA, 'Receita', 'receita'],
            [ContaFinanceira::DESPESA, 'Despesa', 'despesa'],
        ];

        foreach ($contas as [$codigo, $nome, $grupo]) {
            ContaFinanceira::firstOrCreate(['codigo' => $codigo], ['nome' => $nome, 'grupo' => $grupo]);
        }
    }
}
