<?php

namespace App\Console\Commands;

use App\Models\Auditoria;
use App\Models\Cliente;
use App\Models\Fatura;
use App\Models\Staff;
use Illuminate\Console\Command;

/**
 * O que impede excluir em definitivo uma conta ou uma empresa.
 *
 * Somente leitura. Responde a pergunta que a tela responde com um "nao" curto:
 * a exclusao definitiva e recusada quando existe historico apontando para o
 * registro, e quem esta na tela nao ve QUANTO nem DE QUE.
 *
 * Existe tambem por um motivo de operacao: sem SSH, conferir o banco depende de
 * cron, e o campo de comando do cron so aceita dois niveis de aspas (a API do
 * provedor engole barra invertida). Consulta com string PHP nao cabe la. Um
 * comando com nome proprio se chama sem aspas nenhuma.
 */
class ConferirExclusao extends Command
{
    protected $signature = 'avalia:conferir-exclusao
                            {--email= : conta da equipe}
                            {--empresa= : parte da razao social}';

    protected $description = 'Diz o que impede a exclusao definitiva de uma conta ou empresa';

    public function handle(): int
    {
        if ($email = $this->option('email')) {
            $this->conferirStaff($email);
        }

        if ($empresa = $this->option('empresa')) {
            $this->conferirEmpresa($empresa);
        }

        return self::SUCCESS;
    }

    private function conferirStaff(string $email): void
    {
        $staff = Staff::withTrashed()->firstWhere('email', $email);

        if (! $staff) {
            $this->line("staff {$email}: nao existe");

            return;
        }

        // As mesmas tres condicoes de EquipeController::excluir. Repetidas aqui
        // de proposito: este comando existe para explicar aquela recusa, e a
        // explicacao tem de sair da mesma pergunta.
        $carteira = Cliente::withTrashed()->where('vendedor_id', $staff->id)->count();
        $faturas = Fatura::where('vendedor_id', $staff->id)->count();
        $trilha = Auditoria::where('staff_id', $staff->id)->count();

        $this->line("staff {$email}: id={$staff->id}"
            .' removida='.($staff->deleted_at ? 'sim' : 'nao')
            ." carteira={$carteira} faturas={$faturas} trilha={$trilha}");

        $this->line($carteira + $faturas + $trilha === 0
            ? '  pode excluir'
            : '  NAO pode excluir: o historico aponta para ela');
    }

    private function conferirEmpresa(string $parte): void
    {
        $empresas = Cliente::withTrashed()
            ->where('razao_social', 'like', '%'.$parte.'%')
            ->get(['id', 'razao_social', 'deleted_at']);

        if ($empresas->isEmpty()) {
            $this->line("empresa {$parte}: nao existe");

            return;
        }

        foreach ($empresas as $empresa) {
            $faturas = $empresa->faturas()->count();
            $consultas = $empresa->consultas()->count();
            $aceites = $empresa->aceitesDocumentos()->count();
            $operadores = $empresa->operadores()->withTrashed()->count();

            $this->line("empresa {$empresa->razao_social}: id={$empresa->id}"
                .' removida='.($empresa->deleted_at ? 'sim' : 'nao')
                ." faturas={$faturas} consultas={$consultas} aceites={$aceites} operadores={$operadores}");

            $this->line($faturas + $consultas + $aceites === 0
                ? '  pode excluir (os operadores saem junto)'
                : '  NAO pode excluir: o historico e dela');
        }
    }
}
