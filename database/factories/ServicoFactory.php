<?php

namespace Database\Factories;

use App\Models\Servico;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Servico> */
class ServicoFactory extends Factory
{
    protected $model = Servico::class;

    public function definition(): array
    {
        $codigo = fake()->unique()->slug(2);

        return [
            'codigo' => $codigo,
            'nome' => ucfirst(str_replace('-', ' ', $codigo)),
            'categoria' => 'credito',
            'ativo' => true,
            'exige_liberacao' => false,
        ];
    }

    public function veicular(): static
    {
        return $this->state(['categoria' => 'veicular']);
    }

    /** SCR e afins: existe no catalogo, mas nao pode ser consultado ainda. */
    public function aguardandoLiberacao(): static
    {
        return $this->state(['exige_liberacao' => true]);
    }

    public function inativo(): static
    {
        return $this->state(['ativo' => false]);
    }
}
