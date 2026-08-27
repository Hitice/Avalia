<?php

namespace App\Http\Requests;

use App\Support\Documento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Cadastro de um lead a mao.
 *
 * A base vem dos PDFs da prospeccao, mas o vendedor descobre empresa em feira,
 * em indicacao e no telefone, e sem esta porta esse lead ficava num caderno.
 *
 * O CNPJ e opcional e nao passa por validacao de digito verificador: metade da
 * base chega sem documento, e exigir CNPJ correto de quem ainda nao e cliente
 * jogaria fora o lead que a Receita ainda vai confirmar. Ele e guardado
 * normalizado, do mesmo jeito que na tabela de clientes, para dar para comparar
 * as duas.
 */
class LeadRequest extends FormRequest
{
    /** A rota ja passa por auth:staff + admin. */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nome' => trim((string) $this->input('nome')),
            'codigo' => trim((string) $this->input('codigo')) ?: null,
            'cnpj' => Documento::normalizarCnpj($this->input('cnpj')) ?: null,
            'cidade' => trim((string) $this->input('cidade')) ?: null,
            'uf' => mb_strtoupper(trim((string) $this->input('uf'))) ?: null,
            'telefone' => trim((string) $this->input('telefone')) ?: null,
            'email' => mb_strtolower(trim((string) $this->input('email'))) ?: null,
            'origem' => trim((string) $this->input('origem')) ?: null,
            'observacao' => trim((string) $this->input('observacao')) ?: null,
            'ativo' => $this->boolean('ativo'),
        ]);
    }

    public function rules(): array
    {
        $lead = $this->route('lead');

        return [
            'nome' => ['required', 'string', 'max:160'],
            'codigo' => ['nullable', 'string', 'max:20', Rule::unique('leads', 'codigo')->ignore($lead)],
            'cnpj' => ['nullable', 'string', 'max:20'],
            'cidade' => ['nullable', 'string', 'max:80'],
            'uf' => ['nullable', 'string', 'size:2'],
            'telefone' => ['nullable', 'string', 'max:60'],
            'email' => ['nullable', 'email', 'max:160'],
            'origem' => ['nullable', 'string', 'max:40'],
            'observacao' => ['nullable', 'string', 'max:2000'],
            'ativo' => ['boolean'],
        ];
    }

    /** @return array<string, mixed> */
    public function dados(): array
    {
        return $this->safe()->all();
    }

    public function attributes(): array
    {
        return ['codigo' => 'código da base', 'uf' => 'UF'];
    }
}
