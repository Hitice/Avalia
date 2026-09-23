@php
    use App\Support\Empresa;
    use App\Support\Suporte;

    // O menu do site, num lugar so. O cabecalho e o rodape mostram os mesmos
    // itens, e escritos duas vezes um deles sairia desatualizado no dia em que
    // uma pagina entrasse ou saisse.
    $menu = [
        ['rota' => 'inicio', 'rotulo' => 'Início'],
        ['rota' => 'site.softwares', 'rotulo' => 'Softwares'],
        ['rota' => 'site.quem-somos', 'rotulo' => 'Quem somos'],
        ['rota' => 'site.blog', 'rotulo' => 'Blog'],
        ['rota' => 'site.contato', 'rotulo' => 'Contato'],
    ];

    // Qual item fica marcado como atual. O artigo do blog aponta para o blog:
    // sem isso, o leitor de um artigo ve o menu inteiro apagado e perde a
    // referencia de onde esta.
    $atual = $secao ?? request()->route()?->getName();
@endphp

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $titulo }} · {{ Empresa::marca() }}</title>
    <meta name="description" content="{{ $descricao }}">
    <meta name="robots" content="{{ $robots ?? 'index, follow' }}">
    <link rel="canonical" href="{{ url()->current() }}">

    <meta property="og:type" content="{{ $tipoOg ?? 'website' }}">
    <meta property="og:site_name" content="{{ Empresa::marca() }}">
    <meta property="og:locale" content="pt_BR">
    <meta property="og:title" content="{{ $titulo }}">
    <meta property="og:description" content="{{ $descricao }}">
    <meta property="og:url" content="{{ url()->current() }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

{{--
    O site vive no tema claro e so nele.

    Nenhum script de tema aqui, de proposito: quem passou pelo CRM e deixou o
    escuro ligado tem "dark" guardado no navegador, e bastaria ler esse valor
    para o site institucional abrir metade preto e metade branco. O preto que
    o site usa e superficie escolhida no tema claro, e nao o tema escuro.
--}}

