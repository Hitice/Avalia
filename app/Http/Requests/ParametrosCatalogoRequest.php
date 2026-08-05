<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Parâmetro financeiro editável sem reprecificar a tabela manual.
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
        ];
    }

    public function bps(string $campo): int
    {
        return (int) round((float) $this->input($campo) * 100);
    }
}
