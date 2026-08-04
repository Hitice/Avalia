<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modulo Catalogo: o que a Avalia vende e por quanto.
 *
 * Raiz de dependencia de todo o resto: plano aponta para versao, consulta
 * apontara para preco, fatura apontara para os dois.
 *
 * A ideia central e a VERSAO. Preco nao e um numero solto no servico: e um
 * numero dentro de uma versao datada do catalogo. Quando o comercial muda a
 * tabela, nasce uma versao nova; a antiga fica intacta porque contratos,
 * consultas e faturas ja emitidos apontam para ela. Sem isso, reajustar preco
 * em marco reescreveria a fatura de janeiro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('versoes_catalogo', function (Blueprint $t) {
            $t->id();
            $t->string('rotulo')->unique();
            $t->text('observacao')->nullable();

            // rascunho  -> em edicao, nao vende
            // agendada  -> aprovada, entra em vigor na data marcada
            // ativa     -> e a tabela vigente (so uma por vez)
            // encerrada -> saiu de vigencia, mas continua legivel para historico
            $t->enum('situacao', ['rascunho', 'agendada', 'ativa', 'encerrada'])
                ->default('rascunho')
                ->index();

            $t->date('vigencia_inicio')->nullable();
            $t->date('vigencia_fim')->nullable();

            // Carimbo do momento em que a versao deixou de ser editavel.
            $t->timestamp('congelada_em')->nullable();

            $t->timestamps();
        });

        Schema::create('servicos', function (Blueprint $t) {
            $t->id();

            // Codigo estavel, usado por integracao e relatorio. O nome comercial
            // muda com marketing; o codigo nao.
            $t->string('codigo')->unique();
            $t->string('nome');
            $t->enum('categoria', ['credito', 'veicular'])->index();

            // O custo do fornecedor e interno e fica no preco, por faixa.
            // aqui so mora a identidade do servico.
            $t->boolean('ativo')->default(true)->index();

            // SCR e afins: catalogo e permissao existem desde ja, mas a chamada
            // externa fica travada ate a homologacao juridica e contratual.
            $t->boolean('exige_liberacao')->default(false);

            $t->timestamps();
        });

        Schema::create('precos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('versao_id')->constrained('versoes_catalogo')->cascadeOnDelete();
            $t->foreignId('servico_id')->constrained('servicos')->cascadeOnDelete();

            // A faixa e o proprio valor do consumo minimo, em centavos: 0 (sem
            // minimo), 7500, 20000, 50000, 90000, 150000, 500000. Guardar o
            // valor em vez de um id de faixa deixa uma versao futura acrescentar
            // ou remover faixa sem migration.
            $t->bigInteger('consumo_minimo_cents');

            // Preco de venda ao cliente da Avalia.
            $t->bigInteger('preco_cents');

            // Custo do fornecedor: interno, nunca exibido a cliente ou vendedor.
            // Nulo enquanto o contrato de custo nao for cadastrado.
            $t->bigInteger('custo_cents')->nullable();

            $t->timestamps();

            $t->unique(['versao_id', 'servico_id', 'consumo_minimo_cents']);
        });

        Schema::create('planos', function (Blueprint $t) {
            $t->id();

            // restrictOnDelete: versao com plano vinculado nao se apaga. E a
            // trava de ultima instancia do congelamento comercial.
            $t->foreignId('versao_id')->constrained('versoes_catalogo')->restrictOnDelete();

            $t->string('nome')->unique();
            $t->text('descricao')->nullable();

            // Mensalidade e consumo minimo sao colunas separadas porque a
            // fatura e mensalidade + max(consumo_minimo, consumo_realizado).
            // Num campo unico nao da para saber quanto do valor e piso de
            // consumo e quanto e assinatura, e o excedente deixa de ser
            // calculavel.
            $t->bigInteger('mensalidade_cents')->default(0);

            // Tem que casar com uma das faixas da versao: e ela que escolhe a
            // coluna de precos que o cliente paga.
            $t->bigInteger('consumo_minimo_cents')->default(0);

            $t->boolean('ativo')->default(true)->index();
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('franquias_plano', function (Blueprint $t) {
            $t->id();
            $t->foreignId('plano_id')->constrained('planos')->cascadeOnDelete();
            $t->foreignId('servico_id')->constrained('servicos')->cascadeOnDelete();

            // Quantidade de consultas incluidas na mensalidade, por competencia.
            // Zero = servico liberado, mas toda consulta e excedente.
            $t->unsignedInteger('quantidade')->default(0);

            $t->timestamps();

            $t->unique(['plano_id', 'servico_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('franquias_plano');
        Schema::dropIfExists('planos');
        Schema::dropIfExists('precos');
        Schema::dropIfExists('servicos');
        Schema::dropIfExists('versoes_catalogo');
    }
};
