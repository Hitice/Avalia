<?php

/*
|--------------------------------------------------------------------------
| Um modal, e ele cabe no celular
|--------------------------------------------------------------------------
*/

$raiz = dirname(__DIR__, 2);

it('nao deixa overlay de modal escrito a mao nas views', function () use ($raiz) {
    $achados = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz.'/resources/views')) as $f) {
        if (str_ends_with((string) $f, '.blade.php') && ! str_contains((string) $f, 'components/avalia/modal')
            && preg_match('/fixed inset-0 z-50 flex items-(center|start|end) justify-center/', (string) file_get_contents((string) $f))) {
            $achados[] = str_replace($raiz.'/', '', (string) $f);
        }
    }

    expect($achados)->toBe([]);
});

it('e folha no celular e caixa no desktop, com o pe e o campo preparados para o toque', function () use ($raiz) {
    $css = (string) file_get_contents($raiz.'/resources/css/app.css');

    expect($css)->toMatch('/@utility modal-fundo \{[^}]*items-end[^}]*sm:items-center/s')
        ->and($css)->toMatch('/@utility modal \{[^}]*max-h-\[92dvh\][^}]*rounded-t-2xl[^}]*sm:rounded-2xl/s')
        ->and($css)->toContain('env(safe-area-inset-bottom)')
        ->and($css)->toMatch('/@media \(max-width: 639px\) \{\s*\.campo \{ font-size: 1rem; \}/');
});
