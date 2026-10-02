{{-- Porta fechada para quem chegou pelo endereco. Quem veio por link nem ve
     esta tela: volta para onde estava com o aviso (bootstrap/app.php). --}}
@php
    $mensagem = $exception->getMessage() ?: 'Área restrita.';
    $logado = auth('staff')->check() || auth('empresa')->check();
@endphp

@if ($logado)
    @extends('layouts.app', ['title' => 'Área restrita'])

    @section('content')
        <div class="cartao mx-auto mt-10 flex max-w-md flex-col items-center p-8 text-center">
            <svg class="size-12 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"
                 role="img" aria-label="Cadeado">
                <rect x="4.5" y="10.5" width="15" height="10" rx="2"/>
                <path stroke-linecap="round" d="M8 10.5V7.5a4 4 0 0 1 8 0v3M12 14.5v2.5"/>
            </svg>
            <h1 class="mt-4 text-lg font-semibold text-gray-800 dark:text-white/90">{{ $mensagem }}</h1>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Se precisa deste acesso, fale com a administração.</p>
            <a href="{{ App\Helpers\MenuHelper::inicioDaArea() }}" class="botao botao-primario mt-6">Voltar ao início</a>
        </div>
    @endsection
@else
    @extends('layouts.site', ['titulo' => 'Área restrita', 'descricao' => $mensagem, 'robots' => 'noindex'])

    @section('content')
        <x-site.cabecalho selo="Erro 403" titulo="Esta área é restrita."
                          icone="M8 10.5V7.5a4 4 0 0 1 8 0v3M4.5 10.5h15v10h-15z">
            <x-slot:rodape>
                <p class="max-w-2xl text-lg leading-relaxed text-white/60">{{ $mensagem }}</p>
                <div class="mt-8 flex flex-wrap items-center gap-3">
                    <x-avalia.botao :href="route('entrar')">Entrar</x-avalia.botao>
                </div>
            </x-slot:rodape>
        </x-site.cabecalho>
    @endsection
@endif
