<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A venda da plaquinha passa a saber quem vendeu e quanto custou.
 *
 * Faltavam as duas pontas para fechar o repasse. `staff_id` nao servia: ele
 * responde "quem gerou a tiragem", que e a administracao imprimindo mil placas
 * de uma vez, e nao "quem vendeu esta aqui". Usar um pelo outro creditaria
 * todas as vendas a quem operou a impressora.
 *
 * Sao tres conceitos diferentes na mesma linha, e agora cada um tem coluna:
 *   staff_id    -> quem gerou      (trilha de producao)
 *   dono_tipo   -> de quem e       (permissao, ver App\Support\Dono)
 *   vendedor_id -> quem vendeu     (repasse)
 *
 * `custo_cents` e copia do config no momento da venda, pela mesma regra que ja
 * vale para `valor_cents`: o fornecedor reajusta, e sem a copia o lucro de um
 * mes ja fechado mudaria de numero depois do repasse pago.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('etiquetas', 'vendedor_id')) {
            Schema::table('etiquetas', function (Blueprint $t) {
                $t->foreignId('vendedor_id')->nullable()->after('staff_id')
                    ->constrained('staff')->nullOnDelete();

                // O painel de vendas agrupa por esta coluna dentro de um
                // periodo; sem indice ele varre a tabela inteira a cada F5.
                $t->index(['vendedor_id', 'vendida_em']);
            });
        }

        if (! Schema::hasColumn('etiquetas', 'custo_cents')) {
            Schema::table('etiquetas', function (Blueprint $t) {
                $t->unsignedBigInteger('custo_cents')->nullable()->after('valor_cents');
            });
        }

        $this->recuperarVendedores();
        $this->recuperarCustos();
    }

    /**
     * Quem vendeu, recuperado do historico de destinos.
     *
     * A primeira vez que alguem apontou a plaquinha para algum lugar e a venda:
     * e o passo que tira a placa do estoque. `destinos_etiqueta` guarda o staff
     * de cada apontamento desde sempre, entao a informacao existia, so nao
     * estava onde da para somar.
     *
     * Fica nulo quando quem apontou nao era da casa (cliente e produtor mexem
     * no proprio codigo) ou quando a conta foi removida. Nulo aparece no painel
     * como "sem vendedor", e nao dividido entre os outros: inventar dono para
     * uma venda orfa e pior que mostrar que ela existe.
     */
    private function recuperarVendedores(): void
    {
        DB::table('etiquetas')
            ->whereNotNull('vendida_em')
            ->whereNull('vendedor_id')
            ->orderBy('id')
            ->chunkById(200, function ($etiquetas) {
                foreach ($etiquetas as $etiqueta) {
                    $vendedor = DB::table('destinos_etiqueta')
                        ->where('etiqueta_id', $etiqueta->id)
                        ->whereNotNull('staff_id')
                        ->orderBy('vigorou_de')
                        ->orderBy('id')
                        ->value('staff_id');

                    if ($vendedor !== null) {
                        DB::table('etiquetas')->where('id', $etiqueta->id)
                            ->update(['vendedor_id' => $vendedor]);
                    }
                }
            });
    }

    /**
     * O custo das vendas antigas, pelo unico numero que a casa conhece.
     *
     * Nao ha registro de quanto cada placa antiga custou, e deixar nulo faria o
     * lucro dos meses passados depender do config de hoje: bastaria o
     * fornecedor reajustar para um mes ja fechado mudar de numero. Gravar o
     * custo atual congela o passado num valor conferivel, que e o pior dos dois
     * males e o unico que para de se mexer sozinho.
     */
    private function recuperarCustos(): void
    {
        DB::table('etiquetas')
            ->whereNotNull('vendida_em')
            ->whereNull('custo_cents')
            ->update(['custo_cents' => (int) config('etiquetas.custo_cents')]);
    }

    public function down(): void
    {
        Schema::table('etiquetas', function (Blueprint $t) {
            $t->dropForeign(['vendedor_id']);
            $t->dropIndex(['vendedor_id', 'vendida_em']);
            $t->dropColumn(['vendedor_id', 'custo_cents']);
        });
    }
};
