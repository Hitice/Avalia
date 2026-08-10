<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Parâmetro financeiro editável sem reprecificar a tabela manual.
 *
 * São três, e os três mexem no catálogo inteiro: o imposto, a margem alvo da
 * primeira faixa e o degrau que cada faixa seguinte cede. Alterar qualquer um
 * muda o preço que a tabela deveria ter, mas nunca muda preço sozinho: o
 * reajuste é outro botão, e é decisão comercial.
 */
class ParametrosCatalogoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'imposto' => ['required', 'numeric', 'min:0', 'max:99.99'],
            'margem_alvo' => ['required', 'numeric', 'min:0', 'max:99.99'],
            // O degrau tem teto proprio: um degrau grande zera a margem alvo
            // nas faixas fundas antes de chegar na ultima, e alvo zerado e a
            // casa vendendo no piso sem ter decidido isso.
            'degrau_margem' => ['required', 'numeric', 'min:0', 'max:20'],
        ];
    }

    public function bps(string $campo): int
    {
        return (int) round((float) $this->input($campo) * 100);
    }
}
