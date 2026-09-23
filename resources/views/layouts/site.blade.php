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

    <title>{{ Empresa::marca() }} · {{ $titulo }}</title>

    {{-- O medidor da marca em laranja. Laranja, e nao o azul da casa: a aba
         e um quadrado de 16px no meio de outros, e o azul se perde entre os
         favicons de sistema. --}}
    {{-- A versao no endereco existe porque o navegador guarda favicon com
         teimosia: sem ela, quem ja abriu o site continua vendo o icone antigo
         por tempo indeterminado. --}}
    <link rel="icon" href="{{ asset('favicon.svg') }}?v=2" type="image/svg+xml">
    <link rel="mask-icon" href="{{ asset('favicon.svg') }}?v=2" color="#fb6514">
    <meta name="description" content="{{ $descricao }}">
    <meta name="robots" content="{{ $robots ?? 'index, follow' }}">
    <link rel="canonical" href="{{ url()->current() }}">

    <meta property="og:type" content="{{ $tipoOg ?? 'website' }}">
    <meta property="og:site_name" content="{{ Empresa::marca() }}">
    <meta property="og:locale" content="pt_BR">
    <meta property="og:title" content="{{ $titulo }}">
    <meta property="og:description" content="{{ $descricao }}">
    <meta property="og:url" content="{{ url()->current() }}">

    {{-- Sora e Manrope so aqui: o sistema segue em Outfit, e carregar duas
         familias a mais nas telas de trabalho custaria sem ninguem pedir. --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet"
          href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&family=Sora:wght@500;600;700&display=swap">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

{{--
    O site vive no tema claro e so nele.

    Nenhum script de tema aqui, de proposito: quem passou pelo CRM e deixou o
    escuro ligado tem "dark" guardado no navegador, e bastaria ler esse valor
    para o site institucional abrir metade preto e metade branco. O preto que
    o site usa e superficie escolhida no tema claro, e nao o tema escuro.
--}}

<body class="tipografia-site bg-white text-gray-800 antialiased">
    <a href="#conteudo" class="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 focus:z-50 focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:text-brand-600 focus:shadow-theme-md">
        Pular para o conteúdo
    </a>

    {{-- Topo de 60px, o mesmo de toda tela do sistema.

         No alto da pagina ele ocupa a largura inteira, alinhado com o
         conteudo. Ao rolar, encolhe para uma ilha arredondada e solta, que
         flutua sobre o texto: e o mesmo gesto de um cabecalho que sai do
         caminho sem sumir, e diz sozinho que a pagina saiu do topo. --}}
    <header x-data="{
                menu: false,
                rolou: false,
                convite: false,
                jaMostrou: false,

                /* Mostra uma vez, e so depois de a pessoa demonstrar interesse:
                   rolando alem do herói ou ficando oito segundos na pagina.
                   Convite na chegada interrompe quem ainda nao sabe o que o
                   site e, que e exatamente o que o torna chato.

                   A marca no navegador e gravada na hora em que ele aparece, e
                   nao quando alguem fecha: quem ignorou tambem ja viu. */
                init() {
                    let visto = true;

                    try {
                        visto = !! localStorage.getItem('avalia-convite-produtor');
                    } catch (e) {}

                    if (visto) {
                        return;
                    }

                    const abrir = () => {
                        if (this.jaMostrou) return;

                        this.jaMostrou = true;
                        this.convite = true;

                        try { localStorage.setItem('avalia-convite-produtor', '1') } catch (e) {}
                    };

                    const aoRolar = () => {
                        if (window.scrollY > 400) abrir();
                    };

                    window.addEventListener('scroll', aoRolar, { passive: true });
                    setTimeout(abrir, 8000);
                },
            }"
            @scroll.window.passive="rolou = window.scrollY > 40"
            class="fixed inset-x-0 top-0 z-40 px-3 sm:px-6">
        {{-- A ilha e o unico estado do cabecalho: ela ja nasce redonda e solta,
             flutuando por cima da faixa escura do topo. Antes havia uma barra
             quadrada de largura inteira que virava ilha ao rolar, e essa troca
             de forma no meio da rolagem nunca ficou boa.

             O que a rolagem muda agora e so a densidade do vidro. No alto, a
             ilha esta sobre a faixa escura e pode ser mais transparente; a
             partir dali ela passa por cima do corpo branco da pagina, e o preto
             precisa fechar para o texto continuar legivel.

             Escura nos dois casos, entao a cor do texto nao muda no meio do
             caminho. --}}
        <div :class="rolou ? 'bg-[#000]/80 shadow-theme-lg' : 'bg-[#000]/40 shadow-theme-md'"
             class="relative mx-auto mt-3 flex h-[60px] w-full max-w-[64rem] items-center justify-between rounded-full border border-white/10 px-6 backdrop-blur-xl transition-colors duration-300">
            <a href="{{ route('inicio') }}" aria-label="{{ Empresa::marca() }}, início" class="flex items-center">
                <x-avalia.logotipo :tamanho="34" texto="1.3rem" claro />
            </a>

            <nav class="hidden items-center gap-1 lg:flex" aria-label="Principal">
                @foreach ($menu as $item)
                    <a href="{{ route($item['rota']) }}"
                       @if ($atual === $item['rota']) aria-current="page" @endif
                       class="rounded-lg px-3 py-2 text-sm font-medium transition {{ $atual === $item['rota'] ? 'text-white' : 'text-white/60 hover:text-white' }}">
                        {{ $item['rotulo'] }}
                    </a>
                @endforeach

                {{-- Rosa, e nao azul: e a unica acao do cabecalho, e sobre a
                     faixa preta o azul da marca some entre os cinzas. --}}
                <x-avalia.botao variante="rosa" class="ml-2" :href="route('area')">
                    Acesso
                    <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 3h4a1 1 0 011 1v16a1 1 0 01-1 1h-4M10 17l5-5-5-5M15 12H3" />
                    </svg>
                </x-avalia.botao>
            </nav>

            {{-- No celular o menu inteiro cabe atras de um botao, e a Área do
                 produtor fica fora dele: e a acao que traz quem ja e cliente,
                 e escondida atras de dois toques ela deixa de existir. --}}
            <div class="flex items-center gap-2 lg:hidden">
                <x-avalia.botao variante="rosa" tamanho="sm" :href="route('area')">Acesso</x-avalia.botao>

                <button type="button" @click="menu = ! menu" :aria-expanded="menu ? 'true' : 'false'"
                        aria-controls="menu-celular" aria-label="Abrir menu"
                        class="botao botao-sm botao-icone border border-white/20 text-white transition hover:bg-white/10">
                    <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" x-show="! menu" d="M4 7h16M4 12h16M4 17h16" />
                        <path stroke-linecap="round" x-cloak x-show="menu" d="m6 6 12 12M18 6 6 18" />
                    </svg>
                </button>
            </div>
            {{-- O convite de quem ja e cliente.

                 Balao ancorado no botao, e nao caixa no meio da tela: ele
                 oferece sem bloquear, e quem nao quiser simplesmente continua
                 lendo. Ao fechar, encolhe para o canto de onde saiu, que e o
                 proprio botao. --}}
            @if ($atual !== 'area')
                <div x-cloak x-show="convite"
                     x-transition:enter="transition duration-300 ease-out"
                     x-transition:enter-start="translate-y-1 scale-90 opacity-0"
                     x-transition:leave="transition duration-200 ease-in"
                     x-transition:leave-end="scale-50 opacity-0"
                     class="absolute top-full right-0 z-10 mt-3 w-72 origin-top-right rounded-2xl border border-gray-200 bg-white p-4 shadow-theme-lg"
                     role="status">
                    <button type="button" @click="convite = false" aria-label="Fechar"
                            class="absolute top-2.5 right-2.5 flex size-7 items-center justify-center rounded-lg text-gray-400 transition hover:bg-gray-100 hover:text-gray-700">
                        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m6 6 12 12M18 6 6 18" />
                        </svg>
                    </button>

                    <p class="pr-6 text-sm text-gray-600">
                        <strong class="block font-semibold text-gray-900">Já é cliente {{ Empresa::marca() }}?</strong>
                        Acesse uma de nossas plataformas.
                    </p>

                    <x-avalia.botao variante="rosa" tamanho="sm" :href="route('area')" class="mt-3 w-full">
                        Acessar
                        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6" />
                        </svg>
                    </x-avalia.botao>
                </div>
            @endif
        </div>
        <div x-cloak x-show="menu" x-transition.opacity.duration.150ms id="menu-celular"
             class="mx-auto mt-2 w-full max-w-[87rem] rounded-2xl border border-white/10 bg-[#000]/90 shadow-theme-lg backdrop-blur-xl lg:hidden">
            <nav class="flex flex-col px-4 py-3" aria-label="Principal">
                @foreach ($menu as $item)
                    <a href="{{ route($item['rota']) }}"
                       @if ($atual === $item['rota']) aria-current="page" @endif
                       class="rounded-lg px-2 py-2.5 text-sm font-medium transition {{ $atual === $item['rota'] ? 'text-white' : 'text-white/60 hover:text-white' }}">
                        {{ $item['rotulo'] }}
                    </a>
                @endforeach
            </nav>
        </div>
    </header>

    {{-- Sem respiro no topo: a faixa escura de cada pagina comeca no alto e
         passa por baixo da ilha. Quem reserva o espaco do cabecalho e a
         propria faixa, que abre com 76px de folga. --}}
    <main id="conteudo">
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
                            {{ Empresa::rotulo() }} · {{ Empresa::localidade() }}<br>
                            {{ Empresa::bracoRotulo() }} · {{ Empresa::bracoLocalidade() }}
                        </span>
                    </li>
                </ul>
            </div>
        </div>

        {{-- A linha de registro: ano, razao social e CNPJ, centrados. A praca
             saiu daqui quando as duas pracas ganharam rotulo na coluna de
             contato: repetida, ela so alongava o pe da pagina. O endereco
             completo fica em documento (fatura, laudo, contrato). --}}
        <div class="border-t border-white/10">
            <p class="mx-auto w-full max-w-[87rem] px-6 py-5 text-center text-xs leading-relaxed text-white/40">
                © {{ now()->year }} {{ Empresa::razaoSocial() }} · CNPJ {{ Empresa::cnpj() }}
            </p>
        </div>
    </footer>
</body>

</html>
