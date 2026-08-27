<?php

namespace Database\Factories;

use App\Enums\SituacaoLead;
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
            'situacao' => SituacaoLead::Novo,
        ];
    }

    /** Lead que chegou sem telefone e sem e-mail: precisa de enriquecimento. */
    public function semContato(): static
    {
        return $this->state(['telefone' => null, 'email' => null]);
    }

    /** Lead com reuniao marcada, que e o unico estagio com data. */
    public function agendado(?string $quando = null): static
    {
        return $this->state([
            'situacao' => SituacaoLead::Agendado,
            'agendado_para' => $quando ?? now()->addDays(2),
        ]);
    }

    /** Ficha completa: o lead que ja da para converter em cliente. */
    public function pronto(): static
    {
        return $this->state([
            'cnpj' => '08876860000103',
            'email' => 'contato@exemplo.com.br',
            'responsavel_nome' => 'Quem Decide',
        ]);
    }
}
