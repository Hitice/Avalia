<?php

namespace App\Http\Requests;

use App\Enums\Categoria;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Reajuste percentual, opcionalmente restrito a uma categoria. */
class ReajusteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // -90 a 900: abaixo disso o preco zera, acima e quase certo erro de
            // digitacao de quem quis escrever 9 e escreveu 900.
            'percentual' => ['required', 'numeric', 'between:-90,900'],
            'categoria' => ['nullable', Rule::in(Categoria::valores())],
        ];
    }

    public function percentual(): float
    {
        return (float) $this->input('percentual');
    }

    public function categoria(): ?Categoria
    {
        return Categoria::tentar($this->input('categoria'));
    }
}
