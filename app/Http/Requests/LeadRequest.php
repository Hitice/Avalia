<?php

namespace App\Http\Requests;

use App\Enums\SituacaoLead;
use App\Support\Documento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A ficha do lead, cadastrada ou corrigida a mao.
 *
 * A base vem dos PDFs da prospeccao, mas quem trabalha o lead e quem descobre o
 * que falta: o telefone que mudou, o nome de quem decide, o CNPJ que o site da
 * empresa mostrava. Sem esta porta esses dados ficavam num caderno, e a venda
 * fechada virava entrevista de cadastro com o cliente esperando na linha.
 *
 * Os campos sao os mesmos de `EmpresaRequest`, com uma diferenca deliberada: o
 * CNPJ e opcional e nao tem o digito verificador conferido. Metade da base chega
 * sem documento, e exigir CNPJ correto de quem ainda nao e cliente jogaria fora
 * justamente o lead que o vendedor vai qualificar. A conferencia acontece na
 * conversao, que e onde o documento passa a valer dinheiro.
 *
 * O que o vendedor NAO decide: o codigo e a origem, que sao a procedencia do
 * lead na base, e as situacoes "Nao atender" e "Virou cliente". A primeira e
 * decisao da casa; a segunda quem marca e a conversao, quando o cadastro do
 * cliente e gravado de verdade.
 */
class LeadRequest extends FormRequest
{
    /** A rota confere administracao ou vinculo do vendedor com o lead. */
    public function authorize(): bool
    {
        return true;
    }

    private function ehAdmin(): bool
    {
        $conta = $this->user('staff');

        return (bool) ($conta?->ehAdmin() || $conta?->ehSuper());
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
            'responsavel_nome' => trim((string) $this->input('responsavel_nome')) ?: null,
            'responsavel_cpf' => preg_replace('/\D/', '', (string) $this->input('responsavel_cpf')) ?: null,
            'cep' => preg_replace('/\D/', '', (string) $this->input('cep')) ?: null,
            'logradouro' => trim((string) $this->input('logradouro')) ?: null,
            'numero' => trim((string) $this->input('numero')) ?: null,
            'complemento' => trim((string) $this->input('complemento')) ?: null,
            'bairro' => trim((string) $this->input('bairro')) ?: null,
            'origem' => trim((string) $this->input('origem')) ?: null,
            'observacao' => trim((string) $this->input('observacao')) ?: null,
            'agendado_para' => trim((string) $this->input('agendado_para')) ?: null,
        ]);
    }

    public function rules(): array
    {
        $lead = $this->route('lead');

        $situacoes = $this->ehAdmin()
            ? SituacaoLead::valores()
            : array_keys(SituacaoLead::doVendedor());

        return [
            'nome' => ['required', 'string', 'max:160'],
            'codigo' => ['nullable', 'string', 'max:20', Rule::unique('leads', 'codigo')->ignore($lead)],
            'cnpj' => ['nullable', 'string', 'max:20'],
            'cidade' => ['nullable', 'string', 'max:80'],
            'uf' => ['nullable', 'string', 'size:2'],
            'telefone' => ['nullable', 'string', 'max:60'],
            'email' => ['nullable', 'email', 'max:160'],
            'responsavel_nome' => ['nullable', 'string', 'max:150'],
            'responsavel_cpf' => ['nullable', 'string', 'size:11'],
            'cep' => ['nullable', 'string', 'size:8'],
            'logradouro' => ['nullable', 'string', 'max:150'],
            'numero' => ['nullable', 'string', 'max:20'],
            'complemento' => ['nullable', 'string', 'max:100'],
            'bairro' => ['nullable', 'string', 'max:100'],
            'origem' => ['nullable', 'string', 'max:40'],
            'situacao' => ['required', Rule::in($situacoes)],

            // Agendado sem data nao diz nada a quem abre a tela amanha, e e
            // justamente essa pessoa que a coluna existe para servir.
            'agendado_para' => [
                Rule::requiredIf(fn () => $this->input('situacao') === SituacaoLead::Agendado->value),
                'nullable', 'date',
            ],

            'observacao' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Dados prontos para gravar, ja sem o que quem esta pedindo nao decide.
     *
     * Mesmo desenho de `EmpresaController::comOsCamposQuePode`: a tela do
     * vendedor nao mostra estes campos, e tirar aqui tambem fecha o caminho de
     * quem monta o POST a mao.
     *
     * @return array<string, mixed>
     */
    public function dados(): array
    {
        $dados = $this->safe()->all();

        if (! $this->ehAdmin()) {
            unset($dados['codigo'], $dados['origem']);
        }

        // Sair de "agendado" limpa a data: agendamento de reuniao que nao
        // aconteceu ficaria para sempre na fila de atrasados.
        if (($dados['situacao'] ?? null) !== SituacaoLead::Agendado->value) {
            $dados['agendado_para'] = null;
        }

        return $dados;
    }

    public function attributes(): array
    {
        return [
            'codigo' => 'código da base',
            'uf' => 'UF',
            'nome' => 'razão social',
            'agendado_para' => 'data do agendamento',
            'responsavel_cpf' => 'CPF do responsável',
        ];
    }

    public function messages(): array
    {
        return [
            'agendado_para.required' => 'Informe quando é o agendamento.',
            'responsavel_cpf.size' => 'O CPF do responsável precisa ter 11 dígitos.',
            'cep.size' => 'O CEP precisa ter 8 dígitos.',
        ];
    }
}
