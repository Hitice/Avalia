{{-- Mensagens de resultado das acoes de catalogo. --}}

@if (session('ok'))
    <div class="aviso aviso-ok mb-6">{{ session('ok') }}</div>
@endif

@if (session('erro'))
    <div class="aviso aviso-erro mb-6">{{ session('erro') }}</div>
@endif
