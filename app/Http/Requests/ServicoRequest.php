<?php

namespace App\Http\Requests;

use App\Enums\Categoria;
use App\Models\Servico;
use App\Support\Dinheiro;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Cadastro e edicao de servico do catalogo.
 *
 * O codigo so entra na criacao. Depois disso ele e imutavel: e a chave que
 * integracao, relatorio e franquia de plano usam para achar o servico. Nome
 * comercial muda com marketing; codigo nao muda nunca.
 */
class ServicoRequest extends FormRequest
{
    /** A rota ja passa por auth:staff + admin. */
    public function authorize(): bool
    {
        return true;
    }

    private function ehCriacao(): bool
    {
        return $this->route('servico') === null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'ativo' => $this->boolean('ativo'),
            'exige_liberacao' => $this->boolean('exige_liberacao'),
        ]);

        if ($this->ehCriacao()) {
            $this->merge([
                'codigo' => Str::slug((string) $this->input('codigo')),
                'preco_base_cents' => Dinheiro::paraCentavos($this->input('preco_base')),
            ]);
        }
    }

    public function rules(): array
    {
        $regras = [
            'nome' => ['required', 'string', 'max:150'],
            'categoria' => ['required', Rule::in(Categoria::valores())],
            'ativo' => ['boolean'],
            'exige_liberacao' => ['boolean'],
        ];

        if ($this->ehCriacao()) {
            $regras['codigo'] = [
                'required', 'string', 'max:60',
                'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/',
                Rule::unique('servicos', 'codigo'),
            ];

            // Sem preco o servico nasceria invisivel: a matriz do catalogo so
            // mostra servico que tem preco em alguma faixa.
            $regras['preco_base_cents'] = ['required', 'integer', 'min:0'];
        }

        return $regras;
    }

    public function messages(): array
    {
        return [
            'codigo.regex' => 'O código aceita apenas letras minúsculas, números e hífen.',
        ];
    }

    public function attributes(): array
    {
        return [
            'preco_base_cents' => 'preço inicial',
            'exige_liberacao' => 'liberação pendente',
        ];
    }
}
