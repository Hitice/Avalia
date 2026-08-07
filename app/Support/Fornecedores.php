<?php

namespace App\Support;

/**
 * O catalogo de conexoes que a Avalia sabe fazer.
 *
 * A tela de Conexoes oferece esta lista, e nao um formulario livre: cada
 * fornecedor tem os campos que o contrato dele pede, a URL da documentacao e,
 * quando ha, o webhook que precisa ser cadastrado la. Fornecedor novo entra
 * aqui primeiro, com os campos certos, e a tela o exibe sozinha.
 *
 * O levantamento que sustenta estes campos (autenticacao, formato, caminho de
 * contratacao de cada um) esta na secao de conexoes do PDD.
 */
final class Fornecedores
{
    /** @return array<string, array<string, mixed>> */
    public static function todos(): array
    {
        return [
            'asaas' => [
                'nome' => 'Asaas',
                'categoria' => 'Cobrança',
                'descricao' => 'Emite os boletos e o Pix das faturas e confirma o pagamento sozinho, pelo webhook.',
                'doc' => 'https://docs.asaas.com',
                'webhook' => 'webhooks.asaas',
                'campos' => [
                    ['chave' => 'api_key', 'rotulo' => 'Chave da API', 'secreto' => true,
                        'ajuda' => 'Gerada no painel do Asaas, em Integrações. A de produção começa com $aact_prod_, a de teste com $aact_hmlg_.'],
                    ['chave' => 'webhook_token', 'rotulo' => 'Token do webhook', 'secreto' => true,
                        'ajuda' => 'O mesmo token informado ao cadastrar a URL do webhook no painel do Asaas.'],
                ],
                // A chave e a URL andam juntas: chave de teste so funciona na
                // URL de teste. O ambiente escolhe as duas de uma vez.
                'ambientes' => [
                    'producao' => 'https://api.asaas.com/v3',
                    'homologacao' => 'https://api-sandbox.asaas.com/v3',
                ],
            ],

            'serasa' => [
                'nome' => 'Serasa Experian',
                'categoria' => 'Bureau de crédito',
                'descricao' => 'Consultas de crédito PF e PJ (Concentre, Relato, score), com as credenciais do contrato da Avalia.',
                'doc' => 'https://developer.serasaexperian.com.br/apis',
                'campos' => [
                    ['chave' => 'client_id', 'rotulo' => 'Client ID', 'secreto' => false],
                    ['chave' => 'client_secret', 'rotulo' => 'Client Secret', 'secreto' => true],
                ],
                // URLs oficiais do portal do desenvolvedor: a chave e o
                // endereco andam juntos, e homologacao tem credencial propria.
                'ambientes' => [
                    'producao' => 'https://api.serasaexperian.com.br',
                    'homologacao' => 'https://uat-api.serasaexperian.com.br',
                ],
            ],

            'spc' => [
                'nome' => 'SPC Brasil',
                'categoria' => 'Bureau de crédito',
                'descricao' => 'Consultas e negativações do SPC. O acesso vem pela CDL ou associação comercial, que emite operador e senha.',
                'doc' => 'https://www.spcbrasil.org.br/empresa',
                'campos' => [
                    ['chave' => 'operador', 'rotulo' => 'Operador', 'secreto' => false],
                    ['chave' => 'senha', 'rotulo' => 'Senha', 'secreto' => true],
                    ['chave' => 'base_url', 'rotulo' => 'URL do webservice', 'secreto' => false,
                        'ajuda' => 'Fornecida pela CDL. O SPC só aceita requisição de IP brasileiro autorizado.'],
                ],
            ],

            'boa-vista' => [
                'nome' => 'Equifax | Boa Vista',
                'categoria' => 'Bureau de crédito',
                'descricao' => 'Consultas SCPC e relatórios (cadastro, restritivos, score). O portal do desenvolvedor tem sandbox aberto, com credenciais próprias por ambiente.',
                'doc' => 'https://developer.equifax.com/products/apiproducts/equifax-boa-vista-api-scpc',
                'campos' => [
                    ['chave' => 'client_id', 'rotulo' => 'Client ID', 'secreto' => false],
                    ['chave' => 'client_secret', 'rotulo' => 'Client Secret', 'secreto' => true],
                    // Os dois escopos que o portal mostra na app, um por
                    // produto: cada token vale para um escopo so.
                    ['chave' => 'escopo_scpc', 'rotulo' => 'Escopo do SCPC', 'secreto' => false,
                        'ajuda' => 'Copie da app no portal, em Equifax Boa Vista API SCPC. Termina em /business/scpc-gateway/v1.'],
                    ['chave' => 'escopo_relatorios', 'rotulo' => 'Escopo dos Relatórios', 'secreto' => false,
                        'ajuda' => 'Copie da app no portal, em Equifax Boa Vista API Reports. Termina em /business/reporting-orchestrator/v1.'],
                    ['chave' => 'caminho_consulta', 'rotulo' => 'Caminho da consulta', 'secreto' => false,
                        'ajuda' => 'O recurso depois do escopo, conforme a API Reference do seu contrato. Em branco, a conexão só valida a credencial.'],
                ],
                // Os tres ambientes do portal. A credencial e propria de cada
                // um: a de sandbox nao vale em producao.
                'ambientes' => [
                    'producao' => 'https://api.equifax.com',
                    'homologacao' => 'https://api.uat.equifax.com',
                    'sandbox' => 'https://api.sandbox.equifax.com',
                ],
            ],

            'quod' => [
                'nome' => 'Quod',
                'categoria' => 'Bureau de crédito',
                'descricao' => 'Bureau criado pelos grandes bancos. Consultas de crédito PF e PJ com dado direto da fonte, no contrato da Avalia.',
                'doc' => 'https://www.quod.com.br',
                'campos' => [
                    ['chave' => 'client_id', 'rotulo' => 'Client ID', 'secreto' => false],
                    ['chave' => 'client_secret', 'rotulo' => 'Client Secret', 'secreto' => true],
                    ['chave' => 'base_url', 'rotulo' => 'URL do serviço', 'secreto' => false],
                ],
            ],

            'veicular' => [
                'nome' => 'Consulta veicular',
                'categoria' => 'Veicular',
                'descricao' => 'Consultas veiculares do contrato da Avalia (base estadual, BIN, gravame, leilão, sinistro, FIPE).',
                'doc' => 'https://www.gov.br/transportes/pt-br/assuntos/transito',
                'campos' => [
                    ['chave' => 'token', 'rotulo' => 'Token da API', 'secreto' => true],
                    ['chave' => 'base_url', 'rotulo' => 'URL do serviço', 'secreto' => false],
                ],
            ],

            'scr' => [
                'nome' => 'SCR · Banco Central',
                'categoria' => 'Bureau de crédito',
                'descricao' => 'Endividamento no sistema financeiro. O acesso ao SCR passa por instituição autorizada pelo BACEN e toda consulta exige consentimento do titular.',
                'doc' => 'https://www.bcb.gov.br/estabilidadefinanceira/scr',
                'campos' => [
                    ['chave' => 'client_id', 'rotulo' => 'Client ID', 'secreto' => false],
                    ['chave' => 'client_secret', 'rotulo' => 'Client Secret', 'secreto' => true],
                    ['chave' => 'base_url', 'rotulo' => 'URL do serviço', 'secreto' => false],
                ],
            ],
        ];
    }

    public static function existe(string $slug): bool
    {
        return array_key_exists($slug, self::todos());
    }

    /** @return array<string, mixed> */
    public static function de(string $slug): array
    {
        return self::todos()[$slug];
    }
}
