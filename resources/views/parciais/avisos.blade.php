{{-- O resultado da ultima acao, em qualquer tela de qualquer produto.

     Saiu de paginas/catalogo/ porque nao e do catalogo: 27 telas o incluem, e
     partial generico dentro da pasta de um produto faz o Avalia Sales
     depender do Avalia One para mostrar "salvo". --}}

@if (session('ok'))
    <div class="aviso aviso-ok mb-6">{{ session('ok') }}</div>
@endif

@if (session('erro'))
    <div class="aviso aviso-erro mb-6">{{ session('erro') }}</div>
@endif
