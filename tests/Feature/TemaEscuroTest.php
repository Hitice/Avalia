<?php

use Illuminate\Support\Facades\File;

/**
 * Texto escuro no tema escuro ja aconteceu: rotulo sem cor herdava a cor do
 * corpo e sumia no fundo. Este teste varre as views e derruba a suite quando
 * alguem escreve texto cinza escuro sem a variante dark, ou rotulo sem cor
 * nenhuma. A correcao e sempre a mesma: usar as utilities do tema
 * (rotulo-campo, rotulo-opcao, ajuda-campo) ou declarar o par dark:text.
 */
it('nao deixa texto sem variante do tema escuro nas telas', function () {
    $problemas = [];

    foreach (File::allFiles(resource_path('views')) as $arquivo) {
        // E-mail nao tem tema: cliente de e-mail nao carrega o CSS do site.
        if (str_starts_with($arquivo->getRelativePathname(), 'mail')) {
            continue;
        }

        preg_match_all('/class="([^"]*)"/', $arquivo->getContents(), $atributos);

        foreach ($atributos[1] as $classes) {
            $cinzaEscuroSemPar = preg_match('/(?<![\w:-])text-gray-[6789]00(?![\w-])/', $classes)
                && ! str_contains($classes, 'dark:text');

            // So a classe de tamanho, sem cor: o texto herda a cor do corpo,
            // que no tema escuro e escura.
            $tamanhoSemCor = in_array(trim($classes), ['text-sm', 'text-xs'], true);

            if ($cinzaEscuroSemPar || $tamanhoSemCor) {
                $problemas[] = $arquivo->getRelativePathname().' -> class="'.$classes.'"';
            }
        }
    }

    expect($problemas)->toBe([]);
});
