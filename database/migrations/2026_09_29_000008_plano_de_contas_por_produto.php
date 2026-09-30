<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fase 1 do PLANO-FINANCEIRO.md: conta separada por produto e por natureza.
 *
 * Hoje o razao tem tres contas, `caixa`, `receita` e `despesa`, e com elas nao
 * ha como perguntar quanto o Avalia One deu de lucro no mes. A pergunta e
 * respondida hoje recalculando `faturas` em tela, e e dai que saem os 24
 * arquivos que tocam comissao e a divergencia de R$ 169,84 contra R$ 148,61 no
 * mesmo painel.
 *
 * Resultado por produto vira saldo de conta, e nao soma refeita por quem
 * pergunta. Sem isso, nenhuma fase seguinte tem onde lancar.
 *
 * As tres contas antigas FICAM. Lancamento manual do socio continua caindo nelas,
 * e mover partida existente para conta nova reescreveria historico que o razao
 * precisa continuar explicando. As novas comecam zeradas e recebem do lastro
 * (Fase 3).
 *
 * Codigo com `:` segue o que ja existe em `aporte:<id>` e `emprestimo:<id>`.
 * Grupo decide o lado em que a conta cresce, e `ContaFinanceira::saldoCents()`
 * ja le isso sem tabela de regra por conta.
 */
return new class extends Migration
{
    /** Os tres produtos, na grafia que os codigos de conta usam. */
    private const PRODUTOS = [
        'one' => 'Avalia One',
        'gestor' => 'Avalia Gestor',
        'plaquinha' => 'QR dinamico',
    ];

    public function up(): void
    {
        $agora = now();

        foreach ($this->contas() as $conta) {
            if (DB::table('contas_financeiras')->where('codigo', $conta['codigo'])->exists()) {
                continue;
            }

            DB::table('contas_financeiras')->insert($conta + [
                'ativa' => true,
                'created_at' => $agora,
                'updated_at' => $agora,
            ]);
        }
    }

    /** @return list<array{codigo: string, nome: string, grupo: string}> */
    private function contas(): array
    {
        $contas = [
            // O que o cliente deve e ainda nao pagou. Fatura fechada credita
            // receita e debita aqui; a liquidacao move daqui para o caixa. Sem
            // essa conta, receita e caixa viram a mesma coisa, e o mes fecha com
            // dinheiro que ninguem recebeu.
            ['codigo' => 'clientes-a-receber', 'nome' => 'Clientes a receber', 'grupo' => 'ativo'],

            // Imposto sai da nota cheia e antes do custo, na ordem que a PDD fixa.
            ['codigo' => 'imposto', 'nome' => 'Imposto sobre a receita', 'grupo' => 'despesa'],

            // Comissao e despesa quando apurada, e passivo enquanto nao e paga.
            // Separar as duas e o que permite responder "quanto devo ao Warley"
            // sem varrer venda por venda.
            ['codigo' => 'comissao', 'nome' => 'Comissao apurada', 'grupo' => 'despesa'],
            ['codigo' => 'comissao-a-pagar', 'nome' => 'Comissao a pagar', 'grupo' => 'passivo'],

            // Taxa do provedor de cobranca, que no Gestor sai antes do split e
            // por isso nunca foi receita nossa.
            ['codigo' => 'taxa-provedor', 'nome' => 'Taxa do provedor de cobranca', 'grupo' => 'despesa'],
        ];

        foreach (self::PRODUTOS as $chave => $nome) {
            $contas[] = ['codigo' => "receita:{$chave}", 'nome' => "Receita {$nome}", 'grupo' => 'receita'];
            $contas[] = ['codigo' => "custo:{$chave}", 'nome' => "Custo {$nome}", 'grupo' => 'despesa'];
        }

        return $contas;
    }

    /** Sem volta, pela mesma razao da semeadura das tres primeiras contas. */
    public function down(): void {}
};
