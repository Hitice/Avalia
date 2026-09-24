<?php

use App\Support\CodigoCurto;

/**
 * O codigo impresso na plaquinha.
 *
 * O que estes testes guardam e o que nao tem conserto depois de a placa sair
 * da oficina: codigo que sai em sequencia, letra que se confunde com digito no
 * acrilico, e leitura que recusa o que o cliente ditou por telefone.
 */
it('sorteia no alfabeto combinado, no tamanho combinado', function () {
    foreach (range(1, 50) as $ignorado) {
        $codigo = CodigoCurto::sortear();

        expect(strlen($codigo))->toBe(6)
            ->and(CodigoCurto::valido($codigo))->toBeTrue();
    }
});

it('nunca sorteia letra que se confunde com digito na placa', function () {
    // I e L viram 1, O vira 0, e U sai fora para nao formar palavra feia por
    // acidente. Sorteada uma delas, o cliente dita um codigo e chega noutro.
    $saida = implode('', array_map(fn () => CodigoCurto::sortear(), range(1, 400)));

    expect($saida)->not->toContain('I')
        ->and($saida)->not->toContain('L')
        ->and($saida)->not->toContain('O')
        ->and($saida)->not->toContain('U');
});

it('usa o alfabeto inteiro, e nao 31 dos 32', function () {
    // Um erro de um no limite do sorteio tiraria o ultimo caractere de
    // circulacao em silencio: os codigos continuariam saindo, so que nenhum
    // deles teria Z. Nada quebraria, e ninguem descobriria.
    $saida = implode('', array_map(fn () => CodigoCurto::sortear(), range(1, 500)));

    foreach (str_split(CodigoCurto::ALFABETO) as $caractere) {
        expect($saida)->toContain($caractere);
    }
});

it('nao repete codigo em sorteio seguido', function () {
    $sorteados = array_map(fn () => CodigoCurto::sortear(), range(1, 200));

    expect(array_unique($sorteados))->toHaveCount(200);
});

/*
|--------------------------------------------------------------------------
| Leitura do que a pessoa digitou
|--------------------------------------------------------------------------
*/

it('aceita o codigo do jeito que o cliente dita', function () {
    // Minuscula, espaco e hifen: e assim que o codigo chega pelo WhatsApp e
    // pelo telefone, e exigir o formato exato so gera chamado de suporte.
    expect(CodigoCurto::normalizar('k7m2px'))->toBe('K7M2PX')
        ->and(CodigoCurto::normalizar('  K7M2PX '))->toBe('K7M2PX')
        ->and(CodigoCurto::normalizar('K7M-2PX'))->toBe('K7M2PX')
        ->and(CodigoCurto::normalizar('K7M 2PX'))->toBe('K7M2PX');
});

it('conserta as letras que ninguem sabe que nao existem', function () {
    // Quem le "1" impresso no acrilico digita "I" ou "l", e quem le "0" digita
    // "O". Sao os mesmos caracteres que o alfabeto exclui justamente por isso.
    expect(CodigoCurto::normalizar('K7M2PI'))->toBe('K7M2P1')
        ->and(CodigoCurto::normalizar('K7M2PL'))->toBe('K7M2P1')
        ->and(CodigoCurto::normalizar('K7M2PO'))->toBe('K7M2P0');
});

it('devolve vazio em vez de chutar um codigo parecido', function () {
    // Melhor a pagina de nao encontrado do que mandar a freguesia de um
    // cliente para a loja de outro.
    expect(CodigoCurto::normalizar('K7M2P'))->toBe('')
        ->and(CodigoCurto::normalizar('K7M2PXY'))->toBe('')
        ->and(CodigoCurto::normalizar('K7M2PU'))->toBe('')
        ->and(CodigoCurto::normalizar(''))->toBe('')
        ->and(CodigoCurto::normalizar(null))->toBe('');
});

it('reconhece codigo valido e recusa o resto', function () {
    expect(CodigoCurto::valido('K7M2PX'))->toBeTrue()
        ->and(CodigoCurto::valido('000000'))->toBeTrue()
        ->and(CodigoCurto::valido('ZZZZZZ'))->toBeTrue()
        ->and(CodigoCurto::valido('k7m2px'))->toBeFalse()
        ->and(CodigoCurto::valido('K7M2PI'))->toBeFalse()
        ->and(CodigoCurto::valido('K7M2P'))->toBeFalse()
        ->and(CodigoCurto::valido(null))->toBeFalse();
});
