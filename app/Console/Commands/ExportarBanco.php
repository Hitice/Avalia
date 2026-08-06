<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Copia de seguranca do banco, em SQL, sem depender de pg_dump.
 *
 * A maquina de desenvolvimento nao tem cliente Postgres instalado, e esperar
 * que tenha e o que faz backup virar promessa. Isto usa a conexao que a
 * aplicacao ja tem.
 *
 * Exporta SOMENTE dados. A estrutura vive nas migrations e e reconstruida com
 * `migrate`, o que deixa o arquivo portatil: ele sobe em qualquer Postgres,
 * inclusive de outro provedor, sem carregar tipo, indice ou dono do banco de
 * origem.
 *
 * A ordem das tabelas sai do grafo de chaves estrangeiras do proprio banco, e
 * nao de uma lista escrita a mao: lista a mao envelhece na primeira migration
 * que ninguem lembrar de atualizar, e o erro so aparece na hora da restauracao.
 *
 * As sequencias sao reposicionadas no fim. Sem isso o primeiro registro criado
 * depois da restauracao colide com um id que ja existe.
 */
class ExportarBanco extends Command
{
    protected $signature = 'avalia:exportar
        {--saida= : Caminho do arquivo}
        {--reter= : Dias de cópias antigas a manter}
        {--destino= : Dialeto do banco que vai receber (pgsql ou mysql)}';

    protected $description = 'Exporta os dados do banco para um arquivo SQL restaurável';

    /**
     * Por quantos dias a copia diaria fica no disco.
     *
     * Duas semanas cobre o caso real de um erro que so e percebido depois: dado
     * apagado por engano na sexta costuma aparecer na segunda, e uma competencia
     * fechada errada aparece no fechamento seguinte. Guardar mais nao ajuda,
     * porque a partir de um ponto o certo e restaurar do backup externo.
     */
    public const DIAS_DE_RETENCAO = 14;

    /**
     * Tabelas que o destino refaz sozinho.
     *
     * `migrations` e do proprio migrador: o destino ja a preenche ao rodar
     * `migrate`, e restaurar por cima quebra com chave duplicada. Cache, fila e
     * sessao sao estado descartavel e so aumentariam o arquivo.
     */
    private const DESCARTAVEIS = [
        'migrations', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'sessions',
    ];

    public function handle(): int
    {
        if (! in_array($this->driver(), ['pgsql', 'mysql', 'mariadb'], true)) {
            $this->error('Este comando exporta de PostgreSQL ou MySQL.');

            return self::FAILURE;
        }

        $arquivo = $this->option('saida') ?: base_path('backup/avalia-'.now()->format('Y-m-d-Hi').'.sql');

        if (! is_dir(dirname($arquivo))) {
            mkdir(dirname($arquivo), 0755, true);
        }

        $saida = fopen($arquivo, 'w');

        fwrite($saida, '-- Avalia: dados exportados em '.now()->format('d/m/Y H:i')."\n");
        fwrite($saida, "-- Restaure com: php artisan migrate --force && php artisan avalia:importar ARQUIVO\n");
        fwrite($saida, "-- Somente dados. A estrutura vem das migrations.\n\n");

        $total = 0;

        foreach ($this->emOrdemDeDependencia() as $tabela) {
            $linhas = DB::table($tabela)->get();

            if ($linhas->isEmpty()) {
                continue;
            }

            fwrite($saida, "-- {$tabela}: {$linhas->count()}\n");

            foreach ($linhas as $linha) {
                fwrite($saida, $this->insert($tabela, (array) $linha)."\n");
            }

            fwrite($saida, "\n");
            $total += $linhas->count();
            $this->line(sprintf('  %-24s %6d', $tabela, $linhas->count()));
        }

        foreach ($this->sequencias() as $sql) {
            fwrite($saida, $sql."\n");
        }

        fclose($saida);

        $this->newLine();
        $this->info(sprintf(
            '%d registros em %s (%s KB)',
            $total,
            $arquivo,
            number_format(filesize($arquivo) / 1024, 1, ',', '.'),
        ));

        // Um arquivo com zero registro nao e backup: e o sinal de que a conexao
        // caiu no meio, ou de que alguem apontou o comando para o banco errado.
        // Melhor falhar e a rotina diaria acusar do que guardar um vazio que
        // parece uma copia.
        if ($total === 0) {
            $this->error('Nenhum registro exportado. Confira a conexão antes de confiar neste arquivo.');

            return self::FAILURE;
        }

        $this->expurgar(dirname($arquivo));

        return self::SUCCESS;
    }

