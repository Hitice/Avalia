@props([
    // A chave do produto: credito, cobranca ou vendas. Decide a marca, a entrada
    // da sessao atual e o destino depois do login.
    'produto',
    'titulo',
    // Para onde o "Conhecer" leva: a pagina informativa do produto.
    'conhecer',
    // O apelido do destino depois do login, quando o produto tem ferramenta
    // propria. Vazio manda a pessoa para o painel do proprio guard dela.
    'porta' => '',
    'atraso' => null,
])

{{--
    Um produto da casa na vitrine.

    DOIS botoes, e nao um link no cartao inteiro: "Conhecer" para quem esta
    decidindo e "Entrar" para quem ja e cliente. Com o cartao inteiro clicavel
    nao havia como oferecer os dois, e quem ja usava o sistema passava pela
    pagina de venda toda vez para chegar ao painel.

    Por isso o cartao e uma div e nao uma ancora: botao dentro de link e elemento
    interativo aninhado, que o teclado e o leitor de tela nao sabem percorrer.

    O "Entrar" leva a area DESTE produto, e nao ao painel generico: com a sessao
    aberta os tres cartoes mandavam para o Avalia One. Quando a sessao nao tem
    acesso ao produto, o botao nao aparece, porque botao que leva a 403 ensina a
    ignorar o botao.
--}}

@php
    $entrada = App\Support\Porta::entradaDe($produto);
    $temSessao = App\Support\Porta::painelDaSessao() !== null;
@endphp

<div {{ $attributes->class(['bloco']) }} @if ($atraso) style="--atraso: {{ $atraso }}" @endif data-revelar>
    <div class="flex items-center justify-between">
        <x-avalia.logotipo :tamanho="34" texto="1.2rem" :marca="$produto" />
        <span class="etiqueta etiqueta-sucesso">Em operação</span>
    </div>

    <h3 class="text-xl font-semibold text-gray-900">{{ $titulo }}</h3>

    <p class="text-gray-600">{{ $slot }}</p>

    <div class="mt-auto flex flex-wrap items-center gap-2">
        <a href="{{ $conhecer }}" class="botao botao-secundario botao-sm">Conhecer</a>

        @if ($entrada)
            <a href="{{ $entrada }}" class="botao botao-primario botao-sm">Entrar</a>
        @elseif (! $temSessao)
            <button type="button" class="botao botao-primario botao-sm"
                    x-on:click="$dispatch('abrir-porta', { destino: '{{ $porta }}' })">Entrar</button>
        @endif
    </div>
</div>