<?php

namespace Database\Factories;

use App\Models\Plano;
use App\Models\VersaoCatalogo;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Plano> */
class PlanoFactory extends Factory
{
    protected $model = Plano::class;

    public function definition(): array
    {
        return [
            'versao_id' => VersaoCatalogo::factory(),
            'nome' => 'Plano '.fake()->unique()->numerify('###'),
            'descricao' => null,
            // Mensalidade da Avalia (PDD.md, secao 5) e a menor faixa com
            // consumo minimo: R$ 75,00.
            'mensalidade_cents' => 7_990,
            'consumo_minimo_cents' => 7_500,
            'ativo' => true,
        ];
    }

    /** Consumo minimo em reais, para o teste nao contar zeros. */
    public function consumoMinimo(int $reais): static
    {
        return $this->state(['consumo_minimo_cents' => $reais * 100]);
    }

    public function semMinimo(): static
    {
        return $this->state(['consumo_minimo_cents' => 0]);
    }

    public function inativo(): static
    {
        return $this->state(['ativo' => false]);
    }
}
