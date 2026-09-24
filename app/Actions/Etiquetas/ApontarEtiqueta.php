<?php

namespace App\Actions\Etiquetas;

use App\Exceptions\Recusa;
use App\Models\DestinoEtiqueta;
use App\Models\Etiqueta;
use App\Support\Auditar;
use App\Support\Destino;
use Illuminate\Support\Facades\DB;

/**
 * Aponta a plaquinha para um endereco, e guarda para onde ela apontava antes.
 *
 * Serve a primeira vez e a milesima: ativar uma placa em branco e trocar o
 * destino de uma placa vendida ha dois anos sao a mesma operacao, e escrever
 * duas faria uma delas esquecer o historico.
 *
 * O historico existe porque "para onde essa placa apontava em marco" vai ser
 * perguntado, e a coluna `destino` so sabe responder pelo presente. A
 * auditoria da casa registra quem fez; esta tabela guarda a linha do tempo.
 *
 * O cache do redirecionamento nao e limpo aqui: o evento `saved` do model faz
 * isso sozinho, e assim nenhuma forma futura de trocar destino pode esquecer.
 */
class ApontarEtiqueta
{
    public function __invoke(Etiqueta $etiqueta, string $destino): Etiqueta
    {
        $novo = Destino::normalizar($destino);

        if ($problema = Destino::problema($novo)) {
            throw new Recusa($problema);
        }

        if ($novo === $etiqueta->destino) {
            return $etiqueta;
        }

        return DB::transaction(function () use ($etiqueta, $novo) {
            $anterior = $etiqueta->destino;
            $agora = now();

            // Fecha a linha aberta antes de abrir a proxima: duas linhas sem
            // `vigorou_ate` deixariam o historico sem saber qual e a atual.
            $etiqueta->destinos()->vigente()->update(['vigorou_ate' => $agora]);

            DestinoEtiqueta::create([
                'etiqueta_id' => $etiqueta->id,
                'destino' => $novo,
                'vigorou_de' => $agora,
                'staff_id' => auth('staff')->id(),
            ]);

            $etiqueta->update(['destino' => $novo]);

            Auditar::registrar('etiquetas.destino.trocado', $etiqueta, [
                'de' => $anterior,
                'para' => $novo,
            ]);

            return $etiqueta->refresh();
        });
    }
}
