<?php

namespace Database\Seeders;

use App\Models\Staff;
use Illuminate\Database\Seeder;

/**
 * Cria o superusuario a partir do .env.
 *
 * E o unico dado semeado do sistema. Nao ha cliente, plano ou consulta de
 * exemplo: a decisao do produto e operar so com dado real.
 *
 * Idempotente — rodar de novo atualiza o registro em vez de duplicar.
 */
class AcessoSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL');
        $senha = env('ADMIN_SENHA');

        if (! $email || ! $senha) {
            $this->command->warn('ADMIN_EMAIL/ADMIN_SENHA ausentes no .env: nenhum admin criado.');

            return;
        }

        $admin = Staff::withTrashed()->firstOrNew(['email' => $email]);

        $admin->fill([
            'nome' => env('ADMIN_NOME', 'Administrador'),
            'papel' => 'admin',
            'super' => true,
            'ativo' => true,
        ]);

        // So (re)define a senha ao criar. Rodar o seeder de novo em producao
        // nao pode reverter uma senha que ja foi trocada pelo titular.
        if (! $admin->exists) {
            $admin->senha = $senha;
        }

        $admin->deleted_at = null;
        $admin->save();

        $this->command->info(
            $admin->wasRecentlyCreated
                ? "Superusuario criado: {$email}"
                : "Superusuario ja existia, dados conferidos: {$email}"
        );
    }
}
