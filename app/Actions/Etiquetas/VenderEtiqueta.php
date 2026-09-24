<?php

namespace App\Actions\Etiquetas;

use App\Enums\SituacaoEtiqueta;
use App\Exceptions\Recusa;
use App\Models\Etiqueta;
use App\Support\Auditar;
use Illuminate\Support\Facades\DB;

/**
 * Poe a plaquinha em campo: dono, destino e prazo.
 *
 * O valor fica GRAVADO na etiqueta, e nao lido da tabela de precos na hora de
 * mostrar. A tabela muda; o que foi cobrado nao. E a mesma regra da consulta e
 * da fatura da casa.
 *
 * O prazo sai de config, e nao do formulario: venda com validade digitada a
 * mao e venda que um dia sai com dois anos por engano, e a plaquinha fica dois
 * anos de pe sem ninguem cobrar.
 */
class VenderEtiqueta
{
    public function __construct(private readonly ApontarEtiqueta $apontar) {}

    /**
     * @param  array{destino: string, cliente_nome: ?string, cliente_contato: ?string, titulo: ?string, valor_cents: ?int}  $dados
     */
    public function __invoke(Etiqueta $etiqueta, array $dados): Etiqueta
    {
        if ($etiqueta->situacao === SituacaoEtiqueta::Baixada) {
            throw new Recusa('Esta etiqueta foi encerrada: o código fica reservado para sempre e não volta a circular.');
        }

        ($this->apontar)($etiqueta, $dados['destino']);

        return DB::transaction(function () use ($etiqueta, $dados) {
            $primeiraVenda = $etiqueta->vendida_em === null;

            $etiqueta->update([
                'situacao' => SituacaoEtiqueta::Ativa,
                'titulo' => $dados['titulo'] ?? $etiqueta->titulo,
                'cliente_nome' => $dados['cliente_nome'] ?? $etiqueta->cliente_nome,
                'cliente_contato' => $dados['cliente_contato'] ?? $etiqueta->cliente_contato,
            ] + ($primeiraVenda ? [
                'vendida_em' => now(),
                'vence_em' => now()->addMonths((int) config('etiquetas.validade_meses')),
                'valor_cents' => $dados['valor_cents'] ?? (int) config('etiquetas.precos.placa_cents'),
            ] : []));

            if ($primeiraVenda) {
                Auditar::registrar('etiquetas.vendida', $etiqueta, [
                    'cliente' => $etiqueta->cliente_nome,
                    'vence_em' => $etiqueta->vence_em?->toDateString(),
                ]);
            }

            return $etiqueta->refresh();
        });
    }
}
