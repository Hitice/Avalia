<?php

namespace App\Http\Requests;

use App\Models\VersaoCatalogo;
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
            'versao_id' => ['required', Rule::exists('versoes_catalogo', 'id')],
            'mensalidade_cents' => ['required', 'integer', 'min:0'],
            'consumo_minimo_cents' => ['required', 'integer', 'min:0'],
            'ativo' => ['boolean'],
        ];
    }

    /**
     * O consumo minimo tem que ser uma das faixas da versao escolhida.
     *
     * Sem isso da para salvar um plano de R$ 300 numa versao com faixas de
     * 75/200/500: nenhuma consulta acharia coluna de preco, e o erro so
     * apareceria na hora de faturar.
     */
    public function withValidator(Validator $validador): void
    {
        $validador->after(function (Validator $validador) {
            $versao = VersaoCatalogo::find($this->input('versao_id'));

            if (! $versao) {
                return;
            }

            $faixas = $versao->faixas();

            if ($faixas === []) {
                $validador->errors()->add('versao_id', 'Esta versao ainda nao tem preco nenhum cadastrado.');

                return;
            }

            if (! in_array($this->input('consumo_minimo_cents'), $faixas, true)) {
                $validador->errors()->add('consumo_minimo', sprintf(
                    'Faixa inexistente nesta versao. Disponiveis: %s.',
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
            'versao_id' => 'versao do catalogo',
            'mensalidade_cents' => 'mensalidade',
            'consumo_minimo_cents' => 'consumo minimo',
        ];
    }
}
