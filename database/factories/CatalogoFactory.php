<?php

namespace Database\Factories;

use App\Models\Catalogo;
use App\Models\Servico;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Catalogo> */
class CatalogoFactory extends Factory
{
    protected $model = Catalogo::class;

    public function definition(): array
    {
        return [
            'rotulo' => 'Catálogo '.fake()->unique()->numerify('##/20##'),
            // Espelha o que o CatalogoSeeder grava: sem isto o model
            // recem-criado fica com a coluna nula em memoria ate um fresh().
            'imposto_bps' => 860,
            'margem_alvo_bps' => 3_000,
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
        return $this->afterCreating(function (Catalogo $catalogo) use ($codigo, $precosPorFaixa) {
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
