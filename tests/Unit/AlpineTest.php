<?php

/*
|--------------------------------------------------------------------------
| A ordem do Alpine
|--------------------------------------------------------------------------
|
| Guarda um defeito que nao aparece: componente registrado depois do `start`
| simplesmente nao existe, o `x-data` quebra com "is not defined", e a tela
| abre inteira, bonita e morta. Nenhum teste de tela pega isso, porque o HTML
| esta certo.
|
*/

it('registra todo componente antes de o Alpine comecar', function () {
    $js = file_get_contents(__DIR__.'/../../resources/js/app.js');

    $comeco = strpos($js, 'Alpine.start()');

    expect($comeco)->not->toBeFalse('o app.js precisa chamar Alpine.start()');

    preg_match_all('/Alpine\.data\(/', substr($js, $comeco), $tardios);

    expect($tardios[0])->toBeEmpty('há Alpine.data() depois do Alpine.start(): esse componente não existe em tela');
});
