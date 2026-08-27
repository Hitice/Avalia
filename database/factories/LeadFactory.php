<?php

namespace Database\Factories;

use App\Models\Lead;
use Illuminate\Database\Eloquent\Factories\Factory;

class LeadFactory extends Factory
{
    protected $model = Lead::class;

    public function definition(): array
    {
        return [
            'codigo' => (string) fake()->unique()->numberBetween(10_000, 99_999),
            'nome' => mb_strtoupper(fake()->company()),
            'cnpj' => (string) fake()->unique()->numerify('##############'),
            'cidade' => fake()->city(),
            'uf' => fake()->randomElement(['MG', 'SP', 'GO', 'PE', 'DF']),
            'telefone' => fake()->numerify('(31) ####-####'),
            'email' => fake()->unique()->safeEmail(),
            'origem' => fake()->numberBetween(1, 38).'.pdf',
            'ativo' => true,
        ];
    }

    /** Lead que chegou sem telefone e sem e-mail: precisa de enriquecimento. */
    public function semContato(): static
    {
        return $this->state(['telefone' => null, 'email' => null]);
    }

    public function inativo(): static
    {
        return $this->state(['ativo' => false]);
    }
}
