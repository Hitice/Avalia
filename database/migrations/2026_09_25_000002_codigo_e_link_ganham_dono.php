<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cada codigo e cada link passam a ter dono.
 *
 * A ferramenta deixa de ser so da administracao e abre para vendedor, cliente
 * e produtor. Sem dono, abrir a porta significaria que qualquer conta ve os
 * codigos de todas as outras e troca o destino deles: um cliente mandaria a
 * freguesia de outro para onde quisesse, e a gente so descobriria pelo
 * telefone.
 *
 * Guarda o GUARD e o id, e nao uma chave estrangeira: sao tres tabelas de
 * conta diferentes (staff, clientes, produtores), e uma coluna por tabela
 * deixaria duas sempre nulas e a conferencia com tres caminhos.
 *
 * `staff_id` continua onde esta: ele responde "quem gerou", que e trilha de
 * producao, e nao "de quem e", que e permissao.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['etiquetas', 'links'] as $tabela) {
            if (Schema::hasColumn($tabela, 'dono_tipo')) {
                continue;
            }

            Schema::table($tabela, function (Blueprint $t) {
                $t->string('dono_tipo', 20)->nullable()->after('staff_id');
                $t->unsignedBigInteger('dono_id')->nullable()->after('dono_tipo');

                $t->index(['dono_tipo', 'dono_id']);
            });

            // O que ja existe era tudo da administracao, que gerou tudo.
            DB::table($tabela)->whereNotNull('staff_id')->update([
                'dono_tipo' => 'staff',
                'dono_id' => DB::raw('staff_id'),
            ]);
        }
    }

    public function down(): void
    {
        foreach (['etiquetas', 'links'] as $tabela) {
            Schema::table($tabela, function (Blueprint $t) {
                $t->dropIndex(['dono_tipo', 'dono_id']);
                $t->dropColumn(['dono_tipo', 'dono_id']);
            });
        }
    }
};
