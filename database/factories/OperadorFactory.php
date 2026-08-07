<?php

namespace Database\Factories;

use App\Models\Cliente;
use App\Models\Operador;
use Illuminate\Database\Eloquent\Factories\Factory;

class OperadorFactory extends Factory
{
    protected $model = Operador::class;

    public function definition(): array
    {
        return [
            'cliente_id' => Cliente::factory(),
            'nome' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'senha' => 'senha-valida-123',
            'ativo' => true,
        ];
    }

    public function inativo(): static
    {
        return $this->state(['ativo' => false]);
    }
}
