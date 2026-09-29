<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O razao da empresa, em partidas dobradas.
 *
 * Partidas dobradas nao e preciosismo contabil aqui: e o que torna estruturais
 * as tres confusoes que custam dinheiro numa sociedade de dois.
 *
 *   aporte nao e receita        -> credita patrimonio, nao resultado
 *   reembolso nao e despesa     -> debita passivo, e a despesa ja foi lancada
 *   transferencia nao e nada    -> debita um ativo e credita outro
 *
 * Com valor unico numa linha so, essas tres dependem de quem digita lembrar da
 * regra. Com duas pernas que somam zero, a regra vira invariante: o lancamento
 * que nao fecha nao grava.
 *
 * O modelo segue o razao do 360, que ja existe nesta casa: linha nasce e nunca
 * muda, corrigir e lancar o contrario, e `ocorrido_em` guarda quando o dinheiro
 * se moveu, que nao e quando alguem digitou.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Quem divide a empresa. Tabela propria e nao uma marca em `staff`:
        // socio e relacao societaria, e staff e conta de acesso. Hoje as duas
        // coincidem em duas pessoas, e amarra-las faria remover o acesso de
        // alguem apagar a participacao dele.
        Schema::hasTable('socios') || Schema::create('socios', function (Blueprint $t) {
            $t->id();
            $t->string('nome', 120);

            // Liga a conta de acesso quando existe, e continua valendo quando
            // nao existe: socio que nao opera o sistema tambem tem quota.
            $t->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();

            // Em pontos-base, como o resto do dinheiro nesta casa. A soma dos
            // socios ativos deveria dar 10000, e quem confere isso e a tela:
            // trava no banco impediria cadastrar o primeiro socio, que sozinho
            // nunca fecha 100% enquanto o segundo nao entra.
            $t->unsignedInteger('participacao_bps')->default(0);

            $t->boolean('ativo')->default(true);
            $t->timestamps();
        });

        // O plano de contas. Pequeno de proposito: conta demais vira
        // classificacao que ninguem mantem, e o que se quer responder aqui cabe
        // em poucas linhas.
        Schema::hasTable('contas_financeiras') || Schema::create('contas_financeiras', function (Blueprint $t) {
            $t->id();

            // Codigo estavel, usado pelo codigo e pelos seeds. O nome muda; ele nao.
            $t->string('codigo', 30)->unique();
            $t->string('nome', 120);

            // ativo, passivo, patrimonio, receita, despesa
            $t->string('grupo', 20)->index();

            // Conta de socio aponta para quem. Nulo nas contas da empresa.
            $t->foreignId('socio_id')->nullable()->constrained('socios')->restrictOnDelete();

            $t->boolean('ativa')->default(true);
            $t->timestamps();
        });

        // O evento: uma compra, um aporte, um reembolso. Guarda o porque e o
        // comprovante; os valores ficam nas partidas.
        Schema::hasTable('lancamentos_financeiros') || Schema::create('lancamentos_financeiros', function (Blueprint $t) {
            $t->id();

            // A natureza do evento, em vocabulario de negocio e nao de contas:
            // e o que a tela pergunta, e e ela que decide quais pernas gerar.
            $t->string('natureza', 30)->index();

            $t->string('descricao', 200);

            // Competencia e quando o fato pertence; `ocorrido_em` e quando o
            // dinheiro se moveu. Conta paga em marco referente a fevereiro tem
            // os dois diferentes, e misturar os dois e o que faz o resultado do
            // mes nao bater com o extrato.
            $t->string('competencia', 7)->index();
            $t->date('ocorrido_em')->index();

            $t->string('contraparte', 150)->nullable();
            $t->string('documento', 100)->nullable();
            $t->string('comprovante', 255)->nullable();

            // De onde o lancamento veio, quando veio de algo que ja existe no
            // sistema. O par (tipo, id) e unico: e o que impede a mesma fatura
            // virar receita duas vezes.
            $t->string('origem_tipo', 40)->nullable();
            $t->unsignedBigInteger('origem_id')->nullable();

            $t->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();

            // Estorno aponta para o lancamento que ele desfaz. Corrigir e
            // lancar o contrario, e a ligacao e o que permite mostrar os dois
            // juntos em vez de deixar dois numeros soltos no extrato.
            $t->foreignId('estorna_id')->nullable()->constrained('lancamentos_financeiros')->nullOnDelete();

            $t->timestamp('created_at')->useCurrent();

            $t->unique(['origem_tipo', 'origem_id'], 'lancamento_origem_unica');
        });

        // As pernas. Somam ZERO dentro de cada lancamento, e e essa soma que
        // App\Actions\Socios\RegistrarLancamento confere antes de gravar.
        Schema::hasTable('partidas_financeiras') || Schema::create('partidas_financeiras', function (Blueprint $t) {
            $t->id();
            $t->foreignId('lancamento_id')->constrained('lancamentos_financeiros')->cascadeOnDelete();
            $t->foreignId('conta_id')->constrained('contas_financeiras')->restrictOnDelete();

            // Com sinal: positivo debita, negativo credita. Guardar os dois
            // numa coluna so mantem a conferencia como uma soma, que e a forma
            // mais dificil de errar.
            $t->bigInteger('valor_cents');

            $t->timestamp('created_at')->useCurrent();

            $t->index(['conta_id', 'lancamento_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partidas_financeiras');
        Schema::dropIfExists('lancamentos_financeiros');
        Schema::dropIfExists('contas_financeiras');
        Schema::dropIfExists('socios');
    }
};
