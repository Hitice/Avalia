<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O negocio local que compra marketing da casa.
 *
 * Hoje ele existe solto: a plaquinha guarda `cliente_nome` e `cliente_contato`
 * como texto livre, e nao da para saber que duas placas sao da mesma loja, nem
 * atender essa loja com outro produto. Aqui ele passa a ser cadastro.
 *
 * Nao e `clientes`, que e do Avalia One: aquela tabela carrega plano, franquia e
 * fatura, e um negocio de marketing nao tem nenhuma das tres. Juntar as duas
 * deixaria metade das colunas vazia e um filtro esquecido bastaria para a loja
 * aparecer num fechamento de consultas.
 *
 * Os campos sao os que o Google Meu Negocio pede, porque e o primeiro servico
 * que a casa presta com eles. Nada aqui e cifrado, ao contrario do pre-cadastro
 * do Gestor: nome, endereco e telefone de uma loja existem para ser publicados,
 * e cifrar quebraria busca e ordenacao sem proteger nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('negocios', function (Blueprint $t) {
            $t->id();

            // O que vai no perfil
            $t->string('nome', 150);
            $t->string('categoria', 120)->nullable();
            $t->text('descricao')->nullable();
            $t->string('site', 255)->nullable();
            $t->string('instagram', 120)->nullable();

            // Quem responde pelo negocio. O e-mail e o que recebe o convite de
            // propriedade do perfil no Google, entao sem ele nao da para cadastrar.
            $t->string('responsavel', 150);
            $t->string('email', 150);
            $t->string('whatsapp', 20);
            $t->string('telefone', 20)->nullable();
            $t->string('documento', 20)->nullable();

            // Endereco. O Google distingue loja com balcao de negocio que vai ao
            // cliente, e a diferenca muda o que ele exibe: sem isso o perfil de um
            // prestador que atende em casa publica o endereco residencial dele.
            $t->boolean('atende_no_endereco')->default(true);
            $t->string('cep', 9)->nullable();
            $t->string('logradouro', 180)->nullable();
            $t->string('numero', 20)->nullable();
            $t->string('complemento', 120)->nullable();
            $t->string('bairro', 120)->nullable();
            $t->string('cidade', 120)->nullable();
            $t->string('uf', 2)->nullable();

            // Texto livre de proposito: horario de loja nao cabe em colunas sem
            // virar sete pares de campos que ninguem preenche. Quem cadastra no
            // Google le e digita.
            $t->text('horarios')->nullable();

            $t->string('situacao', 20)->default('recebido')->index();

            // De onde veio, e com quem se liga. `lead_id` e a ponte com o CRM: o
            // mesmo contato pode ter nascido como lead e virar negocio.
            $t->string('origem', 60)->nullable();
            $t->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $t->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();

            $t->timestamp('cadastrado_no_google_em')->nullable();
            $t->text('observacao')->nullable();
            $t->timestamps();

            $t->index('cidade');
            $t->index('created_at');
        });

        // A plaquinha passa a poder apontar para o negocio. Nulo no que ja existe:
        // `cliente_nome` continua valendo, e ninguem reescreve venda feita.
        Schema::table('etiquetas', function (Blueprint $t) {
            $t->foreignId('negocio_id')->nullable()->after('cliente_contato')
                ->constrained('negocios')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('etiquetas', function (Blueprint $t) {
            $t->dropConstrainedForeignId('negocio_id');
        });

        Schema::dropIfExists('negocios');
    }
};
