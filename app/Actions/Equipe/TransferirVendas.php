<?php

namespace App\Actions\Equipe;

use App\Exceptions\Recusa;
use App\Models\Cliente;
use App\Models\Etiqueta;
use App\Models\Fatura;
use App\Models\Negocio;
use App\Models\Staff;
use App\Support\Auditar;
use Illuminate\Support\Facades\DB;

/**
 * Passa o que uma conta vendeu para outra: placas (vendidas, em maos e
 * geradas) e os negocios que ela cadastrou. A carteira do One (clientes e
 * faturas) so quando pedido, porque muda quem recebe comissao de fatura ja
 * liberada. Depois, relancar-plaquinhas acerta a comissao no razao.
 */
class TransferirVendas
{
    /** @return array<string, int> contagens por lote */
    public function __invoke(Staff $de, Staff $para, bool $carteira = false, bool $simular = false): array
    {
        if ($de->id === $para->id) {
            throw new Recusa('Origem e destino são a mesma conta.');
        }

        $lotes = [
            'placas vendidas' => ['vendedor_id', Etiqueta::where('vendedor_id', $de->id)],
            'placas em mãos' => ['consignada_para_id', Etiqueta::where('consignada_para_id', $de->id)],
            'placas geradas' => ['staff_id', Etiqueta::where('staff_id', $de->id)],
            'negócios' => ['vendedor_id', Negocio::where('vendedor_id', $de->id)],
        ];

        if ($carteira) {
            $lotes['clientes'] = ['vendedor_id', Cliente::withTrashed()->where('vendedor_id', $de->id)];
            $lotes['faturas'] = ['vendedor_id', Fatura::where('vendedor_id', $de->id)];
        }

        return DB::transaction(function () use ($lotes, $de, $para, $simular) {
            $contagens = [];

            foreach ($lotes as $rotulo => [$coluna, $consulta]) {
                $contagens[$rotulo] = (clone $consulta)->count();

                if (! $simular) {
                    $consulta->update([$coluna => $para->id]);
                }
            }

            if (! $simular) {
                Auditar::registrar('vendas.transferidas', $para, ['de' => $de->email, 'para' => $para->email] + $contagens);
            }

            return $contagens;
        });
    }
}
