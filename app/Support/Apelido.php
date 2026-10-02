<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;

/**
 * O apelido de um link, quando ele mora na raiz do dominio.
 *
 * `avaliaone.com.br/MarthaNegocios` e mais legivel e mais vendavel que
 * `avaliaone.com.br/l/K7M2PX`, mas mora no mesmo espaco de nomes de TODAS as
 * paginas do site e de toda tela do sistema. Um apelido chamado `contato`
 * nunca abriria, porque a rota de contato ganha; um chamado `painel` seria
 * pior ainda, porque o dia em que alguem renomeasse uma tela, o link de um
 * cliente pararia de funcionar sem ninguem tocar nele.
 *
 * Por isso a lista de palavras proibidas nao e escrita a mao: ela e lida da
 * TABELA DE ROTAS, no momento da conferencia. Rota nova nasce protegida, e o
 * teste que varre as rotas cobra o contrario, que nenhum apelido ja gravado
 * seja engolido por uma rota nova.
 */
final class Apelido
{
    public const TAMANHO_MINIMO = 3;

    public const TAMANHO_MAXIMO = 40;

    /**
     * Palavras que nenhuma rota usa hoje, e que mesmo assim ficam de fora.
     *
     * Sao os enderecos que um navegador, um buscador ou um provedor de e-mail
     * procuram sozinhos, e os que a casa vai querer um dia. Apelido aqui
     * quebraria algo que ninguem pediu.
     */
    private const GUARDADAS = [
        'admin', 'api', 'app', 'assets', 'avalia', 'blog', 'build', 'cdn', 'css',
        'email', 'favicon', 'fonts', 'ftp', 'images', 'img', 'js', 'mail', 'marca',
        'media',
        'robots', 'sitemap', 'ssl', 'static', 'storage', 'suporte', 'up', 'vendor',
        'webmail', 'www',
    ];

    public static function normalizar(?string $entrada): string
    {
        return trim((string) $entrada, " \t\n\r\0\x0B/");
    }

    /**
     * O motivo de o apelido nao servir, ou null quando serve.
     *
     * Devolve o motivo, e nao um booleano, porque quem escolheu o apelido
     * precisa saber qual das regras pegou: "invalido" faz a pessoa tentar a
     * mesma coisa de novo.
     */
    public static function problema(?string $entrada): ?string
    {
        $apelido = self::normalizar($entrada);

        if ($apelido === '') {
            return 'Informe o apelido do link.';
        }

        if (mb_strlen($apelido) < self::TAMANHO_MINIMO || mb_strlen($apelido) > self::TAMANHO_MAXIMO) {
            return 'O apelido precisa ter de '.self::TAMANHO_MINIMO.' a '.self::TAMANHO_MAXIMO.' caracteres.';
        }

        // Letra, numero e hifen. Acento, espaco e ponto viram outra coisa ao
        // passar por WhatsApp e por impressao, e o link deixa de abrir.
        if (preg_match('/^[A-Za-z0-9-]+$/', $apelido) !== 1) {
            return 'Use apenas letras, números e hífen. Sem espaço, acento ou ponto.';
        }

        if (self::reservado($apelido)) {
            return 'Este apelido já é um endereço do site. Escolha outro.';
        }

        return null;
    }

    public static function valido(?string $entrada): bool
    {
        return self::problema($entrada) === null;
    }

    /** Se o apelido colide com um endereco que a aplicacao ja atende. */
    public static function reservado(string $apelido): bool
    {
        return in_array(mb_strtolower($apelido), self::proibidas(), true);
    }

    /**
     * Os primeiros segmentos de toda rota registrada, mais as guardadas.
     *
     * Le a tabela de rotas de verdade: uma lista escrita a mao ficaria velha
     * na primeira tela nova, e o sintoma seria o link de um cliente parando de
     * abrir sem ninguem ter mexido nele.
     *
     * @return list<string>
     */
    public static function proibidas(): array
    {
        $daRota = collect(Route::getRoutes())
            ->map(fn ($rota) => strtok(trim($rota->uri(), '/'), '/'))
            ->filter(fn (?string $segmento) => filled($segmento) && ! str_contains($segmento, '{'))
            ->map(fn (string $segmento) => mb_strtolower($segmento));

        return $daRota->merge(self::GUARDADAS)->unique()->values()->all();
    }
}
