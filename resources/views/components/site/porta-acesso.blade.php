{{--
    A porta da administracao, aberta no proprio cartao.

    Popup e nao pagina: o cartao promete uma ferramenta, e mandar quem clicou
    para uma tela de login em outro endereco faz parecer que ele errou o
    caminho. Aqui ele digita, entra e cai direto na ferramenta.

    O formulario e o MESMO da porta principal: mesma rota, mesma validacao,
    mesmo teto por tentativa, mesma mensagem unica para senha errada e conta
    inexistente. Um segundo caminho de login com regra propria seria um segundo
    caminho para revisar toda vez que a regra mudasse.

    O destino vai por apelido, nunca por endereco: campo que aceita URL vira
    redirecionamento aberto. Ver LoginController::DESTINOS.
--}}

@if (App\Support\Dono::tipo() === null)
<div x-data="{ aberta: false, destino: '' }"
     x-on:abrir-porta.window="aberta = true; destino = $event.detail.destino; $nextTick(() => $refs.email?.focus())"
     x-on:keydown.escape.window="aberta = false">

    <div x-show="aberta" x-cloak x-transition.opacity
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
         x-on:click.self="aberta = false" role="dialog" aria-modal="true" aria-labelledby="porta-titulo">

        <div x-show="aberta" x-cloak x-transition
             class="w-full max-w-sm rounded-2xl border border-gray-200 bg-white p-7 shadow-theme-lg">

            <h2 id="porta-titulo" class="text-lg font-semibold tracking-tight text-gray-900">Acesso da administração</h2>
            <p class="mt-2 text-sm leading-relaxed text-gray-600">
                Esta ferramenta é interna. Entre para criar e gerenciar os códigos.
            </p>

            <form method="POST" action="{{ route('entrar.enviar') }}" class="mt-6 grid gap-4">
                @csrf
                <input type="hidden" name="destino" x-bind:value="destino">

                <div>
                    <label for="porta-email" class="rotulo-campo">E-mail</label>
                    <input id="porta-email" name="email" type="email" autocomplete="username"
                           x-ref="email" required class="campo">
                </div>

                <div>
                    <label for="porta-senha" class="rotulo-campo">Senha</label>
                    <x-avalia.senha id="porta-senha" name="senha" autocomplete="current-password" required />
                </div>

                {{-- O erro volta pela sessao, como no resto do sistema. Sem
                     isso, senha errada devolveria a pessoa a uma pagina
                     silenciosa e ela tentaria de novo sem saber o que houve. --}}
                @error('email')
                    <p class="aviso aviso-erro">{{ $message }}</p>
                @enderror

                <div class="mt-2 flex items-center justify-between gap-3">
                    <x-avalia.botao>Entrar</x-avalia.botao>
                    <button type="button" x-on:click="aberta = false" class="text-sm text-gray-500 hover:text-gray-700">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
