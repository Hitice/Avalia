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
            'senha' => [$staff ? 'nullable' : 'required', 'string', 'min:8'],
            'papel' => ['required', Rule::in(['admin', 'vendedor'])],
            'comissao_pct' => ['required', 'integer', 'min:0', 'max:'.Comissao::PCT_MAXIMO],
            'ativo' => ['boolean'],
        ];
    }

    /** Dados prontos para gravar, sem a senha em branco da edicao. */
    public function dados(): array
    {
        $dados = $this->safe()->except('senha');

        if ($this->filled('senha')) {
            $dados['senha'] = $this->input('senha');
        }

        return $dados;
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
