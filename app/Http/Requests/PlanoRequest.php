<?php

namespace App\Http\Requests;

use App\Models\Catalogo;
use App\Support\Dinheiro;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validacao do cadastro de plano.
 *
 * O formulario recebe dinheiro como o operador digita ("1.234,56") e guarda
 * centavos. A conversao acontece antes da validacao para que as regras
 * numericas trabalhem sobre inteiro, e nao sobre texto formatado.
 */
class PlanoRequest extends FormRequest
{
    /** A rota ja passa por auth:staff + admin; nao ha regra extra por registro. */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'mensalidade_cents' => Dinheiro::paraCentavos($this->input('mensalidade')),
            'consumo_minimo_cents' => Dinheiro::paraCentavos($this->input('consumo_minimo')),
            'faixa_preco_cents' => Dinheiro::paraCentavos($this->input('faixa_preco')),
            'ativo' => $this->boolean('ativo'),
        ]);
    }

    public function rules(): array
    {
        return [
            'nome' => [
                'required', 'string', 'max:120',
                Rule::unique('planos', 'nome')->ignore($this->route('plano')),
            ],
            'descricao' => ['nullable', 'string', 'max:500'],
            'mensalidade_cents' => ['required', 'integer', 'min:0'],
            'consumo_minimo_cents' => ['required', 'integer', 'min:0'],
            'faixa_preco_cents' => ['nullable', 'integer', 'min:0'],
            'ativo' => ['boolean'],
        ];
    }

    /**
     * A faixa de PRECO tem que ser uma das colunas do catalogo.
     *
     * E ela que toda consulta le. Sem esta trava daria para salvar um plano
     * lendo a coluna de R$ 300 num catalogo que so tem 75/200/500: nenhuma
     * consulta acharia preco, e o erro so apareceria na hora de faturar.
     *
     * O consumo minimo em si e livre (R$ 1.350 se o comercial quiser): ele e
     * piso de cobranca. Sem faixa negociada, a coluna lida e a do minimo, e
     * ai o minimo precisa coincidir com uma faixa.
     */
    public function withValidator(Validator $validador): void
    {
        $validador->after(function (Validator $validador) {
            $catalogo = Catalogo::vigente();

            $negociada = $this->input('faixa_preco_cents') !== null;
            $campo = $negociada ? 'faixa_preco' : 'consumo_minimo';
            $faixaDePreco = $this->input('faixa_preco_cents') ?? $this->input('consumo_minimo_cents');

            if (! $catalogo) {
                $validador->errors()->add($campo, 'Nao ha catalogo cadastrado.');

                return;
            }

            $faixas = $catalogo->faixas();

            if ($faixas === []) {
                $validador->errors()->add($campo, 'O catalogo ainda nao tem preco nenhum.');

                return;
            }

            if (! in_array($faixaDePreco, $faixas, true)) {
                $validador->errors()->add($campo, sprintf(
                    $negociada
                        ? 'Tabela de preços inexistente no catálogo. Disponíveis: %s.'
                        : 'O mínimo não coincide com uma faixa de preços. Escolha a tabela no campo ao lado, ou use um destes valores: %s.',
                    implode(', ', array_map(
                        fn (int $faixa) => $faixa === 0 ? 'sem minimo' : Dinheiro::brl($faixa),
                        $faixas,
                    )),
                ));
            }
        });
    }

    public function attributes(): array
    {
        return [
            'mensalidade_cents' => 'mensalidade',
            'consumo_minimo_cents' => 'consumo minimo',
            'faixa_preco_cents' => 'tabela de precos',
        ];
    }
}
