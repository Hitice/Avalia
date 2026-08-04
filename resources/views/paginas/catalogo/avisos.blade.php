{{-- Mensagens de resultado das acoes de catalogo. --}}

@if (session('ok'))
    <div class="border-success-300 bg-success-50 text-success-700 dark:border-success-500/30 dark:bg-success-500/10 dark:text-success-400 mb-6 rounded-lg border p-4 text-sm">
        {{ session('ok') }}
    </div>
@endif

@if (session('erro'))
    <div class="border-error-300 bg-error-50 text-error-700 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400 mb-6 rounded-lg border p-4 text-sm">
        {{ session('erro') }}
    </div>
@endif
