<?php

namespace Database\Factories;

use App\Models\Servico;
use App\Models\VersaoCatalogo;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<VersaoCatalogo> */
class VersaoCatalogoFactory extends Factory
{
    protected $model = VersaoCatalogo::class;

    public function definition(): array
    {
        return [
            'rotulo' => 'Catálogo '.fake()->unique()->numerify('##/20##'),
            'situacao' => 'rascunho',
        ];
    }

    /**
     * Precifica um servico nesta versao.
     *
     * As faixas sao as chaves do array, em centavos, para o teste dizer
     * "sem minimo custa R$ 5,00 e a faixa de R$ 900 custa R$ 3,00" sem
     * depender da ordem das colunas:
     *
     *     ->comServico('score', [0 => 500, 90_000 => 300])
     */
    public function comServico(string $codigo = 'consulta-teste', array $precosPorFaixa = [0 => 500]): static
    {
        return $this->afterCreating(function (VersaoCatalogo $versao) use ($codigo, $precosPorFaixa) {
            $servico = Servico::firstOrCreate(
                ['codigo' => $codigo],
                ['nome' => ucfirst(str_replace('-', ' ', $codigo)), 'categoria' => 'credito'],
            );

            foreach ($precosPorFaixa as $faixaCents => $precoCents) {
                $preco = $versao->precos()->make([
                    'servico_id' => $servico->id,
                    'consumo_minimo_cents' => $faixaCents,
                    'preco_cents' => $precoCents,
                ]);

                // A versao acabou de ser criada como rascunho; dispensa a
                // consulta que a guarda de congelamento faria.
                $preco->setRelation('versao', $versao);
                $preco->save();
            }
        });
    }

    /** Versao ja em vigor. Precos so podem ser definidos antes disso. */
    public function ativa(): static
    {
        return $this->afterCreating(fn (VersaoCatalogo $versao) => $versao->ativar());
    }

    public function encerrada(): static
    {
        return $this->state(['situacao' => 'encerrada']);
    }
}
