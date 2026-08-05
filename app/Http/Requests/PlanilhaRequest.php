<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Planilha enviada para importacao: o xlsx exportado ou um csv. */
class PlanilhaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'planilha' => ['required', 'file', 'mimes:xlsx,csv,txt', 'max:4096'],
        ];
    }

    public function caminho(): string
    {
        return $this->file('planilha')->getRealPath();
    }
}
