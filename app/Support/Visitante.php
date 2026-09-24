<?php

namespace App\Support;

/**
 * Quem esta do outro lado de uma leitura de plaquinha.
 *
 * Serve a uma pergunta so: esta leitura conta? WhatsApp, Telegram, Instagram e
 * Facebook buscam toda URL que passa por eles para montar a previa do link, e
 * buscadores varrem o que acham. Sem filtro, o cliente abre o painel e ve 40
 * leituras numa placa que ninguem olhou, e a partir dai o numero nao vale nada.
 *
 * A lista e explicita, e nao um `contains('bot')`. "Bot" como pedaco de texto
 * casa com CUBOT, que e marca de celular Android: o filtro passaria a descartar
 * em silencio as leituras de quem tem um, e ninguem descobriria. Robo
 * desconhecido que escapa infla um pouco a conta; usuario real descartado
 * apaga uma leitura de verdade, e esse erro e pior.
 */
final class Visitante
{
    private const ROBOS = '/(whatsapp|telegram|facebookexternalhit|facebot|twitterbot|slackbot'
        .'|discordbot|linkedinbot|pinterest|redditbot|skypeuripreview|embedly|vkshare|preview'
        .'|googlebot|bingbot|applebot|duckduckbot|yandexbot|baiduspider|petalbot|ahrefsbot'
        .'|semrushbot|mj12bot|dotbot|crawler|spider|curl|wget|python-requests|okhttp'
        .'|go-http-client|headlesschrome)/i';

    /**
     * Agente vazio conta como robo.
     *
     * Navegador sempre se identifica. Sem identificacao nenhuma, do outro lado
     * ha um script, e script nao e freguesia passando na frente da loja.
     */
    public static function ehRobo(?string $agente): bool
    {
        $agente = trim((string) $agente);

        return $agente === '' || preg_match(self::ROBOS, $agente) === 1;
    }
}
