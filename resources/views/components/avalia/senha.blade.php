@props(['classe' => 'campo'])

{{--
    Campo de senha com o olho de mostrar.

    Existe porque senha digitada as cegas e erro de digitacao que so aparece na
    mensagem de recusa, e quem toma "senha invalida" duas vezes acha que
    esqueceu a senha. O olho ja existia na tela de entrada, escrito a mao; aqui
    ele passa a valer em toda tela que pede senha, sem ninguem precisar lembrar
    de copiar o bloco.

    Sem JavaScript o campo continua um campo de senha comum, so sem o botao: o
    `type` nasce `password` no HTML e o Alpine assume depois.
--}}

<div x-data="{ visivel: false }" class="relative">
    <input {{ $attributes->merge(['type' => 'password', 'class' => $classe.' pr-11']) }}
           x-bind:type="visivel ? 'text' : 'password'">

    <button type="button" x-on:click="visivel = ! visivel" tabindex="-1"
            x-bind:aria-label="visivel ? 'Ocultar senha' : 'Mostrar senha'"
            class="absolute top-1/2 right-3 -translate-y-1/2 cursor-pointer text-gray-400 transition hover:text-gray-600 dark:hover:text-gray-300">
        <svg x-show="! visivel" class="size-5" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z" />
            <circle cx="12" cy="12" r="2.75" />
        </svg>

        <svg x-show="visivel" x-cloak class="size-5" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18M10.6 6a9.6 9.6 0 0 1 1.4-.1c6 0 9.5 6.5 9.5 6.5a17 17 0 0 1-3 3.8M6.5 8.2A17 17 0 0 0 2.5 12S6 18.5 12 18.5c1.4 0 2.6-.3 3.7-.8M9.9 9.9a2.75 2.75 0 0 0 3.9 3.9" />
        </svg>
    </button>
</div>
