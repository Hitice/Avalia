@props(['assunto' => null, 'referencia' => null])

@php
    use App\Support\Suporte;

    $link = Suporte::whatsapp($assunto, $referencia);
@endphp

{{-- Canal de duvidas. Abre conversa com o assunto ja escrito, porque quem
     pergunta raramente sabe dizer de que tela veio, e essa ida e volta e o
     que faz o atendimento demorar.

     Nada de dado pessoal nem de resultado de credito vai na mensagem: a URL
     passa por servidor de terceiro e fica no historico do navegador. --}}

<a href="{{ $link }}" target="_blank" rel="noopener noreferrer"
   {{ $attributes->merge(['class' => 'hover:text-brand-500 dark:hover:text-brand-400 inline-flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400']) }}>
    <svg class="size-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path d="M12.04 2c-5.46 0-9.9 4.44-9.9 9.9 0 1.75.46 3.45 1.32 4.95L2 22l5.3-1.39a9.86 9.86 0 0 0 4.74 1.21h.01c5.46 0 9.9-4.44 9.9-9.9 0-2.64-1.03-5.13-2.9-7A9.82 9.82 0 0 0 12.04 2Zm0 18.06h-.01a8.2 8.2 0 0 1-4.18-1.15l-.3-.18-3.11.82.83-3.03-.2-.31a8.19 8.19 0 0 1-1.26-4.37c0-4.54 3.7-8.23 8.24-8.23 2.2 0 4.27.86 5.82 2.42a8.18 8.18 0 0 1 2.41 5.82c0 4.54-3.7 8.23-8.24 8.23Zm4.52-6.16c-.25-.12-1.47-.72-1.69-.81-.23-.08-.39-.12-.56.13-.16.24-.64.8-.79.97-.14.16-.29.18-.54.06-.25-.13-1.05-.39-1.99-1.23-.74-.66-1.23-1.47-1.38-1.72-.14-.25-.01-.38.11-.5.11-.11.25-.29.37-.43.13-.15.17-.25.25-.41.09-.17.04-.31-.02-.43-.06-.13-.56-1.35-.77-1.84-.2-.48-.4-.42-.56-.43h-.47c-.17 0-.43.06-.66.31-.22.25-.86.85-.86 2.07 0 1.22.89 2.4 1.01 2.56.12.17 1.75 2.67 4.23 3.74.59.26 1.05.41 1.41.52.59.19 1.13.16 1.56.1.48-.07 1.47-.6 1.68-1.18.2-.58.2-1.08.14-1.18-.06-.11-.22-.17-.47-.29Z" />
    </svg>
    {{ $slot->isEmpty() ? 'Falar com a Avalia' : $slot }}
</a>
