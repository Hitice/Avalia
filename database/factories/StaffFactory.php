<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Staff> */
class StaffFactory extends Factory
{
    protected $model = \App\Models\Staff::class;

    public function definition(): array
    {
        return [
            'nome' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'senha' => 'senha-valida-123',
            'papel' => 'vendedor',
            'comissao_pct' => \App\Support\Comissao::PCT_PADRAO,
            'super' => false,
            'ativo' => true,
            'sessao_versao' => 1,
        ];
    }

    public function admin(): static
    {
        return $this->state(['papel' => 'admin']);
    }

    public function super(): static
    {
        return $this->state(['papel' => 'admin', 'super' => true]);
    }

    public function inativo(): static
    {
        return $this->state(['ativo' => false]);
    }
}
