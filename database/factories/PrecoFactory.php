<?php

namespace Database\Factories;

use App\Models\Catalogo;
use App\Models\Preco;
use App\Models\Servico;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Preco> */
class PrecoFactory extends Factory
{
    protected $model = Preco::class;

    public function definition(): array
    {
        return [
            'catalogo_id' => Catalogo::factory(),
            'servico_id' => Servico::factory(),
            'consumo_minimo_cents' => 0,
            'preco_cents' => 500,
            'custo_cents' => null,
        ];
    }

    public function naFaixa(int $centavos): static
    {
        return $this->state(['consumo_minimo_cents' => $centavos]);
    }
}
