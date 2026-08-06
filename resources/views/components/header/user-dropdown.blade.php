@php
    // Resolve a conta sem saber de antemao qual guard abriu a sessao.
    $conta = auth('staff')->user() ?? auth('empresa')->user();
    $ehStaff = auth('staff')->check();
    $nome = $conta?->nome ?? $conta?->razao_social ?? 'Conta';
    $iniciais = mb_strtoupper(mb_substr(trim($nome), 0, 1));
@endphp

<div class="relative" x-data="{ aberto: false }" @click.away="aberto = false">
    <button type="button" @click.prevent="aberto = !aberto"
        class="flex items-center text-gray-700 dark:text-gray-400">
        <span class="mr-3 flex size-11 items-center justify-center rounded-full bg-brand-50 text-sm font-semibold text-brand-600 dark:bg-brand-500/15 dark:text-brand-400">
            {{ $iniciais }}
        </span>
        <span class="text-theme-sm mr-1 block max-w-[160px] truncate font-medium">{{ $nome }}</span>
        <svg class="size-5 transition-transform duration-200" :class="{ 'rotate-180': aberto }"
            fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
    </button>

    <div x-show="aberto" x-cloak
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="transform opacity-0 scale-95"
        x-transition:enter-end="transform opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="transform opacity-100 scale-100"
        x-transition:leave-end="transform opacity-0 scale-95"
        class="shadow-theme-lg dark:bg-gray-dark absolute right-0 z-50 mt-[17px] flex w-[280px] flex-col rounded-2xl border border-gray-200 bg-white p-3 dark:border-gray-800">

        <div class="pb-3">
            <span class="text-theme-sm block font-medium text-gray-700 dark:text-gray-400">{{ $nome }}</span>
            <span class="text-theme-xs mt-0.5 block text-gray-500 dark:text-gray-400">{{ $conta?->email }}</span>

            <span class="mt-2 inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600 dark:bg-white/5 dark:text-gray-400">
                @if ($ehStaff)
                    {{ $conta->ehAdmin() ? 'Administrador' : 'Vendedor' }}
                    @if ($conta->ehSuper())
                        <span class="text-brand-500" title="Este acesso ignora as permissões">&bull; acesso total</span>
                    @endif
                @else
                    Empresa contratante
                @endif
            </span>
        </div>

        {{-- Sair e POST com CSRF, nunca link. Logout por GET pode ser disparado
             por uma imagem numa pagina de terceiro e derrubar a sessao sozinho. --}}
        <form method="POST" action="{{ route('sair') }}" class="border-t border-gray-200 pt-3 dark:border-gray-800">
            @csrf
            <button type="submit"
                class="text-theme-sm group flex w-full items-center gap-3 rounded-lg px-3 py-2 font-medium text-gray-700 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300">
                <span class="text-gray-500 group-hover:text-gray-700 dark:group-hover:text-gray-300">
                    <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                </span>
                Sair
            </button>
        </form>
    </div>
</div>
