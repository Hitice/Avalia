<?php

namespace App\Console\Commands;

use App\Models\Etiqueta;
use App\Models\LoteEtiqueta;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Apaga todos os codigos e campanhas.
 *
 * Existe para limpar o que foi gerado em teste, e nao para operacao. Depois de
 * a primeira placa sair para a rua, este comando e a coisa mais perigosa do
 * repositorio: ele nao distingue codigo de teste de codigo em balcao de
 * cliente, e o que ele apaga volta ao sorteio.
 *
 * Por isso exige `--confirmar` escrito a mao. Comando destrutivo que roda so
 * com o nome e comando que um dia entra numa lista de rotina por engano.
 *
 * Nao entra no agendador. Nunca.
 */
class LimparEtiquetas extends Command
{
    protected $signature = 'avalia:etiquetas-limpar {--confirmar : Apaga de verdade}';

    protected $description = 'Apaga todos os códigos e campanhas de QR dinâmico';

    public function handle(): int
    {
        $codigos = Etiqueta::count();
        $campanhas = LoteEtiqueta::count();

        if (! $this->option('confirmar')) {
            $this->warn("Apagaria {$codigos} código(s) e {$campanhas} campanha(s). Rode com --confirmar.");

            return self::SUCCESS;
        }

        // Na ordem das chaves estrangeiras: as filhas primeiro, senao o banco
        // recusa e a limpeza para no meio.
        DB::transaction(function () {
            DB::table('acessos_etiqueta')->delete();
            DB::table('destinos_etiqueta')->delete();
            DB::table('renovacoes_etiqueta')->delete();
            DB::table('etiquetas')->delete();
            DB::table('lotes_etiquetas')->delete();
        });

        $this->info("Apagados {$codigos} código(s) e {$campanhas} campanha(s). Restam ".Etiqueta::count().'.');

        return self::SUCCESS;
    }
}
