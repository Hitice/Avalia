@props([
    'ligado' => false,
    'acao',
    'titulo' => null,
])

{{-- Chave liga/desliga que grava no clique, sem abrir formulario.

     Verde ligado, vermelho desligado. O checkbox some atras do trilho e o
     proprio change envia: uma acao de um clique nao merece uma tela. --}}

<form method="POST" action="{{ $acao }}" class="inline-flex">
    @csrf
    @method('PATCH')

    <label class="interruptor {{ $ligado ? 'interruptor-ligado' : 'interruptor-desligado' }}"
           @if ($titulo) title="{{ $titulo }}" @endif>
        <input type="checkbox" class="sr-only" @checked($ligado)
               onchange="this.form.submit()">
        <span class="interruptor-bolinha {{ $ligado ? 'translate-x-[1.375rem]' : 'translate-x-0.5' }}"></span>
        <span class="sr-only">{{ $ligado ? 'Ativo' : 'Pausado' }}</span>
    </label>
</form>
