{{--
    A casca das ferramentas que sao negocio proprio.

    Fora do CRM de proposito: o QR dinamico nao e um modulo do Avalia One, e
    abrir com a barra lateral e a marca de la diz ao operador que ele entrou no
    sistema de credito. E produto separado, com identidade separada, e so o
    login e compartilhado.

    Sem barra lateral e sem menu de modulos: a ferramenta tem duas telas, e uma
    lateral de 290px para duas telas e moldura maior que o quadro.

    O bloco de tema repete o de layouts/app.blade.php. Repetido de proposito,
    e nao extraido: mexer no `head` do layout que sustenta o sistema inteiro
    para acomodar uma ferramenta nova troca um risco pequeno por um grande.
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

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.store('theme', {
                theme: 'light',
                init() {
                    this.theme = localStorage.getItem('theme')
                        || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
                    this.updateTheme();
                },
                toggle() {
                    this.theme = this.theme === 'light' ? 'dark' : 'light';
                    localStorage.setItem('theme', this.theme);
                    this.updateTheme();
                },
                updateTheme() {
                    document.documentElement.classList.toggle('dark', this.theme === 'dark');
                    document.body.classList.toggle('dark', this.theme === 'dark');
                    document.body.classList.toggle('bg-gray-900', this.theme === 'dark');
                },
            });
        });
    </script>

    {{-- Antes de pintar: sem isto a tela pisca branca antes de escurecer. --}}
    <script>
        (function () {
            const tema = localStorage.getItem('theme')
                || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');

            if (tema === 'dark') {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>
</head>

<body class="min-h-full bg-gray-50 text-gray-800 antialiased dark:bg-gray-900 dark:text-white/90">
    <header class="relative border-b border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
        <div class="mx-auto flex h-[60px] w-full max-w-[87rem] items-center justify-between px-6 pr-12 sm:pr-14 min-[1550px]:pr-0">
            <a href="{{ route('etiquetas.index') }}" class="flex items-center gap-2.5">
                <svg class="size-7 text-brand-500" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4h6v6H4V4Zm10 0h6v6h-6V4ZM4 14h6v6H4v-6Zm10 1.5a3.5 3.5 0 1 0 3.5 3.5m0-3.5V12m0 3.5H17" />
                </svg>
                <span class="text-sm font-semibold tracking-tight text-gray-800 dark:text-white/90">
                    QR dinâmico
                </span>
            </a>

            <nav class="flex items-center gap-1" aria-label="Ferramenta">
                <a href="{{ route('etiquetas.index') }}"
                   class="rounded-lg px-3 py-2 text-sm font-medium transition {{ request()->routeIs('etiquetas.index') ? 'text-brand-500' : 'text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-white/90' }}">
                    Códigos
                </a>
                <a href="{{ route('etiquetas.lotes.index') }}"
                   class="rounded-lg px-3 py-2 text-sm font-medium transition {{ request()->routeIs('etiquetas.lotes.*') ? 'text-brand-500' : 'text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-white/90' }}">
                    Tiragens
                </a>

                {{-- Nenhum caminho para o CRM daqui, de proposito. O QR
                     dinamico e negocio proprio: um link para o Avalia One no
                     cabecalho diria que ele e um modulo de la. Quem precisa do
                     outro lado digita o endereco; a sessao e a mesma. --}}
                <form method="POST" action="{{ route('sair') }}" class="ml-2">
                    @csrf
                    <button type="submit" class="rounded-lg px-3 py-2 text-sm font-medium text-gray-500 transition hover:text-gray-800 dark:text-gray-400 dark:hover:text-white/90">
                        Sair
                    </button>
                </form>
            </nav>
        </div>

        <x-avalia.tema class="absolute top-1/2 right-3 size-11 -translate-y-1/2 sm:right-4" />
    </header>

    <main class="mx-auto w-full max-w-[87rem] p-4 md:p-6">
        @yield('content')
    </main>

    <footer class="mx-auto w-full max-w-[87rem] px-6 pb-10 text-xs text-gray-400">
        Um serviço da {{ App\Support\Empresa::marca() }}.
    </footer>
</body>

</html>
