<?php

namespace App\Actions\Etiquetas;

use App\Models\Etiqueta;
use App\Support\Visitante;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Soma uma leitura de plaquinha.
 *
 * Duas decisoes moram aqui, e as duas sao de negocio.
 *
 * A primeira: a contagem NUNCA derruba o redirecionamento. O produto e mandar
 * o freguês para a loja do cliente; o contador e relatorio. Banco fora do ar,
 * tabela travada ou disco cheio precisam deixar a plaquinha funcionando, e por
 * isso a falha aqui vira linha de log e nada mais.
 *
 * A segunda: a contagem e por DIA, e nao por leitura. Uma placa em porta de
 * loja movimentada geraria dezenas de milhares de linhas por mes numa
 * hospedagem compartilhada, sem responder melhor a unica pergunta que o
 * cliente faz, que e quantas leituras teve no mes.
 *
 * Escreve pelo query builder, e nao pelo model, de proposito: `save()` no
 * model dispararia o evento que limpa o cache do destino, e o cache da rota
 * mais quente do sistema seria invalidado a cada encostada de celular.
 */
class RegistrarAcesso
{
    public function __invoke(Etiqueta $etiqueta, ?string $agente = null, string $metodo = 'GET'): void
    {
        // HEAD e o que o provedor de previa manda antes de buscar a pagina.
        if ($metodo === 'HEAD' || Visitante::ehRobo($agente)) {
            return;
        }

        try {
            $this->somar($etiqueta);
        } catch (\Throwable $erro) {
            Log::warning('Falhou ao contar leitura de etiqueta', [
                'etiqueta_id' => $etiqueta->id,
                'erro' => $erro->getMessage(),
            ]);
        }
    }

    private function somar(Etiqueta $etiqueta): void
    {
        $dia = now()->toDateString();

        $linhas = DB::table('acessos_etiqueta')
            ->where('etiqueta_id', $etiqueta->id)
            ->where('dia', $dia)
            ->increment('total');

        // Primeira leitura do dia. O `catch` cobre duas leituras simultaneas
        // disputando a criacao da mesma linha: a que perder a corrida soma na
        // linha que a outra acabou de criar, em vez de perder a contagem.
        if ($linhas === 0) {
            try {
                DB::table('acessos_etiqueta')->insert([
                    'etiqueta_id' => $etiqueta->id,
                    'dia' => $dia,
                    'total' => 1,
                ]);
            } catch (QueryException) {
                DB::table('acessos_etiqueta')
                    ->where('etiqueta_id', $etiqueta->id)
                    ->where('dia', $dia)
                    ->increment('total');
            }
        }

        DB::table('etiquetas')->where('id', $etiqueta->id)->update([
            'total_acessos' => DB::raw('total_acessos + 1'),
            'ultimo_acesso_em' => now(),
        ]);
    }
}