<body class="bg-white font-outfit text-gray-800 antialiased">
    <a href="#conteudo" class="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 focus:z-50 focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:text-brand-600 focus:shadow-theme-md">
        Pular para o conteúdo
    </a>

    {{-- Topo de 60px, o mesmo de toda tela do sistema. Fixo e quase solido:
         com a grade animada do herói passando por baixo, o cabecalho
         translucido sumia na pagina. --}}
    <header x-data="{ menu: false }" class="fixed inset-x-0 top-0 z-40 border-b border-gray-200 bg-white/95 shadow-theme-md backdrop-blur">
        <div class="mx-auto flex h-[60px] w-full max-w-[87rem] items-center justify-between px-6">
            <a href="{{ route('inicio') }}" aria-label="{{ Empresa::marca() }}, início" class="flex items-center">
                <x-avalia.logotipo :tamanho="34" texto="1.3rem" />
            </a>

            <nav class="hidden items-center gap-1 lg:flex" aria-label="Principal">
                @foreach ($menu as $item)
                    <a href="{{ route($item['rota']) }}"
                       @if ($atual === $item['rota']) aria-current="page" @endif
                       class="rounded-lg px-3 py-2 text-sm font-medium transition {{ $atual === $item['rota'] ? 'text-brand-600' : 'text-gray-600 hover:text-gray-900' }}">
                        {{ $item['rotulo'] }}
                    </a>
                @endforeach

                <x-avalia.botao class="ml-2" :href="route('area')">
                    Área do produtor
                    <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 3h4a1 1 0 011 1v16a1 1 0 01-1 1h-4M10 17l5-5-5-5M15 12H3" />
                    </svg>
                </x-avalia.botao>
            </nav>

            {{-- No celular o menu inteiro cabe atras de um botao, e a Área do
                 produtor fica fora dele: e a acao que traz quem ja e cliente,
                 e escondida atras de dois toques ela deixa de existir. --}}
            <div class="flex items-center gap-2 lg:hidden">
                <x-avalia.botao tamanho="sm" :href="route('area')">Entrar</x-avalia.botao>

                <button type="button" @click="menu = ! menu" :aria-expanded="menu ? 'true' : 'false'"
                        aria-controls="menu-celular" aria-label="Abrir menu"
                        class="botao botao-secundario botao-sm botao-icone">
                    <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" x-show="! menu" d="M4 7h16M4 12h16M4 17h16" />
                        <path stroke-linecap="round" x-cloak x-show="menu" d="m6 6 12 12M18 6 6 18" />
                    </svg>
                </button>
            </div>
        </div>

        <div x-cloak x-show="menu" x-transition.opacity.duration.150ms id="menu-celular" class="border-t border-gray-100 lg:hidden">
            <nav class="mx-auto flex w-full max-w-[87rem] flex-col px-6 py-3" aria-label="Principal">
                @foreach ($menu as $item)
                    <a href="{{ route($item['rota']) }}"
                       @if ($atual === $item['rota']) aria-current="page" @endif
                       class="rounded-lg px-2 py-2.5 text-sm font-medium transition {{ $atual === $item['rota'] ? 'text-brand-600' : 'text-gray-600 hover:text-gray-900' }}">
                        {{ $item['rotulo'] }}
                    </a>
                @endforeach
            </nav>
        </div>
    </header>

    <main id="conteudo" class="pt-[60px]">
        @yield('content')
    </main>

    <footer class="superficie-escura">
        <div class="mx-auto grid w-full max-w-[87rem] gap-10 px-6 py-14 sm:grid-cols-2 lg:grid-cols-4">
            <div class="lg:col-span-1">
                <a href="{{ route('inicio') }}" aria-label="{{ Empresa::marca() }}, início" class="inline-flex">
                    <x-avalia.logotipo :tamanho="32" texto="1.25rem" claro />
                </a>
                <p class="mt-4 max-w-xs text-sm leading-relaxed text-white/60">
                    Software house de produtos digitais: automação, atendimento humanizado com IA e sistemas sob medida.
                </p>
            </div>

            <div>
                <h2 class="text-xs font-semibold tracking-[0.18em] text-white/40 uppercase">Navegação</h2>
                <ul class="mt-4 space-y-2.5 text-sm text-white/70">
                    @foreach ($menu as $item)
                        <li><a href="{{ route($item['rota']) }}" class="transition hover:text-white">{{ $item['rotulo'] }}</a></li>
                    @endforeach
                </ul>
            </div>

            <div>
                <h2 class="text-xs font-semibold tracking-[0.18em] text-white/40 uppercase">Institucional</h2>
                <ul class="mt-4 space-y-2.5 text-sm text-white/70">
                    <li><a href="{{ route('area') }}" class="transition hover:text-white">Área do produtor</a></li>
                    <li><a href="{{ route('site.perguntas') }}" class="transition hover:text-white">Perguntas frequentes</a></li>
                    <li><a href="{{ route('site.privacidade') }}" class="transition hover:text-white">Política de privacidade</a></li>
                    <li><a href="{{ route('site.termos') }}" class="transition hover:text-white">Termos de uso</a></li>
                </ul>
            </div>

            <div>
                <h2 class="text-xs font-semibold tracking-[0.18em] text-white/40 uppercase">Contato</h2>
                <ul class="mt-4 space-y-3 text-sm text-white/70">
                    <li>
                        <a href="mailto:{{ Empresa::email() }}" class="flex items-start gap-2.5 transition hover:text-white">
                            <svg class="mt-0.5 size-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                <rect x="3" y="5.5" width="18" height="13" rx="2" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4 7 8 6 8-6" />
                            </svg>
                            {{ Empresa::email() }}
                        </a>
                    </li>
                    <li>
                        <a href="{{ Suporte::whatsapp('Quero falar com a '.Empresa::marca()) }}" target="_blank" rel="noopener noreferrer"
                           class="flex items-start gap-2.5 transition hover:text-white">
                            <svg class="mt-0.5 size-4 shrink-0 fill-current" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M12.04 2c-5.46 0-9.9 4.44-9.9 9.9 0 1.75.46 3.45 1.32 4.95L2 22l5.3-1.39a9.86 9.86 0 0 0 4.74 1.21h.01c5.46 0 9.9-4.44 9.9-9.9 0-2.64-1.03-5.13-2.9-7A9.82 9.82 0 0 0 12.04 2Zm0 18.06h-.01a8.2 8.2 0 0 1-4.18-1.15l-.3-.18-3.11.82.83-3.03-.2-.31a8.19 8.19 0 0 1-1.26-4.37c0-4.54 3.7-8.23 8.24-8.23 2.2 0 4.27.86 5.82 2.42a8.18 8.18 0 0 1 2.41 5.82c0 4.54-3.7 8.23-8.24 8.23Z" />
                            </svg>
                            WhatsApp
                        </a>
                    </li>
                    <li class="flex items-start gap-2.5">
                        <svg class="mt-0.5 size-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 21s7-5.7 7-11a7 7 0 1 0-14 0c0 5.3 7 11 7 11Z" />
                            <circle cx="12" cy="10" r="2.5" />
                        </svg>
                        <span>
                            {{ Empresa::localidade() }}<br>
                            {{ Empresa::bracoRotulo() }} · {{ Empresa::bracoLocalidade() }}
                        </span>
                    </li>
                </ul>
            </div>
        </div>

        {{-- A linha de registro: ano, razao social, CNPJ e praca. O endereco
             completo fica em documento (fatura, laudo, contrato), e nao no pe
             de toda pagina. --}}
        <div class="border-t border-white/10">
            <p class="mx-auto w-full max-w-[87rem] px-6 py-5 text-xs leading-relaxed text-white/40">
                © {{ now()->year }} {{ Empresa::razaoSocial() }} · CNPJ {{ Empresa::cnpj() }} · {{ Empresa::localidade() }}
            </p>
        </div>
    </footer>
</body>

</html>
