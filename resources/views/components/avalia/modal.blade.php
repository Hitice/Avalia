@props([
    'aberto',
    'fechar',
    'largura' => 'max-w-md',
    'rotulo' => null,
    // O aviso legal nao tem X: so o botao que diz "entendi".
    'semFechar' => false,
    // Sem recheio quando o conteudo traz o proprio cabecalho (a grade viva do Gestor).
    'recheio' => true,
])

{{-- O modal da casa. No celular e uma folha que sobe do pe da tela, de
     largura inteira, com a altura limitada pelo viewport de verdade (dvh) e
     o pe respeitando a barra do sistema; no desktop, caixa centrada. Esc,
     clique fora e o X fecham; a pagina atras para de rolar. --}}
<div x-cloak x-show="{{ $aberto }}" x-transition.opacity.duration.200ms
     x-effect="document.documentElement.classList.toggle('overflow-hidden', !! ({{ $aberto }}))"
     @keydown.escape.window="{{ $fechar }}" @click.self="{{ $fechar }}"
     class="modal-fundo" role="dialog" aria-modal="true" @if ($rotulo) aria-label="{{ $rotulo }}" @endif>
    <div x-show="{{ $aberto }}" {{ $attributes->class(['modal entra-popup '.$largura, 'modal-recheio' => $recheio]) }}>
        @unless ($semFechar)
            <button type="button" @click="{{ $fechar }}" aria-label="Fechar" class="modal-fechar">
                <x-avalia.icone nome="fechar" class="size-5" />
            </button>
        @endunless

        {{ $slot }}
    </div>
</div>
