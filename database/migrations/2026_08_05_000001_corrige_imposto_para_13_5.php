<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Aliquota de imposto passa de 8,60% para 13,50%.
 *
 * Os 8,60% eram a faixa inicial do Simples, Anexo III, e a apuracao real da
 * Avalia deu 13,50%. Nao e ajuste de tela: o imposto sai antes do lucro, entao
 * ele muda o piso de cada preco e a comissao de cada mes.
 *
 * Atualiza tambem quem ja esta gravado com o valor antigo. Catalogo que alguem
 * tenha ajustado a mao para outro numero fica como esta: correcao de padrao nao
 * pode passar por cima de decisao consciente.
 */
return new class extends Migration
{
    private const ANTIGO = 860;

    private const NOVO = 1_350;

    public function up(): void
    {
        Schema::table('catalogos', function (Blueprint $t) {
            $t->unsignedInteger('imposto_bps')->default(self::NOVO)->change();
        });

        DB::table('catalogos')->where('imposto_bps', self::ANTIGO)->update(['imposto_bps' => self::NOVO]);
    }

    public function down(): void
    {
        Schema::table('catalogos', function (Blueprint $t) {
            $t->unsignedInteger('imposto_bps')->default(self::ANTIGO)->change();
        });

        DB::table('catalogos')->where('imposto_bps', self::NOVO)->update(['imposto_bps' => self::ANTIGO]);
    }
};