    /**
     * Apaga copias mais velhas que a retencao.
     *
     * Sem isto o disco do servidor enche em silencio, e disco cheio derruba a
     * aplicacao inteira, nao so o backup. Apaga somente arquivo com o nome que
     * este comando gera: copia que alguem guardou a mao continua onde esta.
     */
    private function expurgar(string $pasta): void
    {
        $dias = (int) ($this->option('reter') ?: self::DIAS_DE_RETENCAO);

        if ($dias <= 0) {
            return;
        }

        $limite = now()->subDays($dias)->getTimestamp();
        $apagados = 0;

        foreach (glob($pasta.'/avalia-*.sql') ?: [] as $antigo) {
            if (filemtime($antigo) < $limite) {
                unlink($antigo);
                $apagados++;
            }
        }

        if ($apagados > 0) {
            $this->line("{$apagados} cópia(s) com mais de {$dias} dias apagada(s).");
        }
    }

    /**
     * Tabelas ordenadas de forma que nenhuma entre antes daquela a que aponta.
     *
     * Ordenacao topologica simples sobre as chaves estrangeiras. Ciclo entre
     * tabelas nao existe neste banco; se passar a existir, o que sobrar entra no
     * fim e a restauracao acusa, que e melhor do que exportar em ordem errada em
     * silencio.
     *
     * @return list<string>
     */
    private function emOrdemDeDependencia(): array
    {
        $listagem = $this->origemEhPostgres()
            ? "select tablename as nome from pg_tables where schemaname = 'public'"
            : "select table_name as nome from information_schema.tables
               where table_schema = database() and table_type = 'BASE TABLE'";

        $tabelas = collect(DB::select($listagem))
            ->pluck('nome')
            ->reject(fn (string $t) => in_array($t, self::DESCARTAVEIS, true))
            ->values()
            ->all();

        $depende = [];

        // Mesma pergunta, dois catalogos: o MySQL guarda a tabela apontada na
        // propria key_column_usage, e o Postgres exige a juncao com
        // constraint_column_usage.
        $chaves = $this->origemEhPostgres()
            ? "select tc.table_name as origem, ccu.table_name as destino
               from information_schema.table_constraints tc
               join information_schema.constraint_column_usage ccu
                 on ccu.constraint_name = tc.constraint_name
               where tc.constraint_type = 'FOREIGN KEY' and tc.table_schema = 'public'"
            : 'select table_name as origem, referenced_table_name as destino
               from information_schema.key_column_usage
               where table_schema = database() and referenced_table_name is not null';

        foreach (DB::select($chaves) as $fk) {
            // Autorreferencia nao cria dependencia entre tabelas.
            if ($fk->origem !== $fk->destino) {
                $depende[$fk->origem][$fk->destino] = true;
            }
        }

        $ordenadas = [];
        $restantes = $tabelas;

        while ($restantes !== []) {
            $prontas = array_values(array_filter(
                $restantes,
                fn (string $t) => empty(array_diff(array_keys($depende[$t] ?? []), $ordenadas)),
            ));

            if ($prontas === []) {
                // Ciclo: entrega o resto como esta em vez de repetir para sempre.
                return array_merge($ordenadas, $restantes);
            }

            $ordenadas = array_merge($ordenadas, $prontas);
            $restantes = array_values(array_diff($restantes, $prontas));
        }

        return $ordenadas;
    }

    /** @param  array<string, mixed>  $linha */
    private function insert(string $tabela, array $linha): string
    {
        $colunas = implode(', ', array_map($this->citar(...), array_keys($linha)));
        $valores = implode(', ', array_map($this->valor(...), $linha));

        return 'INSERT INTO '.$this->citar($tabela)." ({$colunas}) VALUES ({$valores});";
    }

    private function driver(): string
    {
        return DB::connection()->getDriverName();
    }

    /**
     * Banco de onde se le. Decide qual catalogo responde sobre tabelas e chaves.
     *
     * Separado do destino de proposito: com `--destino=mysql` rodando sobre uma
     * origem PostgreSQL, confundir os dois faria o comando perguntar ao catalogo
     * errado e nao achar tabela nenhuma.
     */
    private function origemEhPostgres(): bool
    {
        return $this->driver() === 'pgsql';
    }

