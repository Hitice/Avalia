<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Quantas linhas cada tabela tem em producao.
 *
 * Somente leitura. Serve a uma pergunta que nenhuma tela responde: das tabelas
 * que existem, quais carregam operacao e quais sobraram de ideia abandonada.
 * Consolidar o financeiro sem saber isso e adivinhar qual migracao tem risco.
 *
 * Sem SSH, a unica via de banco e o cron, e o campo de comando do provedor so
 * aceita dois niveis de aspas. Por isso a consulta mora num comando com nome
 * proprio, e nao numa string passada por parametro.
 */
class Inventario extends Command
{
    protected $signature = 'avalia:inventario {--vazias : lista tambem as tabelas sem nenhuma linha}';

    protected $description = 'Conta as linhas de cada tabela do banco';

    public function handle(): int
    {
        $tabelas = collect(Schema::getTableListing())
            ->reject(fn ($tabela) => in_array($tabela, ['cache', 'cache_locks', 'sessions', 'jobs', 'job_batches', 'failed_jobs']))
            ->mapWithKeys(fn ($tabela) => [$tabela => DB::table($tabela)->count()])
            ->sortDesc();

        $comDado = $tabelas->filter();
        $vazias = $tabelas->filter(fn ($n) => $n === 0);

        $this->line('COM DADO ('.$comDado->count().')');

        foreach ($comDado as $tabela => $linhas) {
            $this->line(str_pad((string) $linhas, 7, ' ', STR_PAD_LEFT).'  '.$tabela);
        }

        $this->line('');
        $this->line('VAZIAS ('.$vazias->count().')');

        if ($this->option('vazias')) {
            foreach ($vazias->keys()->sort() as $tabela) {
                $this->line('        '.$tabela);
            }
        } else {
            $this->line('        '.$vazias->keys()->sort()->implode(' '));
        }

        return self::SUCCESS;
    }
}
