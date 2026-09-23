<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O formulario de contato do site institucional cai na mesma fila.
 *
 * Tabela nova nao se justificava: quem escreve pelo site e a mesma figura dos
 * outros formularios, alguem que ainda nao e cliente pedindo contato, e a
 * administracao ja tem uma fila para isso. O que muda sao duas colunas, e nao
 * a natureza do registro.
 *
 * `funcionarios` passa a aceitar nulo porque o site nao pergunta o tamanho da
 * equipe: quem chega pela pagina de contato quer descrever um problema, e
 * pedir a faixa de funcionarios antes disso e cobrar um formulario de
 * qualificacao de quem ainda esta decidindo se fala com a gente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('interessados', function (Blueprint $t) {
            // Assunto vem de lista fechada na tela, nao de campo livre: e
            // rotulo nosso, usado para encaminhar o pedido.
            $t->string('assunto', 60)->nullable()->after('email');
            $t->text('mensagem')->nullable()->after('assunto');
            $t->string('funcionarios', 20)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('interessados', function (Blueprint $t) {
            $t->dropColumn(['assunto', 'mensagem']);
        });
    }
};
