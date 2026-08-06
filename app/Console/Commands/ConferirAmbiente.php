<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Confere a configuracao antes de a aplicacao atender alguem.
 *
 * Erro de configuracao nao quebra nada: a tela abre, o login funciona, e a
 * aplicacao segue servindo com o depurador ligado ou o cookie de sessao viajando
 * em texto claro. Ninguem descobre olhando, porque nao ha o que olhar.
 *
 * Em servidor proprio isso deixa de ser problema do provedor. O que aqui era
 * padrao seguro da hospedagem gerenciada passa a depender de alguem ter
 * lembrado, e este comando e o "alguem".
 *
 * Roda no fim do deploy. Falha impede a versao nova de entrar no ar, que e
 * melhor do que subir e descobrir depois.
 */
class ConferirAmbiente extends Command
{
    protected $signature = 'avalia:ambiente';

    protected $description = 'Verifica se a configuração do ambiente é segura para produção';

    /** @var list<array{item: string, estado: string, detalhe: string}> */
    private array $achados = [];

    public function handle(): int
    {
        $producao = app()->environment('production');

        $this->line('Ambiente: '.app()->environment());
        $this->newLine();

        $this->segredos($producao);
        $this->sessao($producao);
        $this->banco($producao);
        $this->arquivos();
        $this->rotinas($producao);

        $this->newLine();

        $falhas = array_filter($this->achados, fn (array $a) => $a['estado'] === 'erro');

        foreach ($this->achados as $achado) {
            $marca = match ($achado['estado']) {
                'ok' => '<info>ok  </info>',
                'erro' => '<error>erro</error>',
                default => '<comment>  - </comment>',
            };

            $this->line(sprintf('  %s %-42s %s', $marca, $achado['item'], $achado['detalhe']));
        }

        $this->newLine();

        if ($falhas !== []) {
            $this->error(count($falhas).' item(ns) impedem este ambiente de atender em produção.');

            return self::FAILURE;
        }

        $this->info('Configuração conferida.');

        return self::SUCCESS;
    }

    /**
     * Registra um item conferido.
     *
     * Tres estados, e nao dois. Checagem que so vale em producao aparece como
     * nao aplicavel em vez de verde: marcar como aprovada uma verificacao que
     * nao foi feita e o jeito mais rapido de a lista inteira perder o sentido.
     *
     * O detalhe so aparece quando explica a falha ou quando e o proprio valor
     * conferido. Mostrar "APP_DEBUG=true" ao lado de um ok confunde mais do que
     * informa.
     */
    private function anota(string $item, bool $ok, string $detalhe = '', bool $mostrarSempre = false): void
    {
        $this->achados[] = [
            'item' => $item,
            'estado' => $ok ? 'ok' : 'erro',
            'detalhe' => (! $ok || $mostrarSempre) ? $detalhe : '',
        ];
    }

    /** Item que so faz sentido conferir em producao, e este nao e o caso. */
    private function pula(string $item): void
    {
        $this->achados[] = ['item' => $item, 'estado' => 'pulado', 'detalhe' => 'só em produção'];
    }

    /**
     * Alguma coisa da aplicacao vai para a fila?
     *
     * Le o codigo em vez de perguntar a configuracao, porque a pergunta e sobre
     * intencao: uma classe que implementa ShouldQueue existe para ser processada
     * fora da requisicao, e a partir do momento em que a primeira aparece, a fila
     * sincrona deixa de ser aceitavel e a hospedagem compartilhada deixa de
     * servir. E o dia em que este item precisa reprovar sozinho, sem depender de
     * alguem lembrar.
     */
    /**
     * A aplicacao manda algum e-mail?
     *
     * Mesma leitura de intencao que a da fila: a fachada Mail ou uma classe
     * Mailable so existem para enviar. Enquanto nao houver nenhuma, o driver que
     * grava em arquivo nao esconde nada, porque nao ha nada a esconder.
     */
    private function existeEnvioDeEmail(): bool
    {
        return $this->codigoContem('Illuminate\Support\Facades\\'.'Mail;')
            || $this->codigoContem('Illuminate\Contracts\Mail\\'.'Mailable;');
    }

    private function existeTrabalhoEnfileirado(): bool
    {
        return $this->codigoContem('Illuminate\Contracts\Queue\\'.'ShouldQueue;');
    }

