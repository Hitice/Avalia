<?php

/*
 * Mensagens de validacao em portugues.
 *
 * Sem este arquivo, locale pt_BR sem traducao mostrava a CHAVE crua na tela:
 * "validation.unique" no lugar de uma frase. O operador via jargao de
 * framework onde devia haver uma explicacao.
 *
 * As frases falam com quem preenche, nao com quem programou: dizem o que fazer,
 * nao qual regra falhou.
 */

return [
    'accepted' => 'O campo :attribute precisa ser aceito.',
    'active_url' => 'Informe um endereço válido em :attribute.',
    'after' => 'A data de :attribute precisa ser depois de :date.',
    'after_or_equal' => 'A data de :attribute não pode ser anterior a :date.',
    'alpha' => 'O campo :attribute aceita apenas letras.',
    'alpha_dash' => 'O campo :attribute aceita letras, números, traço e sublinhado.',
    'alpha_num' => 'O campo :attribute aceita apenas letras e números.',
    'array' => 'O campo :attribute precisa ser uma lista.',
    'before' => 'A data de :attribute precisa ser antes de :date.',
    'before_or_equal' => 'A data de :attribute não pode ser posterior a :date.',
    'between' => [
        'numeric' => 'O campo :attribute precisa ficar entre :min e :max.',
        'string' => 'O campo :attribute precisa ter entre :min e :max caracteres.',
    ],
    'boolean' => 'O campo :attribute precisa ser sim ou não.',
    'confirmed' => 'A confirmação de :attribute não confere.',
    'current_password' => 'A senha atual não confere.',
    'date' => 'Informe uma data válida em :attribute.',
    'date_equals' => 'A data de :attribute precisa ser :date.',
    'date_format' => 'A data de :attribute não está no formato esperado.',
    'decimal' => 'O campo :attribute precisa ter :decimal casas decimais.',
    'declined' => 'O campo :attribute precisa ser recusado.',
    'different' => 'Os campos :attribute e :other precisam ser diferentes.',
    'digits' => 'O campo :attribute precisa ter :digits dígitos.',
    'digits_between' => 'O campo :attribute precisa ter entre :min e :max dígitos.',
    'email' => 'Informe um e-mail válido em :attribute.',
    'ends_with' => 'O campo :attribute precisa terminar com: :values.',
    'exists' => 'O valor escolhido em :attribute não está disponível.',
    'filled' => 'O campo :attribute é obrigatório.',
    'gt' => 'O campo :attribute precisa ser maior que :value.',
    'gte' => 'O campo :attribute precisa ser no mínimo :value.',
    'in' => 'O valor escolhido em :attribute não é uma opção válida.',
    'integer' => 'O campo :attribute precisa ser um número inteiro.',
    'ip' => 'Informe um endereço IP válido em :attribute.',
    'json' => 'O campo :attribute precisa ser um texto JSON válido.',
    'lt' => 'O campo :attribute precisa ser menor que :value.',
    'lte' => 'O campo :attribute precisa ser no máximo :value.',
    'max' => [
        'numeric' => 'O campo :attribute não pode passar de :max.',
        'string' => 'O campo :attribute não pode passar de :max caracteres.',
        'array' => 'O campo :attribute não pode ter mais de :max itens.',
    ],
    'mimes' => 'O arquivo de :attribute precisa ser do tipo: :values.',
    'min' => [
        'numeric' => 'O campo :attribute precisa ser no mínimo :min.',
        'string' => 'O campo :attribute precisa ter pelo menos :min caracteres.',
        'array' => 'O campo :attribute precisa ter pelo menos :min itens.',
    ],
    'not_in' => 'O valor escolhido em :attribute não é uma opção válida.',
    'numeric' => 'O campo :attribute precisa ser um número.',
    'present' => 'O campo :attribute precisa estar presente.',
    'regex' => 'O formato de :attribute não é válido.',
    'required' => 'O campo :attribute é obrigatório.',
    'required_if' => 'O campo :attribute é obrigatório quando :other é :value.',
    'required_with' => 'O campo :attribute é obrigatório quando :values está preenchido.',
    'required_without' => 'O campo :attribute é obrigatório quando :values não está preenchido.',
    'same' => 'Os campos :attribute e :other precisam ser iguais.',
    'size' => [
        'numeric' => 'O campo :attribute precisa ser :size.',
        'string' => 'O campo :attribute precisa ter exatamente :size caracteres.',
    ],
    'starts_with' => 'O campo :attribute precisa começar com: :values.',
    'string' => 'O campo :attribute precisa ser um texto.',
    'timezone' => 'Informe um fuso horário válido em :attribute.',
    'unique' => 'Já existe um cadastro com este :attribute.',
    'uploaded' => 'O envio de :attribute falhou. Tente de novo.',
    'url' => 'Informe um endereço válido em :attribute.',
    'uuid' => 'O campo :attribute precisa ser um UUID válido.',

    /*
     * Nomes dos campos como as telas os chamam. O que nao estiver aqui aparece
     * com o proprio nome do campo, entao so entram os que divergem da tela.
     */
    'attributes' => [
        'razao_social' => 'razão social',
        'cnpj' => 'CNPJ',
        'cpf' => 'CPF',
        'email' => 'e-mail',
        'senha' => 'senha',
        'nome' => 'nome',
        'telefone' => 'telefone',
        'empresa' => 'empresa',
        'funcionarios' => 'funcionários',
        'finalidade' => 'finalidade',
        'documento' => 'documento',
        'solicitante' => 'solicitante',
        'plano_id' => 'plano',
        'vendedor_id' => 'vendedor',
        'servico_id' => 'serviço',
        'comissao_pct' => 'comissão',
        'situacao' => 'situação',
        'papel' => 'função',
        'responsavel_nome' => 'responsável pelo contrato',
        'responsavel_cpf' => 'CPF do responsável',
        'vigencia_tipo' => 'vigência',
        'contrato_inicio' => 'início do contrato',
        'contrato_fim' => 'fim da vigência',
        'carencia_ate' => 'carência',
        'adesao_valor' => 'taxa de adesão',
        'adesao_parcelas' => 'parcelas da adesão',
    ],
];
