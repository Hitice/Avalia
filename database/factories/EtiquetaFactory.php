<?php

namespace Database\Factories;

use App\Enums\SituacaoEtiqueta;
use App\Models\Etiqueta;
use App\Support\CodigoCurto;
use Illuminate\Database\Eloquent\Factories\Factory;

class EtiquetaFactory extends Factory
{
    protected $model = Etiqueta::class;

    public function definition(): array
    {
        // Como a plaquinha sai da oficina: com codigo, sem destino e sem dono.
        return [
            'codigo' => CodigoCurto::sortear(),
            'tipo' => 'qr',
            'situacao' => SituacaoEtiqueta::EmBranco,
        ];
    }

    /** Vendida hoje, apontando para algum lugar, com um ano pela frente. */
    public function ativa(?string $destino = null): static
    {
        return $this->state([
            'situacao' => SituacaoEtiqueta::Ativa,
            'destino' => $destino ?? 'https://exemplo.com.br',
            'cliente_nome' => fake()->company(),
            'vendida_em' => now(),
            'vence_em' => now()->addMonths((int) config('etiquetas.validade_meses')),
            'valor_cents' => (int) config('etiquetas.precos.placa_cents'),
        ]);
    }

    /** Ativa, mas com o vencimento no passado: `$dias` atras. */
    public function vencidaHa(int $dias): static
    {
        return $this->ativa()->state(['vence_em' => now()->subDays($dias)]);
    }

    public function suspensa(): static
    {
        return $this->ativa()->state(['situacao' => SituacaoEtiqueta::Suspensa]);
    }

    public function baixada(): static
    {
        return $this->ativa()->state(['situacao' => SituacaoEtiqueta::Baixada]);
    }
}
