@props(['tamanho' => 34])

{{-- Icone: arco de velocimetro com ponteiro, remetendo a score de credito. --}}
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2.5']) }}>
    <svg width="{{ $tamanho }}" height="{{ $tamanho }}" viewBox="0 0 32 32" role="img" aria-label="Avalia">
        <rect width="32" height="32" rx="7" fill="#12B76A" />
        <path d="M7.5 19.2a8.5 8.5 0 0 1 17 0" fill="none" stroke="#04180F" stroke-width="3" stroke-linecap="round" />
        <path d="M16 19.2 21 13" stroke="#04180F" stroke-width="3" stroke-linecap="round" />
        <circle cx="16" cy="19.2" r="2.2" fill="#04180F" />
    </svg>

    <span class="text-xl font-semibold tracking-tight text-gray-800 dark:text-white/90">
        Aval<span class="relative">ı<span class="absolute -top-0.5 left-1/2 size-1.5 -translate-x-1/2 rounded-full bg-[#EC4899]"></span></span>a
    </span>
</span>
