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
            // Com mascara, "38400-192" tem nove caracteres e falhava no
            // tamanho sem dizer por que. Guarda-se so o digito, como no lead.
            'cep' => preg_replace('/\D/', '', (string) $this->input('cep')) ?: null,
            'responsavel_cpf' => preg_replace('/\D/', '', (string) $this->input('responsavel_cpf')) ?: null,
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
            'situacao' => ['required', Rule::in(['ativo', 'inadimplente', 'bloqueado', 'inativo'])],
            'plano_id' => ['nullable', 'exists:planos,id'],
            'vendedor_id' => ['nullable', 'exists:staff,id'],
            'telefone' => ['nullable', 'string', 'max:20'],
            'responsavel_nome' => ['nullable', 'string', 'max:150'],
            'responsavel_cpf' => ['nullable', 'string', 'size:11'],
            'cep' => ['nullable', 'string', 'size:8'],
            'logradouro' => ['nullable', 'string', 'max:150'],
            'numero' => ['nullable', 'string', 'max:20'],
            'complemento' => ['nullable', 'string', 'max:100'],
            'bairro' => ['nullable', 'string', 'max:100'],
            'cidade' => ['nullable', 'string', 'max:100'],
            'uf' => ['nullable', 'string', 'size:2'],
            'vigencia_tipo' => ['nullable', Rule::in(['sem_vigencia', '12_meses', '24_meses', 'carencia'])],
            'contrato_inicio' => ['nullable', 'date'],
            'contrato_fim' => ['nullable', 'date', 'after_or_equal:contrato_inicio'],
            'carencia_ate' => ['nullable', 'date', 'after_or_equal:contrato_inicio'],
            'adesao_valor' => ['nullable', 'string', 'max:30'],
            'adesao_parcelas' => ['nullable', 'integer', 'min:1', 'max:120'],
        ];
    }

    /**
     * As datas decorrem do tipo de vigencia, e a tela nao pede o que da para
     * calcular: 12 e 24 meses derivam o fim do inicio; carencia usa a data
     * propria; sem vigencia nao guarda data nenhuma. Era ambiguidade na tela
     * (fim digitavel ao lado de um prazo fechado) e virava dado divergente.
     *
     * @param  array<string, mixed>  $dados
     * @return array<string, mixed>
     */
    private function comVigenciaResolvida(array $dados): array
    {
        $tipo = $dados['vigencia_tipo'] ?? null;
        $inicio = $dados['contrato_inicio'] ?? null;

        $meses = ['12_meses' => 12, '24_meses' => 24][$tipo] ?? null;

        if ($meses !== null) {
            $dados['contrato_fim'] = $inicio
                ? \Illuminate\Support\Carbon::parse($inicio)->addMonths($meses)->toDateString()
                : null;
            $dados['carencia_ate'] = null;
        } elseif ($tipo === 'carencia') {
            $dados['contrato_fim'] = null;
        } elseif ($tipo === 'sem_vigencia' || $tipo === null) {
            $dados['contrato_fim'] = null;
            $dados['carencia_ate'] = null;
        }

        return $dados;
    }

    /** Dados prontos para gravar. Senha nao passa por aqui: e do convite. */
    public function dados(): array
    {
        return $this->comVigenciaResolvida(
            $this->safe()->except(['senha', 'adesao_valor', 'adesao_parcelas'])
        );
    }

    public function messages(): array
    {
        return [
            'responsavel_cpf.size' => 'O CPF do responsável precisa ter 11 dígitos.',
            'cep.size' => 'O CEP precisa ter 8 dígitos.',
        ];
    }

    public function attributes(): array
    {
        return [
            'razao_social' => 'razão social',
            'responsavel_cpf' => 'CPF do responsável',
            'plano_id' => 'plano',
            'vendedor_id' => 'vendedor',
        ];
    }
}
