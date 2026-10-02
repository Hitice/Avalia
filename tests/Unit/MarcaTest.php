<?php

use App\Support\Marca;

/*
|--------------------------------------------------------------------------
| Uma marca, tres copias
|--------------------------------------------------------------------------
|
| O desenho mora em App\Support\Marca. O favicon e um arquivo em public/ e o
| miolo do QR e JS sem runner, entao este teste le os dois e cobra que sejam
| o mesmo tracado, e que o nome escrito use o magenta do desenho.
*/

$raiz = dirname(__DIR__, 2);

it('desenha o favicon com o arco e o ponteiro de Marca', function () use ($raiz) {
    $favicon = (string) file_get_contents($raiz.'/public/favicon.svg');

    expect($favicon)->toContain('viewBox="'.Marca::CAIXA.'"')
        ->and($favicon)->toContain(Marca::arco())
        ->and($favicon)->toContain(Marca::ponteiro('currentColor'))
        ->and($favicon)->toContain('prefers-color-scheme:dark');
});

it('desenha o miolo do QR com o arco e o ponteiro de Marca, ponteiro preto', function () use ($raiz) {
    $qr = (string) file_get_contents($raiz.'/resources/js/qr.js');

    expect($qr)->toContain('viewBox="'.Marca::CAIXA.'"')
        ->and($qr)->toContain(Marca::arco())
        ->and($qr)->toContain(Marca::ponteiro('#000000'))
        ->and($qr)->toMatch('/id="fundo"[^>]*fill="none"/');
});

it('escreve o nome no magenta do desenho', function () use ($raiz) {
    $css = (string) file_get_contents($raiz.'/resources/css/app.css');
    [$escura, $clara] = Marca::magenta();

    expect($css)->toContain("--color-marca-escura: {$escura};")
        ->and($css)->toContain("--color-marca-clara: {$clara};");
});

it('nao repete id de degrade quando a marca aparece duas vezes', function () {
    expect(Marca::arco('a'))->not->toBe(Marca::arco('b'))
        ->and(Marca::arco('a'))->toContain('id="a-1"')->toContain('url(#a-4)');
});

it('tem o desenho do dono em public/marca', function () use ($raiz) {
    expect(file_exists($raiz.'/public/marca/avaliaone.svg'))->toBeTrue()
        ->and(file_exists($raiz.'/public/marca/icone-180.png'))->toBeTrue();
});
