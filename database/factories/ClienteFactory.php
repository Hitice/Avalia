<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Cliente> */
class ClienteFactory extends Factory
{
    protected $model = \App\Models\Cliente::class;

    public function definition(): array
    {
        return [
            'razao_social' => fake()->company().' LTDA',
            'email' => fake()->unique()->companyEmail(),
            'senha' => 'senha-valida-123',
            'situacao' => 'ativo',
            'sessao_versao' => 1,
        ];
    }

    public function inadimplente(): static
    {
        return $this->state(['situacao' => 'inadimplente']);
    }

    public function bloqueado(): static
    {
        return $this->state(['situacao' => 'bloqueado']);
    }

    public function inativo(): static
    {
        return $this->state(['situacao' => 'inativo']);
    }
}
