<?php

namespace Database\Factories;

use App\Models\Cliente;
use App\Models\Consulta;
use App\Models\Servico;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Consultas para teste, ja com preco e custo congelados.
 *
 * A acao de consumo e o caminho de verdade e continua sendo usada onde o que
 * esta em jogo e a regra. Esta fabrica serve para montar cenario de leitura:
 * lista, filtro e relatorio, onde importa a linha existir com uma situacao e
 * uma data, e nao como ela nasceu.
 *
 * A competencia acompanha `created_at`, porque e assim que o fechamento agrupa:
 * fabricar consulta antiga com competencia do mes corrente criaria um cenario
 * que a aplicacao nunca produz.
 */
class ConsultaFactory extends Factory
{
    protected $model = Consulta::class;

    public function definition(): array
    {
        $quando = now();

        return [
            'cliente_id' => Cliente::factory(),
            'servico_id' => Servico::factory(),
            'competencia' => Consulta::competenciaDe($quando),
            'preco_cents' => 324,
            'custo_cents' => 150,
            'documento' => (string) $this->faker->numerify('###########'),
            'finalidade' => 'Análise de crédito para venda a prazo',
            'situacao' => Consulta::SUCESSO,
            'duracao_ms' => $this->faker->numberBetween(80, 900),
            'created_at' => $quando,
            'updated_at' => $quando,
        ];
    }

    /** Tentativa que nao completou: fica registrada e nao gera cobranca. */
    public function falha(): static
    {
        return $this->state(fn () => [
            'situacao' => Consulta::FALHA,
            'preco_cents' => 0,
            'custo_cents' => 0,
        ]);
    }

    /** Consulta de uma data especifica, com a competencia correspondente. */
    public function em(\DateTimeInterface $quando): static
    {
        return $this->state(fn () => [
            'competencia' => Consulta::competenciaDe($quando),
            'created_at' => $quando,
            'updated_at' => $quando,
        ]);
    }
}
