<?php

namespace App\Support;

/**
 * Canal de duvidas do cliente.
 *
 * A mensagem ja vai escrita com o assunto e, quando houver, uma referencia
 * curta: quem pergunta raramente sabe dizer de que tela veio, e essa ida e
 * volta e o que faz o atendimento demorar.
 *
 * O que NAO vai na mensagem, nunca: nome de pessoa, documento consultado,
 * resultado de credito ou valor. A URL passa por servidor de terceiro, fica no
 * historico do navegador e pode ser compartilhada por engano. Referencia aqui
 * significa numero de fatura ou protocolo de consulta, que sozinhos nao dizem
 * nada a quem nao tem acesso ao sistema.
 */
final class Suporte
{
    public static function telefone(): string
    {
        return (string) config('services.suporte.whatsapp', '');
    }

    /** Link de conversa com o assunto ja preenchido. */
    public static function whatsapp(?string $assunto = null, ?string $referencia = null): string
    {
        $numero = preg_replace('/\D/', '', self::telefone()) ?? '';

        $texto = trim(implode(' ', array_filter([
            'Olá, preciso de ajuda com a Avalia.',
            $assunto ? "Assunto: {$assunto}." : null,
            $referencia ? "Referência: {$referencia}." : null,
        ])));

        return 'https://wa.me/'.$numero.'?text='.rawurlencode($texto);
    }
}
