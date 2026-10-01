<?php

namespace Database\Factories;

use App\Enums\SituacaoNegocio;
use Illuminate\Database\Eloquent\Factories\Factory;

class NegocioFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nome' => 'Pizzaria do Centro',
            'categoria' => 'Pizzaria',
            'responsavel' => 'Marcos Lima',
            'email' => 'contato@pizzariadocentro.com.br',
            'whatsapp' => '34999990000',
            'telefone' => '3433330000',
            'atende_no_endereco' => true,
            'cep' => '38400000',
            'logradouro' => 'Rua das Flores',
            'numero' => '120',
            'bairro' => 'Centro',
            'cidade' => 'Uberlândia',
            'uf' => 'MG',
            'horarios' => 'Seg a sab 18h as 23h',
            'situacao' => SituacaoNegocio::Recebido->value,
            'origem' => 'warley',
        ];
    }

    /** Sem o que o Google exige, para cobrar `faltaPara()`. */
    public function incompleto(): static
    {
        return $this->state([
            'categoria' => null,
            'horarios' => null,
            'telefone' => null,
            'site' => null,
        ]);
    }
}
