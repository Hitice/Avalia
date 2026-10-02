<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * As categorias do livro-caixa manual: para onde vai cada saida e de onde vem
 * cada entrada que nao nasce de um documento do sistema. Sem isto toda despesa
 * digitada caia em "despesa" e o mes fechava sem dizer com o que se gastou.
 */
return new class extends Migration
{
    private const CONTAS = [
        ['codigo' => 'despesa:prolabore', 'nome' => 'Pro-labore dos socios', 'grupo' => 'despesa'],
        ['codigo' => 'despesa:fornecedor', 'nome' => 'Fornecedores e materiais', 'grupo' => 'despesa'],
        ['codigo' => 'despesa:infra', 'nome' => 'Hospedagem, dominio e ferramentas', 'grupo' => 'despesa'],
        ['codigo' => 'despesa:marketing', 'nome' => 'Marketing e anuncios', 'grupo' => 'despesa'],
        ['codigo' => 'despesa:pessoal', 'nome' => 'Equipe e encargos', 'grupo' => 'despesa'],
        ['codigo' => 'receita:servicos', 'nome' => 'Receita Servicos de software', 'grupo' => 'receita'],
    ];

    public function up(): void
    {
        $agora = now();

        foreach (self::CONTAS as $conta) {
            if (DB::table('contas_financeiras')->where('codigo', $conta['codigo'])->exists()) {
                continue;
            }

            DB::table('contas_financeiras')->insert($conta + ['ativa' => true, 'created_at' => $agora, 'updated_at' => $agora]);
        }
    }

    public function down(): void
    {
        DB::table('contas_financeiras')->whereIn('codigo', array_column(self::CONTAS, 'codigo'))->delete();
    }
};
