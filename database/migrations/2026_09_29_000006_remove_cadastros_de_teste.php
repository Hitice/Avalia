<?php

use App\Models\Cliente;
use App\Models\Staff;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Apaga em definitivo os dois cadastros de teste que abriram a operacao.
 *
 * A empresa "Catech Ind" e a conta `pedromuska@gmail.com` foram usadas para
 * exercitar o sistema antes de existir cliente. As telas recusam a exclusao
 * delas, e a recusa esta CERTA como regra geral: consulta, fatura e aceite sao
 * historico do cliente, e carteira e fatura apontam para quem vendeu.
 *
 * A excecao e declarada pelo dono do dado, que e quem sabe que este historico
 * nao e de ninguem. Ela vem por migration, e nao por SQL solto, para ficar
 * versionada, revisavel e com o motivo escrito ao lado do que ela apaga.
 *
 * A ORDEM importa: a empresa sai primeiro. A carteira e as faturas que prendem
 * a conta sao as dela, entao com a empresa fora a conta deixa de ter historico
 * e sai pelo mesmo criterio que a tela usaria.
 *
 * O banco faz a maior parte: `clientes` recebe `cascadeOnDelete` de consultas,
 * faturas, aceites, operadores, adesao, cobrancas e elegibilidade de campanha.
 * `itens_fatura` cai junto com a fatura. Nao ha exclusao manual de filho aqui,
 * e e de proposito: lista escrita a mao envelhece a cada tabela nova.
 *
 * O que NAO se apaga: a trilha de auditoria. `auditoria.staff_id` e
 * `nullOnDelete`, entao as linhas da conta ficam com autor nulo. No caso desta
 * conta isso nao custa nada, porque ela tem zero linhas de trilha (conferido em
 * producao com `avalia:conferir-exclusao` antes desta migration). Se um dia
 * alguem reusar este arquivo como modelo: com trilha > 0, o `staff_id` zerado
 * quebra o resumo encadeado, porque ele entra no hash.
 *
 * O que se perde junto: as 7 consultas da empresa. Consulta guarda quem, quando
 * e qual documento, que e o registro pelo qual um titular pode perguntar o que
 * foi consultado sobre ele nos ultimos 12 meses. Apagar e legitimo aqui porque
 * o consulente e o titular do teste sao a mesma casa.
 */
return new class extends Migration
{
    private const EMPRESA = 'Catech';

    private const CONTA = 'pedromuska@gmail.com';

    public function up(): void
    {
        DB::transaction(function () {
            $this->removerEmpresa();
            $this->removerConta();
        });
    }

    private function removerEmpresa(): void
    {
        $empresas = Cliente::withTrashed()
            ->where('razao_social', 'like', '%'.self::EMPRESA.'%')
            ->get();

        foreach ($empresas as $empresa) {
            // Operadores tem exclusao logica propria: o cascade do banco nao
            // alcanca o que o Eloquent so marcou como removido.
            $empresa->operadores()->withTrashed()->forceDelete();
            $empresa->forceDelete();
        }
    }

    /**
     * A conta sai so depois da empresa, e so se nada mais apontar para ela.
     *
     * A conferencia repete a da tela de propósito: se a empresa nao era a unica
     * coisa presa a esta conta, a migration nao apaga e a publicacao segue. Uma
     * limpeza de teste nao pode levar junto o que ninguem examinou.
     */
    private function removerConta(): void
    {
        $conta = Staff::withTrashed()->firstWhere('email', self::CONTA);

        if ($conta === null) {
            return;
        }

        $aindaPreso = Cliente::withTrashed()->where('vendedor_id', $conta->id)->exists()
            || DB::table('faturas')->where('vendedor_id', $conta->id)->exists()
            || DB::table('auditoria')->where('staff_id', $conta->id)->exists();

        if ($aindaPreso) {
            return;
        }

        $conta->forceDelete();
    }

    /**
     * Sem volta.
     *
     * Exclusao definitiva nao se desfaz, e inventar os registros de novo seria
     * pior que nao ter: dado recriado por migration nao e o dado que existia.
     */
    public function down(): void {}
};
