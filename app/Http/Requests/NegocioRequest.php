<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A ficha do negocio, preenchida pelo proprio cliente ou pela casa.
 *
 * Exige pouco de proposito: nome, responsavel, e-mail e WhatsApp. O resto o
 * cliente quase sempre nao tem na ponta da lingua, e formulario que recusa a
 * primeira tentativa e formulario que ele nao termina. O que falta aparece
 * depois, em `Negocio::faltaPara()`, para a casa cobrar o dado certo em vez de
 * fazer o cliente adivinhar.
 *
 * O e-mail e obrigatorio porque e nele que o Google manda o convite de
 * propriedade do perfil: sem ele nao existe cadastro para fazer.
 */
class NegocioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:150'],
            'responsavel' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150'],
            'whatsapp' => ['required', 'string', 'max:20'],

            'categoria' => ['nullable', 'string', 'max:120'],
            'descricao' => ['nullable', 'string', 'max:1500'],
            'site' => ['nullable', 'string', 'max:255'],
            'instagram' => ['nullable', 'string', 'max:120'],
            'telefone' => ['nullable', 'string', 'max:20'],
            'documento' => ['nullable', 'string', 'max:20'],

            'atende_no_endereco' => ['nullable', 'boolean'],
            'cep' => ['nullable', 'string', 'max:9'],
            'logradouro' => ['nullable', 'string', 'max:180'],
            'numero' => ['nullable', 'string', 'max:20'],
            'complemento' => ['nullable', 'string', 'max:120'],
            'bairro' => ['nullable', 'string', 'max:120'],
            'cidade' => ['nullable', 'string', 'max:120'],
            'uf' => ['nullable', 'string', 'size:2'],
            'horarios' => ['nullable', 'string', 'max:600'],

            // Armadilha, como a do formulario de contato do site: fica escondida,
            // entao gente nao preenche e robo preenche. Nome diferente de `site`
            // porque aqui `site` e campo de verdade.
            'assunto' => ['prohibited'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'nome.required' => 'Diga o nome do negócio, como ele aparece na fachada.',
            'responsavel.required' => 'Diga quem responde pelo negócio.',
            'email.required' => 'O e-mail é por onde o Google envia o acesso ao perfil.',
            'email.email' => 'Confira o e-mail: ele é por onde o Google envia o acesso.',
            'whatsapp.required' => 'Deixe um WhatsApp para a gente falar com você.',
            'assunto.prohibited' => 'Não foi possível enviar. Tente de novo.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $so = fn (?string $v) => $v === null ? null : preg_replace('/\D/', '', $v);

        $this->merge([
            'nome' => trim((string) $this->input('nome')),
            'responsavel' => trim((string) $this->input('responsavel')),
            'email' => mb_strtolower(trim((string) $this->input('email'))),
            'whatsapp' => $so($this->input('whatsapp')),
            'telefone' => $so($this->input('telefone')) ?: null,
            'documento' => $so($this->input('documento')) ?: null,
            'cep' => $so($this->input('cep')) ?: null,
            'uf' => mb_strtoupper(trim((string) $this->input('uf'))) ?: null,

            // O checkbox nao chega quando desmarcado, e `nullable boolean` leria
            // isso como "nao informado". Aqui ausencia e "atende no cliente".
            'atende_no_endereco' => $this->boolean('atende_no_endereco'),
        ]);
    }
}
