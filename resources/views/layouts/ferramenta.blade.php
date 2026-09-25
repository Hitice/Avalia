{{--
    A casca das ferramentas que sao negocio proprio.

    Fora do CRM de proposito: o QR dinamico nao e um modulo do Avalia One, e
    abrir com a barra lateral e a marca de la diz ao operador que ele entrou no
    sistema de credito. E produto separado, com identidade separada, e so o
    login e compartilhado.

    Sem barra lateral e sem menu de modulos: a ferramenta tem duas telas, e uma
    lateral de 290px para duas telas e moldura maior que o quadro.

    SO TEMA CLARO, como o site. O interruptor de tema e ferramenta de quem
    passa o dia dentro do CRM, e aqui ele so atrapalhava: encostava no botao de
    sair nas telas largas e ficava sem clique. Tirar o interruptor e deixar o
    tema escuro seria pior ainda, porque quem tivesse marcado escuro no CRM
    abriria esta tela no escuro sem jeito de voltar.
--}}

<!DOCTYPE html>
<html lang="pt-BR" class="h-full">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex">

    <title>{{ $title ?? 'QR dinâmico' }} · QR dinâmico</title>

    <link rel="icon" href="{{ asset('favicon.svg') }}?v=2" type="image/svg+xml">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

</head>

<body class="min-h-full bg-gray-50 text-gray-800 antialiased">
    <header class="relative border-b border-gray-200 bg-white">
        <div class="mx-auto flex h-[60px] w-full max-w-[87rem] items-center justify-between px-6">
            <a href="{{ route('etiquetas.index') }}" class="flex items-center gap-2.5">
                <svg class="size-7 text-brand-500" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4h6v6H4V4Zm10 0h6v6h-6V4ZM4 14h6v6H4v-6Zm10 1.5a3.5 3.5 0 1 0 3.5 3.5m0-3.5V12m0 3.5H17" />
                </svg>
                <span class="text-sm font-semibold tracking-tight text-gray-800">
                    QR dinâmico
                </span>
            </a>

            <nav class="flex items-center gap-1" aria-label="Ferramenta">
                {{-- Duas ferramentas, dois itens. A tiragem nao entra aqui:
                     ela e um filtro da tabela de codigos, e nao uma tela. --}}
                <a href="{{ route('etiquetas.index') }}"
                   class="rounded-lg px-3 py-2 text-sm font-medium transition {{ request()->routeIs('etiquetas.index') || request()->routeIs('etiquetas.ficha') ? 'text-brand-500' : 'text-gray-500 hover:text-gray-800' }}">
                    QR dinâmico
                </a>
                <a href="{{ route('etiquetas.links.index') }}"
                   class="rounded-lg px-3 py-2 text-sm font-medium transition {{ request()->routeIs('etiquetas.links.*') ? 'text-brand-500' : 'text-gray-500 hover:text-gray-800' }}">
                    Encurtador
                </a>

                {{-- Nenhum caminho para o CRM daqui, de proposito. O QR
                     dinamico e negocio proprio: um link para o Avalia One no
                     cabecalho diria que ele e um modulo de la. Quem precisa do
                     outro lado digita o endereco; a sessao e a mesma. --}}
                {{-- Cada natureza de conta sai pela porta dela: o `sair` do
                     CRM nao encerra a sessao do produtor, e mandar todo mundo
                     para o mesmo deixaria um deles logado achando que saiu. --}}
                <form method="POST" action="{{ auth('produtor')->check() ? route('produtor.sair') : route('sair') }}" class="ml-2">
                    @csrf
                    <button type="submit" class="rounded-lg px-3 py-2 text-sm font-medium text-gray-500 transition hover:text-gray-800">
                        Sair
                    </button>
                </form>
            </nav>
        </div>

    </header>

    <main class="mx-auto w-full max-w-[87rem] p-4 md:p-6">
        @yield('content')
    </main>

    <footer class="mx-auto w-full max-w-[87rem] px-6 pb-10 text-xs text-gray-400">
        Um serviço da {{ App\Support\Empresa::marca() }}.
    </footer>
</body>

</html>
