<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * O lead ganha a ficha do cliente e o estagio do funil.
 *
 * Duas mudancas, e as duas existem pela mesma razao: o lead precisa virar
 * cliente sem ninguem redigitar nada.
 *
 * 1. Os campos que faltavam para abrir o cadastro. `clientes` pede responsavel,
 *    CPF e endereco completo; o lead vinha dos PDFs so com cidade e UF. Quem
 *    fechava a venda descobria na hora de cadastrar que precisava perguntar tudo
 *    de novo, com o cliente do outro lado da linha.
 *
 * 2. `situacao` no lugar de `ativo`. `ativo` era o marcador "(INATIVO)" colado
 *    no nome pela base de origem: dizia algo sobre a Receita e nada sobre o
 *    trabalho. Ninguem consegue responder "com quem eu falei e como foi" olhando
 *    um booleano.
 *
 * `ativo` NAO e removida aqui. A regra do projeto e que coluna sai numa versao
 * posterior a que para de usa-la, porque o retorno do deploy volta o codigo e
 * nao o banco: publicada junto, um retorno deixaria o codigo antigo lendo uma
 * coluna que nao existe mais.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $t) {
            // A ficha que o cadastro de cliente pede, na mesma ordem e com os
            // mesmos tamanhos de `clientes`: e o que permite copiar campo a
            // campo na conversao sem truncar nada.
            $t->string('responsavel_nome', 150)->nullable()->after('email');
            $t->string('responsavel_cpf', 14)->nullable()->after('responsavel_nome');
            $t->string('cep', 8)->nullable()->after('responsavel_cpf');
            $t->string('logradouro', 150)->nullable()->after('cep');
            $t->string('numero', 20)->nullable()->after('logradouro');
            $t->string('complemento', 100)->nullable()->after('numero');
            $t->string('bairro', 100)->nullable()->after('complemento');

            // Texto e nao enum: o funil ganha estagio conforme a operacao
            // aprende a vender, e cada estagio novo num enum do MySQL e um
            // ALTER na tabela inteira. O conjunto de valores vive em
            // App\Enums\SituacaoLead, que e quem valida.
            $t->string('situacao', 20)->default('novo')->after('origem');

            // Agendamento e o unico estagio com data, e a data e o motivo de o
            // estagio existir: "agendado" sem quando nao serve para ninguem.
            $t->timestamp('agendado_para')->nullable()->after('situacao');

            // Para onde o lead foi. E a resposta a "de onde veio este cliente",
            // que e a pergunta que a prospecao existe para responder.
            $t->foreignId('cliente_id')->nullable()->after('agendado_para')
                ->constrained('clientes')->nullOnDelete();
            $t->timestamp('convertido_em')->nullable()->after('cliente_id');

            // A fila de quem prospecta: situacao mais agendamento, que e como a
            // tela do vendedor ordena e filtra.
            $t->index(['situacao', 'agendado_para']);
        });

        // O unico lead que vinha marcado inativo na origem passa a "nao
        // atender", com o porque no proprio cadastro: o marcador some, mas a
        // informacao que ele carregava, nao.
        //
        // Em PHP e nao em SQL: `CONCAT` nao existe em todo SQLite, e a suite
        // roda em SQLite. O valor vai literal, e nao pelo enum, porque migration
        // aplicada nao pode mudar de sentido quando o enum for renomeado.
        foreach (DB::table('leads')->where('ativo', false)->get(['id', 'observacao']) as $linha) {
            DB::table('leads')->where('id', $linha->id)->update([
                'situacao' => 'bloqueado',
                'observacao' => trim(($linha->observacao ? $linha->observacao."\n" : '')
                    .'Veio marcado como INATIVO na base de origem.'),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $t) {
            $t->dropIndex(['situacao', 'agendado_para']);
            $t->dropConstrainedForeignId('cliente_id');
            $t->dropColumn([
                'responsavel_nome', 'responsavel_cpf', 'cep', 'logradouro',
                'numero', 'complemento', 'bairro', 'situacao', 'agendado_para', 'convertido_em',
            ]);
        });
    }
};
