<?php

namespace App\Http\Requests;

use App\Support\Comissao;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Cadastro de quem trabalha na Avalia.
 *
 * O percentual de comissao tem teto: a administracao negocia caso a caso, mas
 * comissao acima do teto comeria a operacao inteira, e um zero a mais digitado
 * por engano viraria repasse no fechamento seguinte.
 */
class StaffRequest extends FormRequest
{
    /** A rota ja passa por auth:staff + admin. */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => mb_strtolower(trim((string) $this->input('email'))),
            'ativo' => $this->boolean('ativo'),
            'pode_financeiro' => $this->boolean('pode_financeiro'),
        ]);
    }

    public function rules(): array
    {
        $staff = $this->route('membro');

        return [
            'nome' => ['required', 'string', 'max:150'],
            'email' => [
                'required', 'email', 'max:150',
                Rule::unique('staff', 'email')->ignore($staff)->whereNull('deleted_at'),
            ],
            'papel' => ['required', Rule::in(['admin', 'vendedor'])],
            'comissao_pct' => ['required', 'integer', 'min:0', 'max:'.Comissao::PCT_MAXIMO],
            'ativo' => ['boolean'],
            'pode_financeiro' => ['boolean'],
        ];
    }

    /** Dados prontos para gravar. Senha nao passa por aqui: e do convite. */
    public function dados(): array
    {
        return $this->safe()->except('senha');
    }

    public function messages(): array
    {
        return [
            'comissao_pct.max' => 'A comissão não pode passar de :max%.',
        ];
    }

    public function attributes(): array
    {
        return ['comissao_pct' => 'comissão'];
    }
}
