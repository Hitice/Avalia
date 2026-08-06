@extends('layouts.fullscreen-layout', ['title' => 'Entrar'])

@section('content')
    {{-- O pedido de cadastro abre em modal aqui mesmo: quem chegou ate o
         login nao e mandado de volta para a pagina publica. Os erros de
         validacao do proprio pedido reabrem o modal; os do login, nao. --}}
    <div class="relative z-1 bg-white p-6 sm:p-0 dark:bg-gray-900"
         x-data="{ cadastro: {{ session('interesse_ok') || $errors->hasAny(['nome', 'empresa', 'telefone', 'funcionarios']) ? 'true' : 'false' }} }"
         @keydown.escape.window="cadastro = false">
        {{-- Claro e escuro no canto superior esquerdo: esta tela nao tem
             header, entao o interruptor mora solto na moldura. --}}
        <button @click="$store.theme.toggle()" aria-label="Alternar tema"
                class="fixed top-4 left-4 z-50 flex size-11 items-center justify-center rounded-lg border border-gray-200 bg-white/80 text-gray-500 backdrop-blur transition hover:bg-gray-100 hover:text-gray-700 dark:border-gray-800 dark:bg-gray-900/80 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white">
            <svg class="hidden size-5 dark:block" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="4"/>
                <path stroke-linecap="round" d="M12 2v2m0 16v2M4.9 4.9l1.4 1.4m11.4 11.4 1.4 1.4M2 12h2m16 0h2M4.9 19.1l1.4-1.4m11.4-11.4 1.4-1.4"/>
            </svg>
            <svg class="size-5 dark:hidden" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/>
            </svg>
        </button>
        <div class="relative flex h-screen w-full flex-col justify-center sm:p-0 lg:flex-row dark:bg-gray-900">

            {{-- Formulario --}}
            <div class="flex w-full flex-1 flex-col lg:w-2/5">
                <div class="mx-auto flex w-full max-w-[25rem] flex-1 flex-col justify-center">

                    <a href="{{ route('inicio') }}"
                       class="mb-8 inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-brand-500 dark:text-gray-400 dark:hover:text-brand-400">
                        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                        </svg>
                        Página inicial
                    </a>

                    <div class="mb-8">
                        <h1 class="mb-2 text-2xl font-semibold text-gray-800 dark:text-white/90">
                            Bem-vindo de volta
                        </h1>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Entre com seu e-mail e senha.
                        </p>
                    </div>

                    @if (session('erro'))
                        <div class="mb-5 flex items-start gap-3 rounded-lg border border-warning-300 bg-warning-50 p-4 text-sm text-warning-700 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-400">
                            <svg class="mt-0.5 size-5 shrink-0 fill-current" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v4a1 1 0 102 0V7zm-1 7a1 1 0 100 2 1 1 0 000-2z" clip-rule="evenodd"/>
                            </svg>
                            <span>{{ session('erro') }}</span>
                        </div>
                    @endif

                    @if ($errors->has('email') && ! $errors->hasAny(['empresa', 'funcionarios']))
                        <div class="mb-5 flex items-start gap-3 rounded-lg border border-error-300 bg-error-50 p-4 text-sm text-error-700 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400">
                            <svg class="mt-0.5 size-5 shrink-0 fill-current" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v4a1 1 0 102 0V7zm-1 7a1 1 0 100 2 1 1 0 000-2z" clip-rule="evenodd"/>
                            </svg>
                            <span>{{ $errors->first('email') }}</span>
                        </div>
                    @endif

                    {{-- action e autocomplete explicitos: sem eles o Chrome nao
                         oferece salvar a senha.

                         O formulario renova o proprio token enquanto a tela fica
                         aberta. A tela de entrada e a que mais fica esquecida em
                         aba, e era a que mais expirava: o token nasce com a
                         sessao e morre com ela, entao quem voltava depois do
                         almoco tomava "formulario expirou" antes de digitar
                         qualquer coisa. Cada renovacao tambem toca a sessao, o
                         que impede que ela expire com a aba aberta. --}}
                    <form method="POST" action="{{ route('entrar.enviar') }}" autocomplete="on"
                          x-data="{
                              renovar() {
                                  fetch('{{ route('token') }}', { headers: { 'Accept': 'application/json' } })
                                      .then(r => r.ok ? r.json() : null)
                                      .then(d => { if (d?.token) this.$refs.token.value = d.token })
                                      .catch(() => {})
                              },
                          }"
                          x-init="setInterval(() => renovar(), {{ (int) (config('session.lifetime') * 60 * 1000 / 3) }})">
                        {{-- No lugar de @csrf, para o campo ter referencia. --}}
                        <input type="hidden" name="_token" x-ref="token" value="{{ csrf_token() }}">

                        <div class="space-y-5">
                            <div>
                                <label for="email" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                    E-mail <span class="text-error-500">*</span>
                                </label>
                                <input type="email" id="email" name="email" required autofocus
                                    value="{{ old('email') }}" autocomplete="username"
                                    placeholder="exemplo@empresa.com.br"
                                    class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                            </div>

                            <div>
                                <label for="senha" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                    Senha <span class="text-error-500">*</span>
                                </label>
                                <div x-data="{ visivel: false }" class="relative">
                                    <input :type="visivel ? 'text' : 'password'" id="senha" name="senha" required
                                        autocomplete="current-password" placeholder="Sua senha"
                                        class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent py-2.5 pr-11 pl-4 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                                    <button type="button" @click="visivel = !visivel"
                                        :aria-label="visivel ? 'Ocultar senha' : 'Mostrar senha'"
                                        class="absolute top-1/2 right-4 z-30 -translate-y-1/2 cursor-pointer text-gray-500 dark:text-gray-400">
                                        <svg x-show="!visivel" class="fill-current" width="20" height="20" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" clip-rule="evenodd" d="M10.0002 13.8619C7.23361 13.8619 4.86803 12.1372 3.92328 9.70241C4.86804 7.26761 7.23361 5.54297 10.0002 5.54297C12.7667 5.54297 15.1323 7.26762 16.0771 9.70243C15.1323 12.1372 12.7667 13.8619 10.0002 13.8619ZM10.0002 4.04297C6.48191 4.04297 3.49489 6.30917 2.4155 9.4593C2.3615 9.61687 2.3615 9.78794 2.41549 9.94552C3.49488 13.0957 6.48191 15.3619 10.0002 15.3619C13.5184 15.3619 16.5055 13.0957 17.5849 9.94555C17.6389 9.78797 17.6389 9.6169 17.5849 9.45932C16.5055 6.30919 13.5184 4.04297 10.0002 4.04297ZM9.99151 7.84413C8.96527 7.84413 8.13333 8.67606 8.13333 9.70231C8.13333 10.7286 8.96527 11.5605 9.99151 11.5605H10.0064C11.0326 11.5605 11.8646 10.7286 11.8646 9.70231C11.8646 8.67606 11.0326 7.84413 10.0064 7.84413H9.99151Z" fill="#98A2B3"/>
                                        </svg>
                                        <svg x-show="visivel" x-cloak class="fill-current" width="20" height="20" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" clip-rule="evenodd" d="M4.63803 3.57709C4.34513 3.2842 3.87026 3.2842 3.57737 3.57709C3.28447 3.86999 3.28447 4.34486 3.57737 4.63775L4.85323 5.91362C3.74609 6.84199 2.89363 8.06395 2.4155 9.45936C2.3615 9.61694 2.3615 9.78801 2.41549 9.94558C3.49488 13.0957 6.48191 15.3619 10.0002 15.3619C11.255 15.3619 12.4422 15.0737 13.4994 14.5598L15.3625 16.4229C15.6554 16.7158 16.1302 16.7158 16.4231 16.4229C16.716 16.13 16.716 15.6551 16.4231 15.3622L4.63803 3.57709ZM12.3608 13.4212L10.4475 11.5079C10.3061 11.5423 10.1584 11.5606 10.0064 11.5606H9.99151C8.96527 11.5606 8.13333 10.7286 8.13333 9.70237C8.13333 9.5461 8.15262 9.39434 8.18895 9.24933L5.91885 6.97923C5.03505 7.69015 4.34057 8.62704 3.92328 9.70247C4.86803 12.1373 7.23361 13.8619 10.0002 13.8619C10.8326 13.8619 11.6287 13.7058 12.3608 13.4212ZM16.0771 9.70249C15.7843 10.4569 15.3552 11.1432 14.8199 11.7311L15.8813 12.7925C16.6329 11.9813 17.2187 11.0143 17.5849 9.94561C17.6389 9.78803 17.6389 9.61696 17.5849 9.45938C16.5055 6.30925 13.5184 4.04303 10.0002 4.04303C9.13525 4.04303 8.30244 4.17999 7.52218 4.43338L8.75139 5.66259C9.1556 5.58413 9.57311 5.54303 10.0002 5.54303C12.7667 5.54303 15.1323 7.26768 16.0771 9.70249Z" fill="#98A2B3"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            {{-- Marcado por padrao: e o comportamento que o usuario espera. --}}
                            <label for="lembrar" class="flex cursor-pointer items-center gap-3 text-sm text-gray-700 select-none dark:text-gray-400">
                                <input type="checkbox" id="lembrar" name="lembrar" value="1"
                                    {{ old('lembrar', true) ? 'checked' : '' }}
                                    class="text-brand-500 focus:ring-brand-500/20 size-5 rounded-md border-gray-300 focus:ring-2 dark:border-gray-700 dark:bg-gray-900" />
                                Manter conectado neste dispositivo
                            </label>

                            <button type="submit"
                                class="bg-brand-500 shadow-theme-xs hover:bg-brand-600 flex w-full items-center justify-center rounded-lg px-4 py-3 text-sm font-medium text-white transition">
                                Entrar
                            </button>
                        </div>
                    </form>

                    <p class="mt-6 text-center text-xs text-gray-500 dark:text-gray-400">
                        Novo por aqui?
                        <a href="{{ route('inicio', ['interesse' => 1]) }}" @click.prevent="cadastro = true"
                           class="text-brand-600 hover:text-brand-700 font-medium dark:text-brand-400 dark:hover:text-brand-300">
                            Solicite seu cadastro.
                        </a>
                    </p>
                </div>
            </div>

            {{-- Lateral da marca: a mesma grade viva da pagina inicial, com as
                 celulas acesas concentradas nos cantos inferior esquerdo e
                 superior direito, e o miolo escurecido para a logo e o medidor
                 respirarem. O medidor e o mesmo instrumento do carrossel, lendo
                 um nivel alto no degrade da marca.

                 O painel mede a si mesmo e fecha a grade em azulejos inteiros,
                 como o heroi da pagina inicial. --}}
            <div class="bg-brand-950 relative hidden h-full w-full items-center justify-center overflow-hidden lg:flex lg:w-3/5"
                 x-data="{
                     ajustar() {
                         const r = this.$el.getBoundingClientRect();
                         const cx = Math.max(1, Math.round(r.width / 42));
                         const cy = Math.max(1, Math.round(r.height / 42));
                         this.$el.style.setProperty('--passo-x', (r.width / cx) + 'px');
                         this.$el.style.setProperty('--passo-y', (r.height / cy) + 'px');
                     },
                 }" x-init="ajustar(); new ResizeObserver(() => ajustar()).observe($el)">

                {{-- A foto por baixo de tudo, com o veu da marca por cima:
                     e ela que da vida ao painel, mas quem manda na leitura
                     continua sendo o azul, a grade e a marca. --}}
                <img src="/images/entrar-fundo.png" alt="" draggable="false"
                     class="pointer-events-none absolute inset-0 h-full w-full object-cover" />
                <div aria-hidden="true" class="pointer-events-none absolute inset-0"
                     style="background: linear-gradient(to top right, rgb(0 0 0 / 0.96) 0%, rgb(0 0 0 / 0.7) 32%, rgb(0 0 0 / 0.5) 50%, rgb(0 0 0 / 0.7) 68%, rgb(0 0 0 / 0.96) 100%)"></div>

                {{-- A grade sobe para uma camada propria: como fundo do painel
                     ela ficaria escondida atras da foto. --}}
                {{-- A grade abre um vazio irregular no miolo: duas elipses
                     descentradas mascaram ~15 quadrados e devolvem a area para
                     a foto e a marca. Nos cantos, onde as celulas acendem, ela
                     segue inteira. --}}
                <div aria-hidden="true" class="grade-viva-escura pointer-events-none absolute inset-0"
                     style="mask-image: radial-gradient(ellipse 44% 40% at 47% 42%, transparent 58%, black 78%), radial-gradient(ellipse 30% 27% at 66% 60%, transparent 52%, black 74%); -webkit-mask-image: radial-gradient(ellipse 44% 40% at 47% 42%, transparent 58%, black 78%), radial-gradient(ellipse 30% 27% at 66% 60%, transparent 52%, black 74%)"></div>

                <div aria-hidden="true" class="pointer-events-none absolute inset-0">
                    {{-- Canto inferior esquerdo --}}
                    @foreach ([[0, 1, '0s'], [1, 0, '1.1s'], [2, 2, '2.3s'], [1, 3, '3.4s'], [3, 1, '4.3s']] as [$col, $lin, $atraso])
                        <span class="celula-viva"
                              style="left: calc(var(--passo-x, 42px) * {{ $col }} + 1px); bottom: calc(var(--passo-y, 42px) * {{ $lin }} + 1px); animation-delay: {{ $atraso }}"></span>
                    @endforeach
                    {{-- Canto superior direito --}}
                    @foreach ([[1, 1, '0.6s'], [0, 3, '1.7s'], [2, 0, '2.9s'], [3, 2, '3.9s'], [1, 4, '4.8s']] as [$col, $lin, $atraso])
                        <span class="celula-viva"
                              style="right: calc(var(--passo-x, 42px) * {{ $col }} + 1px); top: calc(var(--passo-y, 42px) * {{ $lin }} + 1px); animation-delay: {{ $atraso }}"></span>
                    @endforeach
                </div>

                <div class="z-1 flex max-w-xs flex-col items-center [filter:drop-shadow(0_2px_14px_rgb(0_0_0/0.65))]">
                    <x-avalia.medidor :tamanho="120" por-nivel class="mb-3" style="--nivel: 0.82" />
                    {{-- So o wordmark: o simbolo ja esta logo acima, em
                         tamanho grande, e repetir o gauge pequeno na logo
                         duplicava o desenho na mesma dobra. --}}
                    <span class="mb-4 text-4xl leading-none font-semibold tracking-tight text-white">
                        Avalia<span class="text-gray-400">one</span>
                    </span>
                    <p class="texto-bureau text-center font-medium">
                        Segurança para suas melhores escolhas.
                    </p>
                </div>
            </div>
        </div>

        {{-- Modal do pedido de cadastro: mesmo formulario da campanha, mesma
             fila no painel da administracao, origem propria. --}}
        <div x-cloak x-show="cadastro" x-transition.opacity.duration.300ms
             class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 p-4 backdrop-blur-sm"
             @click.self="cadastro = false" role="dialog" aria-modal="true" aria-label="Pedido de cadastro">
            <div class="relative w-full max-w-md">
                <button type="button" @click="cadastro = false" aria-label="Fechar"
                        class="absolute -top-10 right-0 z-10 text-white transition hover:scale-125">
                    <svg class="size-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18"/>
                    </svg>
                </button>

                <div class="entra-popup max-h-[92vh] w-full overflow-y-auto rounded-2xl border border-gray-200 bg-white p-7 shadow-theme-lg dark:border-gray-700 dark:bg-gray-800">
                    @if (session('interesse_ok'))
                        <div class="py-4 text-center">
                            <div class="mx-auto flex size-14 items-center justify-center rounded-full bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-500">
                                <svg class="size-7" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/>
                                </svg>
                            </div>
                            <h3 class="mt-4 text-xl font-semibold text-gray-800 dark:text-white/90">Pedido recebido</h3>
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                Obrigado pelo interesse. Um consultor entra em contato ainda hoje,
                                em horário comercial, com seus dados de acesso.
                            </p>
                            <x-avalia.botao variante="secundario" class="mt-6 w-full" type="button" @click="cadastro = false">
                                Fechar
                            </x-avalia.botao>
                        </div>
                    @else
                        <div>
                            <h3 class="text-xl font-semibold text-gray-800 dark:text-white/90">Solicite seu cadastro</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Deixe seu contato e um consultor retorna ainda hoje com seus dados de acesso.
                            </p>
                        </div>

                        <form method="POST" action="{{ route('interesse.salvar') }}" class="mt-5 space-y-4">
                            @csrf
                            <input type="hidden" name="origem" value="login">
                            <input type="text" name="site" value="" tabindex="-1" autocomplete="off"
                                   class="hidden" aria-hidden="true">

                            <div>
                                <label for="cad-nome" class="rotulo-campo">Seu nome</label>
                                <input id="cad-nome" name="nome" type="text" class="campo" required
                                       maxlength="120" value="{{ old('nome') }}" placeholder="Como podemos te chamar">
                                @error('nome') <span class="erro-campo">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label for="cad-empresa" class="rotulo-campo">Nome da empresa</label>
                                <input id="cad-empresa" name="empresa" type="text" class="campo" required
                                       maxlength="150" value="{{ old('empresa') }}">
                                @error('empresa') <span class="erro-campo">{{ $message }}</span> @enderror
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label for="cad-telefone" class="rotulo-campo">Telefone com DDD</label>
                                    <input id="cad-telefone" name="telefone" type="tel" class="campo" required
                                           inputmode="tel" maxlength="20" value="{{ old('telefone') }}" placeholder="(11) 99999-0000">
                                    @error('telefone') <span class="erro-campo">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label for="cad-funcionarios" class="rotulo-campo">Funcionários</label>
                                    <select id="cad-funcionarios" name="funcionarios" class="campo" required>
                                        <option value="" disabled @selected(! old('funcionarios'))>Escolha</option>
                                        @foreach (['Até 5', '6 a 20', '21 a 50', 'Mais de 50'] as $faixa)
                                            <option value="{{ $faixa }}" @selected(old('funcionarios') === $faixa)>{{ $faixa }}</option>
                                        @endforeach
                                    </select>
                                    @error('funcionarios') <span class="erro-campo">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            <div>
                                <label for="cad-email" class="rotulo-campo">E-mail</label>
                                <input id="cad-email" name="email" type="email" class="campo" required
                                       maxlength="150" value="{{ old('email') }}" placeholder="voce@empresa.com.br">
                                @if ($errors->has('email') && $errors->hasAny(['empresa', 'funcionarios']))
                                    <span class="erro-campo">{{ $errors->first('email') }}</span>
                                @endif
                            </div>

                            <x-avalia.botao class="w-full">
                                Enviar pedido de contato
                                <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5-5 5M18 12H6"/>
                                </svg>
                            </x-avalia.botao>

                            <p class="text-center text-xs text-gray-400 dark:text-gray-500">
                                Retornamos em horário comercial. Seus dados ficam só com a Avalia.
                            </p>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
