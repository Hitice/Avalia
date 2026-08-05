<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Quantidade incluida por servico: id do servico => quantidade. */
class FranquiaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'franquias' => ['array'],
            'franquias.*' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ];
    }

    /** @return array<int|string, int|null> */
    public function quantidades(): array
    {
        return $this->input('franquias', []);
    }
}
