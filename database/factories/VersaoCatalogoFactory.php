<?php

namespace Database\Factories;

use App\Models\Servico;
use App\Models\VersaoCatalogo;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<VersaoCatalogo> */
class VersaoCatalogoFactory extends Factory
{
    protected $model = VersaoCatalogo::class;

    public function definition(): array
    {
        return [
            'rotulo' => 'Catálogo '.fake()->unique()->numerify('##/20##'),
        ];
    }

    /**
     * Precifica um servico neste catalogo.
     *
     * As faixas sao as chaves do array, em centavos, para o teste dizer
     * "sem minimo custa R$ 5,00 e a faixa de R$ 900 custa R$ 3,00" sem
     * depender da ordem das colunas:
     *
     *     ->comServico('score', [0 => 500, 90_000 => 300])
     */
    public function comServico(string $codigo = 'consulta-teste', array $precosPorFaixa = [0 => 500]): static
    {
        return $this->afterCreating(function (VersaoCatalogo $catalogo) use ($codigo, $precosPorFaixa) {
            $servico = Servico::firstOrCreate(
                ['codigo' => $codigo],
                ['nome' => ucfirst(str_replace('-', ' ', $codigo)), 'categoria' => 'credito'],
            );

            foreach ($precosPorFaixa as $faixaCents => $precoCents) {
                $catalogo->precos()->create([
                    'servico_id' => $servico->id,
                    'consumo_minimo_cents' => $faixaCents,
                    'preco_cents' => $precoCents,
                ]);
            }
        });
    }
}
