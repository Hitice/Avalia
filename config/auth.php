<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Padrao
    |--------------------------------------------------------------------------
    |
    | O guard padrao e o `staff`. Rota que esquecer de declarar o guard cai na
    | area de gestao, que e a mais protegida, e errar o roteamento passa a
    | fechar a porta em vez de abrir.
    |
    */

    'defaults' => [
        'guard' => 'staff',
        'passwords' => 'staff',
    ],

    /*
    |--------------------------------------------------------------------------
    | Guards
    |--------------------------------------------------------------------------
    |
    | Duas naturezas de conta que nunca se misturam:
    |   staff   -> quem opera a Avalia (admin, vendedor)
    |   empresa -> o cliente contratante, que consulta e ve as proprias faturas
    |
    | Tabelas e providers separados: nao existe caminho de codigo em que um
    | cliente seja resolvido como operador.
    |
    */

    'guards' => [
        'staff' => [
            'driver' => 'session',
            'provider' => 'staff',
        ],

        'empresa' => [
            'driver' => 'session',
            'provider' => 'clientes',
        ],
    ],

    'providers' => [
        'staff' => [
            'driver' => 'eloquent',
            'model' => App\Models\Staff::class,
        ],

        'clientes' => [
            'driver' => 'eloquent',
            'model' => App\Models\Cliente::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Redefinicao de senha
    |--------------------------------------------------------------------------
    */

    'passwords' => [
        'staff' => [
            'provider' => 'staff',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],

        'clientes' => [
            'provider' => 'clientes',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
