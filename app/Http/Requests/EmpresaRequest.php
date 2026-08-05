<?php

namespace App\Http\Requests;

use App\Support\Documento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Cadastro da empresa contratante.
 *
 * O CNPJ e normalizado antes de validar, entao "12.345.678/0001-95" e
 * "12345678000195" sao o mesmo documento e nao entram duas vezes.
 */
class EmpresaRequest extends FormRequest
{
    /** A rota ja passa por auth:staff + admin. */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'cnpj' => Documento::normalizarCnpj($this->input('cnpj')) ?: null,
            'email' => mb_strtolower(trim((string) $this->input('email'))),
        ]);
    }

    public function rules(): array
    {
        $empresa = $this->route('empresa');

        return [
            'razao_social' => ['required', 'string', 'max:150'],
            'cnpj' => [
                'required', 'string', 'size:14',
                // Soft delete guardado: empresa encerrada nao pode bloquear o
                // recadastro da mesma empresa se ela voltar.
                Rule::unique('clientes', 'cnpj')->ignore($empresa)->whereNull('deleted_at'),
                fn ($atributo, $valor, $falhou) => Documento::cnpjValido($valor)
                    ? null
                    : $falhou('CNPJ invalido: confira os digitos.'),
            ],
            'email' => [
                'required', 'email', 'max:150',
                Rule::unique('clientes', 'email')->ignore($empresa)->whereNull('deleted_at'),
            ],
            'senha' => [$empresa ? 'nullable' : 'required', 'string', 'min:8'],
            'situacao' => ['required', Rule::in(['ativo', 'inadimplente', 'bloqueado', 'inativo'])],
            'plano_id' => ['nullable', 'exists:planos,id'],
            'vendedor_id' => ['nullable', 'exists:staff,id'],
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

    public function attributes(): array
    {
        return [
            'razao_social' => 'razão social',
            'plano_id' => 'plano',
            'vendedor_id' => 'vendedor',
        ];
    }
}