    /**
     * Dialeto em que o arquivo e escrito.
     *
     * Por padrao e o da propria origem, que e o caso comum: copiar um banco para
     * outro igual. Com `--destino` passa a ser o do banco que vai receber, e e
     * isso que permite sair do PostgreSQL e entrar no MySQL, onde a citacao de
     * identificador e outra e sequencia nao existe.
     *
     * So a forma de escrever muda. Os dados sao os mesmos: nenhuma tabela daqui
     * usa recurso exclusivo de um dos dois, e a prova e a suite inteira rodando
     * em SQLite.
     */
    private function destinoEhPostgres(): bool
    {
        $destino = (string) ($this->option('destino') ?? '');

        return $destino === '' ? $this->origemEhPostgres() : $destino === 'pgsql';
    }

    /**
     * Nome de tabela ou coluna, com a marca que o banco de destino entende.
     *
     * Aspas duplas sao o padrao ANSI e o que o Postgres usa. O MySQL usa crase e
     * recusa aspas duplas fora do modo ANSI_QUOTES: sem isto, um arquivo
     * exportado do Postgres nao entra no MySQL de jeito nenhum.
     */
    private function citar(string $identificador): string
    {
        return $this->destinoEhPostgres() ? '"'.$identificador.'"' : '`'.$identificador.'`';
    }

    private function valor(mixed $valor): string
    {
        if ($valor === null) {
            return 'NULL';
        }

        if (is_bool($valor)) {
            return $valor ? 'true' : 'false';
        }

        if (is_int($valor) || is_float($valor)) {
            return (string) $valor;
        }

        // O texto sai como esta, com as quebras de linha que tiver. Nao existe
        // funcao de concatenacao que Postgres e SQLite aceitem igual: `chr` e de
        // um, `char` e do outro, e escolher uma amarra o arquivo a um banco so.
        // Quem junta as linhas de volta e o importador, e assim o arquivo
        // continua sendo SQL comum, que qualquer cliente executa.
        return $this->literal((string) $valor);
    }

    /** Aspas dobradas: e o escape que o proprio SQL usa para texto literal. */
    private function literal(string $texto): string
    {
        return "'".str_replace("'", "''", $texto)."'";
    }

    /**
     * Reposiciona cada sequencia depois do maior id gravado.
     *
     * Sem isto o proximo registro criado tenta reutilizar um id existente e a
     * primeira gravacao depois da restauracao falha com chave duplicada.
     *
     * @return list<string>
     */
    private function sequencias(): array
    {
        // O MySQL nao tem sequencia. O contador do AUTO_INCREMENT se ajusta
        // sozinho quando um id explicito maior que ele e gravado, entao ao fim da
        // restauracao ele ja esta em max(id) + 1 sem ninguem mandar.
        if (! $this->destinoEhPostgres()) {
            return [];
        }

        $sql = ["\n-- Sequencias, para o proximo id nao colidir com o que foi restaurado"];

        // Quem e o dono de cada sequencia vem do proprio catalogo do Postgres, e
        // nao do nome dela. Tirar `_id_seq` do nome parece equivalente e nao e:
        // `catalogos` foi renomeada e a sequencia continuou `versoes_catalogo_id_seq`.
        // Pelo nome, o comando apontaria para uma tabela que nao existe, e a
        // sequencia de catalogos nunca seria reposicionada.
        $donos = DB::select("
            select s.relname as sequencia, t.relname as tabela, a.attname as coluna
            from pg_class s
            join pg_depend d on d.objid = s.oid and d.deptype = 'a'
            join pg_class t on t.oid = d.refobjid
            join pg_attribute a on a.attrelid = t.oid and a.attnum = d.refobjsubid
            join pg_namespace n on n.oid = s.relnamespace
            where s.relkind = 'S' and n.nspname = 'public'
            order by t.relname
        ");

        foreach ($donos as $dono) {
            if (in_array($dono->tabela, self::DESCARTAVEIS, true)) {
                continue;
            }

            $sql[] = sprintf(
                'SELECT setval(\'%s\', coalesce((SELECT max("%s") FROM "%s"), 1), true);',
                $dono->sequencia,
                $dono->coluna,
                $dono->tabela,
            );
        }

        return $sql;
    }
}
