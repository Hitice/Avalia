<?php

use App\Support\Destino;

/**
 * Para onde a plaquinha aponta.
 *
 * E o unico campo do sistema que manda um desconhecido para fora do dominio
 * sem clique intermediario. O que estes testes guardam e o que aconteceria se
 * ele aceitasse qualquer texto.
 */
it('completa o https que ninguem digita', function () {
    // Sem esquema, o navegador leria `padaria.com.br` como caminho relativo e
    // mandaria o fregues para uma pagina nossa que nao existe.
    expect(Destino::normalizar('padaria.com.br'))->toBe('https://padaria.com.br')
        ->and(Destino::normalizar('  padaria.com.br  '))->toBe('https://padaria.com.br')
        ->and(Destino::normalizar('http://padaria.com.br'))->toBe('http://padaria.com.br')
        ->and(Destino::normalizar('https://wa.me/5531999999999'))->toBe('https://wa.me/5531999999999');
});

it('recusa esquema que o navegador executa', function () {
    // O navegador do fregues rodaria o que estivesse escrito ali, com a
    // plaquinha de um cliente nosso como porta de entrada.
    expect(Destino::valido('javascript:alert(1)'))->toBeFalse()
        ->and(Destino::valido('data:text/html;base64,PHNjcmlwdD4='))->toBeFalse()
        ->and(Destino::valido('file:///etc/passwd'))->toBeFalse();
});

it('recusa endereco sem dominio', function () {
    expect(Destino::valido('https://localhost'))->toBeFalse()
        ->and(Destino::valido(''))->toBeFalse()
        ->and(Destino::valido(null))->toBeFalse();
});

it('recusa plaquinha apontando para plaquinha', function () {
    // Laco: o navegador roda ate desistir e o fregues nao chega a lugar nenhum.
    config(['app.url' => 'https://avaliaone.com.br']);

    expect(Destino::valido('https://avaliaone.com.br/q/K7M2PX'))->toBeFalse()
        ->and(Destino::valido('https://avaliaone.com.br/Q/K7M2PX'))->toBeFalse()
        // O resto do nosso dominio continua valendo: apontar para a pagina de
        // contato da casa e legitimo.
        ->and(Destino::valido('https://avaliaone.com.br/contato'))->toBeTrue();
});

it('aceita o que o lojista realmente usa', function () {
    foreach ([
        'https://wa.me/5531999999999?text=Ol%C3%A1',
        'https://instagram.com/padariadoze',
        'https://g.page/r/CQabc123/review',
        'https://padariadoze.com.br/cardapio.pdf',
    ] as $endereco) {
        expect(Destino::valido($endereco))->toBeTrue("recusou {$endereco}");
    }
});

it('diz o que corrigir, e nao so que esta errado', function () {
    // "Endereço inválido" faz a pessoa tentar a mesma coisa de novo.
    expect(Destino::problema('javascript:alert(1)'))->toContain('http://')
        ->and(Destino::problema(''))->toContain('Informe')
        ->and(Destino::problema('https://exemplo.com.br'))->toBeNull();
});
