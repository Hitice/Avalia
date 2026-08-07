<?php

return [

    'asaas' => [
        'api_key' => env('ASAAS_API_KEY'),
        'webhook_token' => env('ASAAS_WEBHOOK_TOKEN'),
        'base_url' => env('ASAAS_BASE_URL', 'https://api.asaas.com/v3'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
     * Quem atende as consultas de credito. Sem conector real configurado vale o
     * simulado, que responde sem falar com fornecedor nenhum e marca a resposta
     * como simulada.
     */
    'bureau' => [
        // Vazio deixa a tela de Conexoes decidir: a primeira conexao de
        // bureau ativa escolhe o conector, e sem nenhuma vale o simulado.
        // Definir BUREAU_CONECTOR forca um valor, o que serve a homologacao.
        'conector' => env('BUREAU_CONECTOR', ''),
    ],

    /*
     * Canal de duvidas do cliente. So o numero: a mensagem e montada em
     * App\Support\Suporte, que decide o que pode e o que nao pode ir na URL.
     */
    'suporte' => [
        'whatsapp' => env('SUPORTE_WHATSAPP', '5534991176599'),
    ],

];
