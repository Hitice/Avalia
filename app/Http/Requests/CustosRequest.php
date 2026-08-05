<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Custo do fornecedor digitado na matriz: id do servico => valor. */
class CustosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'custos' => ['array'],
            'custos.*' => ['nullable', 'string', 'max:20'],
        ];
    }

    /** @return array<int|string, string|null> */
    public function valores(): array
    {
        return $this->input('custos', []);
    }
}