    /**
     * Algum arquivo de `app/` importa isto?
     *
     * Procura o import, e nao o nome solto: o nome aparece neste proprio arquivo,
     * que ficaria se acusando para sempre. Pelo mesmo motivo, o arquivo que faz a
     * busca fica de fora dela.
     */
    private function codigoContem(string $import): bool
    {
        $arquivos = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(app_path(), \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($arquivos as $arquivo) {
            if ($arquivo->getExtension() !== 'php' || $arquivo->getPathname() === __FILE__) {
                continue;
            }

            if (str_contains((string) file_get_contents($arquivo->getPathname()), 'use '.$import)) {
                return true;
            }
        }

        return false;
    }

    private function segredos(bool $producao): void
    {
        $this->anota('Chave da aplicação definida', config('app.key') !== null && config('app.key') !== '');

        // Depurador ligado imprime a stack, o trecho do arquivo e o conteudo do
        // ambiente na propria tela de erro. E o vazamento mais barato que existe.
        $producao
            ? $this->anota('Depurador desligado', config('app.debug') === false, 'APP_DEBUG=true')
            : $this->pula('Depurador desligado');

        $url = (string) config('app.url');

        $producao
            ? $this->anota('Endereço em HTTPS', str_starts_with($url, 'https://'), $url)
            : $this->pula('Endereço em HTTPS');

        // Servidor apontado para a raiz do projeto em vez de /public deixa .env,
        // storage e vendor acessiveis pela web.
        $this->anota(
            'Raiz do servidor em public',
            ! str_contains($url, '/public'),
            str_contains($url, '/public') ? 'APP_URL aponta para /public' : '',
        );
    }

    private function sessao(bool $producao): void
    {
        // Cookie sem `secure` viaja em texto claro na primeira requisicao HTTP e
        // e o suficiente para alguem na mesma rede assumir a sessao.
        $producao
            ? $this->anota('Cookie de sessão só por HTTPS', config('session.secure') === true, 'SESSION_SECURE_COOKIE=false')
            : $this->pula('Cookie de sessão só por HTTPS');

        $this->anota(
            'Cookie inacessível ao navegador',
            config('session.http_only') === true,
        );

        $this->anota(
            'Cookie restrito a este site',
            in_array(config('session.same_site'), ['strict', 'lax'], true),
            (string) config('session.same_site'),
            true,
        );
    }

    private function banco(bool $producao): void
    {
        $driver = DB::connection()->getDriverName();

        // PostgreSQL ou MySQL, e nao um so: a aplicacao nao depende de recurso
        // exclusivo de nenhum dos dois, e a prova e a suite inteira rodando em
        // SQLite. O que este item pega e producao servida a partir do arquivo
        // SQLite de teste, que nao aguenta dois acessos simultaneos.
        //
        // Conferido so em producao porque a suite usa SQLite de proposito: teste
        // em memoria e o que a deixa rapida o bastante para rodar a cada mudanca.
        $producao
            ? $this->anota('Banco de produção', in_array($driver, ['pgsql', 'mysql', 'mariadb'], true), $driver, true)
            : $this->pula('Banco de produção');

        try {
            $inicio = microtime(true);
            DB::select('select 1');
            $ida = (microtime(true) - $inicio) * 1000;

            // Banco no mesmo servidor responde em fracao de milissegundo. Dezenas
            // de milissegundos significam banco remoto, e cada tela paga isso
            // multiplicado pelo numero de consultas dela.
            $this->anota(
                'Banco responde',
                true,
                number_format($ida, 1, ',', '.').' ms'.($ida > 20 ? '  <comment>banco remoto: cada tela paga isto por consulta</comment>' : ''),
                true,
            );
        } catch (\Throwable $e) {
            $this->anota('Banco responde', false, $e->getMessage());
        }

        // Prepare emulado quebra booleano no Postgres, e a suite roda em SQLite,
        // onde o mesmo SQL funciona: o teste passa e a producao recusa a consulta.
        $this->anota(
            'Prepare nativo (não emulado)',
            ! env('DB_EMULA_PREPARE', false),
            env('DB_EMULA_PREPARE', false) ? 'DB_EMULA_PREPARE=true' : '',
        );
    }

    private function arquivos(): void
    {
        foreach (['storage/framework', 'storage/logs', 'bootstrap/cache'] as $pasta) {
            $this->anota("Gravável: {$pasta}", is_writable(base_path($pasta)));
        }

        // A pasta de copias tem dado de cliente e hash de senha. Dentro de
        // public, cada arquivo vira um download aberto.
        $backup = base_path('backup');

        $this->anota(
            'Cópias do banco fora de public',
            ! str_starts_with(realpath($backup) ?: $backup, realpath(public_path()) ?: public_path()),
        );
    }

    private function rotinas(bool $producao): void
    {
        // Fila em `sync` executa o trabalho dentro da requisicao do usuario: a
        // tela trava pelo tempo do processamento e a falha vira erro na cara dele.
        //
        // So que isso so e problema se houver o que enfileirar. Hoje nao ha, e
        // em hospedagem compartilhada `sync` e a unica opcao possivel, porque
        // ela nao mantem processo de pe. Reprovar aqui seria reprovar o ambiente
        // por um defeito que nao existe, e alarme que toca sem motivo e alarme
        // que se aprende a ignorar.
        $enfileira = $this->existeTrabalhoEnfileirado();
        $sincrona = config('queue.default') === 'sync';

        $this->anota(
            'Fila compatível com o que a aplicação enfileira',
            ! $producao || ! $sincrona || ! $enfileira,
            $sincrona
                ? ($enfileira ? 'há trabalho enfileirado e a fila é síncrona' : 'síncrona, e nada é enfileirado')
                : (string) config('queue.default'),
            true,
        );

        // Driver `log` grava a mensagem no arquivo em vez de enviar. Recuperacao
        // de senha e aviso de vencimento sairiam sem erro nenhum e sem chegar.
        //
        // Mesma logica da fila: so e defeito se houver o que enviar. Hoje a
        // aplicacao nao envia nada, e barrar a publicacao por isso seria alarme
        // sem defeito, do tipo que se aprende a ignorar. O dia em que o primeiro
        // e-mail existir, este item passa a reprovar sozinho.
        $envia = $this->existeEnvioDeEmail();
        $emArquivo = config('mail.default') === 'log';

        $this->anota(
            'Envio de e-mail compatível com o que a aplicação manda',
            ! $producao || ! $emArquivo || ! $envia,
            $emArquivo
                ? ($envia ? 'a aplicação envia e-mail e o driver é "log"' : 'driver "log", e nada é enviado')
                : (string) config('mail.default'),
            true,
        );
    }
}
