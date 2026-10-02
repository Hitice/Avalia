<?php

/*
|--------------------------------------------------------------------------
| A marca no QR e no favicon
|--------------------------------------------------------------------------
|
| O QR e desenhado em JS e nao ha runner JS: este teste le os arquivos. O que
| ele guarda e a decisao de 02/10/2026: marca no azul da casa, e nao em
| laranja, nos dois lugares, e o fundo do QR transparente.
*/

$raiz = dirname(__DIR__, 2);

function azulDaMarca(string $raiz): string
{
    preg_match('/--color-brand-500:\s*(#[0-9a-fA-F]{6})/', (string) file_get_contents($raiz.'/resources/css/app.css'), $m);

    return strtolower($m[1] ?? '');
}

it('usa o azul da marca, e nao laranja, no favicon e no miolo do QR', function () use ($raiz) {
    $azul = azulDaMarca($raiz);
    $favicon = strtolower((string) file_get_contents($raiz.'/public/favicon.svg'));
    $qr = strtolower((string) file_get_contents($raiz.'/resources/js/qr.js'));

    expect($azul)->toMatch('/^#[0-9a-f]{6}$/')
        ->and($favicon)->toContain($azul)
        ->and($favicon)->not->toContain('#fb6514')
        ->and($qr)->toContain($azul)
        ->and($qr)->not->toContain('#fb6514');
});

it('deixa o fundo do QR transparente, mostrando a cor da peca', function () use ($raiz) {
    $qr = (string) file_get_contents($raiz.'/resources/js/qr.js');

    expect($qr)->toContain('id="fundo"')
        ->and($qr)->toMatch('/id="fundo"[^>]*fill="none"/');
});
