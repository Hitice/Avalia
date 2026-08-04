<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modulo Acesso.
 *
 * Duas naturezas de conta que nunca se misturam:
 *   staff    -> quem opera a Avalia (admin e vendedor)
 *   clientes -> a empresa que contrata e consulta
 *
 * Aqui a tabela `clientes` nasce so com o que o login precisa. O modulo
 * Cadastro acrescenta depois a ficha completa (proposta comercial) numa
 * migration propria, porque migration e aditiva, entao nao ha remendo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff', function (Blueprint $t) {
            $t->id();
            $t->string('nome');
            $t->string('email')->unique();
            $t->string('senha');
            $t->enum('papel', ['admin', 'vendedor'])->index();

            // Superusuario ignora as policies. Existe um so, criado pelo seeder.
            $t->boolean('super')->default(false);
            $t->boolean('ativo')->default(true)->index();

            // Revogacao de sessao: incrementar derruba todas as sessoes da conta
            // na hora, sem depender de expiracao de cookie.
            $t->unsignedInteger('sessao_versao')->default(1);

            // Dados de repasse de comissao do vendedor.
            $t->string('cpf', 14)->nullable();
            $t->string('pix_chave')->nullable();
            $t->string('banco', 60)->nullable();
            $t->string('agencia', 20)->nullable();
            $t->string('conta', 30)->nullable();

            $t->timestamp('ultimo_acesso_em')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('clientes', function (Blueprint $t) {
            $t->id();
            $t->string('razao_social');
            $t->string('email')->unique();
            $t->string('senha');

            // Situacao comanda o acesso E a cobranca. So 'ativo' consulta.
            //   inadimplente -> suspenso por falta de pagamento (automatico)
            //   bloqueado    -> suspenso por decisao da gestao (manual)
            //   inativo      -> encerrado, mantido para historico fiscal
            $t->enum('situacao', ['ativo', 'inadimplente', 'bloqueado', 'inativo'])
                ->default('ativo')->index();

            $t->unsignedInteger('sessao_versao')->default(1);
            $t->timestamp('ultimo_acesso_em')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });

        // Forca bruta: contagem por conta E por origem, com castigo exponencial.
        // Em tabela e nao em cache porque precisa sobreviver a restart e virar
        // evidencia numa eventual apuracao de incidente.
        Schema::create('tentativas_login', function (Blueprint $t) {
            $t->id();
            $t->string('chave')->index();          // "conta:email" ou "origem:ip"
            $t->unsignedInteger('falhas')->default(0);
            $t->timestamp('bloqueado_ate')->nullable();
            $t->timestamp('ultima_falha_em')->nullable();
            $t->timestamps();

            $t->unique('chave', 'tentativas_login_chave_unica');
        });

        // Colunas no padrao do DatabaseSessionHandler: ele grava user_id,
        // ip_address e user_agent por convencao. Renomear quebra silenciosamente.
        Schema::create('sessions', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->foreignId('user_id')->nullable()->index();
            $t->string('ip_address', 45)->nullable();
            $t->text('user_agent')->nullable();
            $t->longText('payload');
            $t->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('tentativas_login');
        Schema::dropIfExists('clientes');
        Schema::dropIfExists('staff');
    }
};
