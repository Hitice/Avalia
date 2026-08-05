<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Precos de venda digitados na matriz: id do preco => valor. */
class PrecosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'precos' => ['array'],
            'precos.*' => ['nullable', 'string', 'max:20'],
        ];
    }

    /** @return array<int|string, string|null> */
    public function valores(): array
    {
        return $this->input('precos', []);
    }
}
