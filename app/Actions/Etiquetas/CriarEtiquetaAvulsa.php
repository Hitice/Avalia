<?php

namespace App\Actions\Etiquetas;

use App\Enums\SituacaoEtiqueta;
use App\Models\Etiqueta;
use App\Support\Auditar;
use App\Support\CodigoCurto;

/**
 * Um codigo so, sem tiragem.
 *
 * E o QR dinamico vendido sem placa: nao ha acrilico para cortar, nao ha
 * numero de sequencia e nao ha pacote para baixar. Mora fora do lote de
 * proposito, porque tiragem de uma plaquinha so seria uma tiragem vazia de
 * sentido na lista de producao.
 *
 * O sorteio repete ate achar codigo inedito. Sao 1,07 bilhao de combinacoes,
 * entao a primeira tentativa quase sempre basta; quem garante mesmo e o indice
 * unico do banco.
 */
class CriarEtiquetaAvulsa
{
    private const TENTATIVAS = 20;

    /** @param  array{titulo: ?string, tipo: string}  $dados */
    public function __invoke(array $dados): Etiqueta
    {
        $etiqueta = Etiqueta::create([
            'codigo' => $this->codigoInedito(),
            'tipo' => $dados['tipo'],
            'titulo' => $dados['titulo'] ?? null,
            'situacao' => SituacaoEtiqueta::EmBranco,
            'staff_id' => auth('staff')->id(),
        ]);

        Auditar::registrar('etiquetas.avulsa.criada', $etiqueta);

        return $etiqueta;
    }

    private function codigoInedito(): string
    {
        for ($tentativa = 0; $tentativa < self::TENTATIVAS; $tentativa++) {
            $codigo = CodigoCurto::sortear();

            if (! Etiqueta::where('codigo', $codigo)->exists()) {
                return $codigo;
            }
        }

        throw new \RuntimeException('Não foi possível sortear um código inédito.');
    }
}
