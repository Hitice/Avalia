<?php

namespace App\Support;

/**
 * O codigo que vai impresso na plaquinha.
 *
 * Seis caracteres do alfabeto Crockford base32, sorteados. Cada escolha aqui
 * responde a um problema de mundo fisico, e nenhuma delas e estetica.
 *
 * SORTEADO, e nao sequencial. Codigo em sequencia deixa qualquer um varrer a
 * base inteira somando um: quem comprou, para onde aponta, e quantas placas
 * existem no estoque ainda sem destino.
 *
 * SEIS. O tamanho do QR e ditado pelo dominio, nao pelo codigo:
 * `HTTPS://AVALIAONE.COM.BR/Q/` ja gasta 27 dos 35 caracteres que a versao 3
 * do QR aguenta no nivel de correcao H. Sobram oito, e seis deixam margem.
 * Quatro digitos cairiam no mesmo QR, do mesmo tamanho, e com um bilhao de
 * codigos a menos.
 *
 * CROCKFORD. O alfabeto exclui `I`, `L`, `O` e `U`: os tres primeiros se
 * confundem com `1` e `0` em placa gravada, e o quarto sai de fora para nao
 * formar palavra feia por acidente numa plaquinha de balcao. Na leitura de
 * volta, `I` e `L` viram `1` e `O` vira `0`, porque quem dita o codigo por
 * telefone nao sabe dessa distincao.
 *
 * MAIUSCULO. O QR tem um modo alfanumerico que so aceita maiuscula e digito, e
 * ele e bem mais compacto que o modo byte. Dominio nao liga para caixa; o
 * caminho liga, e e por isso que a busca normaliza antes de procurar.
 */
final class CodigoCurto
{
    /** Crockford base32: sem I, L, O e U. */
    public const ALFABETO = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    public const TAMANHO = 6;

    /**
     * O que a rota aceita antes de normalizar.
     *
     * Larga de proposito: quem digita o codigo a mao erra `1` por `I` e `0`
     * por `O`, e a rota precisa deixar entrar para a normalizacao poder
     * consertar. Apertar o filtro aqui devolveria 404 a quem so trocou uma
     * letra parecida.
     */
    public const REGEX_ROTA = '[0-9A-Za-z]{6}';

    /** O que a leitura errada vira. */
    private const CONFUSOES = ['I' => '1', 'L' => '1', 'O' => '0'];

    /**
     * Um codigo novo, sorteado.
     *
     * `random_int` e nao `rand`: o gerador comum e previsivel a partir de
     * poucas amostras, e uma sequencia de codigos previsivel devolve
     * exatamente o problema que o sorteio existe para evitar.
     *
     * Nao consulta banco: repeticao e problema de quem grava, e o indice unico
     * da tabela e quem decide. Support nao toca banco.
     */
    public static function sortear(): string
    {
        $ultimo = strlen(self::ALFABETO) - 1;
        $codigo = '';

        for ($i = 0; $i < self::TAMANHO; $i++) {
            $codigo .= self::ALFABETO[random_int(0, $ultimo)];
        }

        return $codigo;
    }

    /**
     * O codigo como ele esta guardado, a partir do que a pessoa digitou.
     *
     * Aceita minuscula, espaco, hifen e as letras parecidas. Devolve string
     * vazia quando nao sobra um codigo valido, e nao um palpite: melhor a
     * pagina de "nao encontrado" do que redirecionar para a loja errada.
     */
    public static function normalizar(?string $entrada): string
    {
        $limpo = strtr(
            preg_replace('/[^0-9A-Z]/', '', mb_strtoupper(trim((string) $entrada))) ?? '',
            self::CONFUSOES,
        );

        return self::valido($limpo) ? $limpo : '';
    }

    public static function valido(?string $codigo): bool
    {
        return is_string($codigo)
            && strlen($codigo) === self::TAMANHO
            && strspn($codigo, self::ALFABETO) === self::TAMANHO;
    }

    /**
     * O endereco publico da plaquinha.
     *
     * Este e o contrato mais duradouro do sistema: plaquinha impressa hoje
     * precisa abrir em 2036. Mudar o prefixo aqui mata tudo o que ja esta em
     * balcao de loja.
     */
    public static function url(string $codigo): string
    {
        return route('q', ['codigo' => $codigo]);
    }

    /**
     * O mesmo endereco, do jeito que entra no QR.
     *
     * Maiusculo pelo modo alfanumerico (veja a nota da classe). Nao serve para
     * link em e-mail nem para a tag NFC: la o ganho nao existe, e a URI da tag
     * ainda encolhe sozinha quando o `https://` vem em minuscula.
     */
    public static function urlParaQr(string $codigo): string
    {
        return mb_strtoupper(self::url($codigo));
    }
}
