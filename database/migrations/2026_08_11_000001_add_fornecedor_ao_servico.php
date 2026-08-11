<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * De qual bureau vem cada servico.
 *
 * Ate aqui o sistema escolhia um conector para o catalogo inteiro, e isso so
 * funcionava enquanto havia um fornecedor. O catalogo mistura bases, e ligar a
 * Boa Vista mandava para ela ate o que ela nao vende.
 *
 * A carga inicial aplica a regra do negocio: serviço de "Base III" e Boa Vista.
 * Fica gravado, e nao calculado, porque nome de servico e texto comercial e
 * muda: regra que le nome quebra no dia em que alguem renomeia uma linha da
 * tabela. O que o nome faz aqui e sugerir o valor uma vez; dali em diante quem
 * manda e o campo, editavel na tela e na planilha.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servicos', function (Blueprint $t) {
            $t->string('fornecedor', 40)->nullable()->after('codigo_fornecedor');
        });

        // Base III e a base da Boa Vista. Cobre "Base III", "Base I e III" e
        // "Base I, II e III", sem pegar "Base I e II".
        DB::table('servicos')
            ->where(function ($q) {
                $q->where('nome', 'like', '%Base III%')
                    ->orWhere('nome', 'like', '%e III%')
                    ->orWhere('nome', 'like', '%, III%');
            })
            ->update(['fornecedor' => 'boa-vista']);
    }

    public function down(): void
    {
        Schema::table('servicos', fn (Blueprint $t) => $t->dropColumn('fornecedor'));
    }
};
