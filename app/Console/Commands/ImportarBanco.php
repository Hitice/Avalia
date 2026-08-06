<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Restaura o arquivo gerado por `avalia:exportar`.
 *
 * Existe porque a maquina nao tem `psql`, e backup que so restaura com uma
 * ferramenta que ninguem instalou nao e backup.
 *
 * Roda tudo em uma transacao: ou o banco fica inteiro, ou fica como estava. A
 * pior restauracao possivel e a que para no meio e deixa metade das tabelas
 * preenchidas, porque parece ter dado certo.
 *
 * Espera as tabelas ja criadas por `migrate`, e vazias. O arquivo so tem
 * INSERT: a estrutura e responsabilidade das migrations.
 */
class ImportarBanco extends Command
{
    protected $signature = 'avalia:importar {arquivo} {--force : Não perguntar}';

    protected $description = 'Restaura os dados de um arquivo gerado por avalia:exportar';

    public function handle(): int
    {
        $arquivo = $this->argument('arquivo');

        if (! is_readable($arquivo)) {
            $this->error("Arquivo não encontrado: {$arquivo}");

            return self::FAILURE;
        }

        $comandos = $this->comandos(file_get_contents($arquivo));

        if ($comandos === []) {
            $this->error('O arquivo não tem nenhum comando. Confira se a exportação terminou.');

            return self::FAILURE;
        }

        $this->line(count($comandos).' comandos em '.basename($arquivo));

        if (! $this->option('force') && ! $this->confirm('Restaurar sobre o banco atual?', false)) {
            return self::FAILURE;
        }

        $barra = $this->output->createProgressBar(count($comandos));

        try {
            DB::transaction(function () use ($comandos, $barra) {
                foreach ($comandos as $sql) {
                    DB::unprepared($sql);
                    $barra->advance();
                }
            });
        } catch (\Throwable $e) {
            $barra->finish();
            $this->newLine(2);
            $this->error('Nada foi gravado. O banco está como estava antes.');
            $this->line($e->getMessage());

            return self::FAILURE;
        }

        $barra->finish();
        $this->newLine(2);
        $this->info('Restaurado.');

        return self::SUCCESS;
    }

    /**
     * Separa os comandos, juntando o que uma aspas aberta deixou pendurado.
     *
     * Conteudo de documento tem paragrafo, e a quebra de linha vai literal para
     * o arquivo. Cortar por linha partiria esse INSERT em pedacos e cada pedaco
     * viraria um comando invalido.
     *
     * A regra e a paridade das aspas: dentro de um literal, `''` e o escape de
     * uma aspas, entao removendo os pares o que sobra e a aspas que abre ou
     * fecha. Numero par significa que estamos fora de literal, e so ai um `;`
     * no fim da linha e realmente o fim do comando.
     *
     * @return list<string>
     */
    private function comandos(string $conteudo): array
    {
        $comandos = [];
        $acumulado = '';

        foreach (preg_split('/\R/', $conteudo) as $linha) {
            if ($acumulado === '' && (trim($linha) === '' || str_starts_with(trim($linha), '--'))) {
                continue;
            }

            $acumulado .= ($acumulado === '' ? '' : "\n").$linha;

            if (str_ends_with(rtrim($acumulado), ';') && $this->foraDeLiteral($acumulado)) {
                $comandos[] = trim($acumulado);
                $acumulado = '';
            }
        }

        // Sobra significa aspas que nunca fechou: arquivo truncado ou corrompido.
        if (trim($acumulado) !== '') {
            $comandos[] = trim($acumulado);
        }

        return $comandos;
    }

    /** Verdadeiro quando todas as aspas do trecho ja foram fechadas. */
    private function foraDeLiteral(string $trecho): bool
    {
        return substr_count(str_replace("''", '', $trecho), "'") % 2 === 0;
    }
}
