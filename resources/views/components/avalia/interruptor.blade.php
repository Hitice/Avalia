@props([
    'ligado' => false,
    'acao',
    'titulo' => null,
    'ligadoRotulo' => 'Ativo',
    'desligadoRotulo' => 'Pausado',
    'form' => null,
])

{{-- Chave liga/desliga que grava no clique, sem abrir formulario.

     Verde ligado, vermelho desligado. O checkbox some atras do trilho e o
     proprio change envia: uma acao de um clique nao merece uma tela.

     `form` existe para a tabela que ja vive dentro de um formulario (a selecao
     em lote): form dentro de form o navegador descarta, e os botoes de dentro
     passam a enviar o de fora. Com `form`, o <form> e declarado fora da tabela e
     a chave se liga a ele pelo id. --}}

@php
    $trilho = 'interruptor '.($ligado ? 'interruptor-ligado' : 'interruptor-desligado');
@endphp

@if ($form)
    <label class="{{ $trilho }}" @if ($titulo) title="{{ $titulo }}" @endif>
        <input type="checkbox" class="sr-only" form="{{ $form }}" @checked($ligado)
               onchange="document.getElementById('{{ $form }}').submit()">
        <span class="interruptor-bolinha {{ $ligado ? 'translate-x-[1.375rem]' : 'translate-x-0.5' }}"></span>
        <span class="sr-only">{{ $ligado ? $ligadoRotulo : $desligadoRotulo }}</span>
    </label>
@else
    <form method="POST" action="{{ $acao }}" class="inline-flex">
        @csrf
        @method('PATCH')

        <label class="{{ $trilho }}" @if ($titulo) title="{{ $titulo }}" @endif>
            <input type="checkbox" class="sr-only" @checked($ligado)
                   onchange="this.form.submit()">
            <span class="interruptor-bolinha {{ $ligado ? 'translate-x-[1.375rem]' : 'translate-x-0.5' }}"></span>
            <span class="sr-only">{{ $ligado ? $ligadoRotulo : $desligadoRotulo }}</span>
        </label>
    </form>
@endif
