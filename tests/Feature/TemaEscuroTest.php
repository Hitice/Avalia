<?php

use Illuminate\Support\Facades\File;

/**
 * Texto preto no fundo escuro voltou tres vezes, sempre pelo mesmo caminho:
 * alguem escrevia um elemento sem cor, e a cor herdada era o preto padrao do
 * navegador. Legivel no tema claro, invisivel no escuro.
 *
 * A correcao esta na fonte, e nao nas telas: o `body` declara a cor nos dois
 * temas, entao quem nao escreve cor nenhuma ja acerta. Este arquivo guarda as
 * duas metades: a fonte continua no lugar, e ninguem escreve cor so do tema
 * claro por cima dela.
 */
it('mantem a cor padrao do texto e do fundo nos dois temas', function () {
    // O teste mais importante do arquivo. Se esta linha cair, TODA tela sem
    // cor propria volta a escrever preto no escuro, e o defeito reaparece
    // inteiro em vez de aparecer numa tela so.
    $css = File::get(resource_path('css/app.css'));

    preg_match('/\bbody\s*\{([^}]*)\}/', $css, $corpo);

    expect($corpo[1] ?? '')->toContain('text-gray-800')
        ->and($corpo[1] ?? '')->toContain('dark:text-white/90')
        ->and($corpo[1] ?? '')->toContain('dark:bg-gray-900');
});

/**
 * As views que formam o site institucional.
 *
 * Reconhecidas pelo caminho, e nao por uma lista de arquivos: pagina nova do
 * site nasce coberta pela mesma excecao, sem ninguem precisar lembrar de
 * inscrever o nome dela aqui.
 */
function paginaDoSite(string $caminho): bool
{
    $caminho = str_replace('\\', '/', $caminho);

    return str_starts_with($caminho, 'paginas/site/')
        || str_starts_with($caminho, 'components/site/')
        || str_starts_with($caminho, 'errors/')
        || $caminho === 'layouts/site.blade.php';
}

it('nao deixa cor de texto so do tema claro nas telas', function () {
    $problemas = [];

    foreach (File::allFiles(resource_path('views')) as $arquivo) {
        // E-mail nao tem tema: cliente de e-mail nao carrega o CSS do site, e
        // la a cor escrita a mao e a unica que existe.
        if (str_starts_with($arquivo->getRelativePathname(), 'mail')) {
            continue;
        }

        // O site institucional tambem nao tem tema: ele vive no claro e so
        // nele, por decisao de produto. O interruptor e ferramenta de quem
        // trabalha no sistema o dia inteiro, e nao de quem chegou para ler o
        // que a casa faz; o preto que o site usa e superficie escolhida dentro
        // do tema claro. Escrever o par `dark:` nessas paginas seria pior que
        // inutil: bastaria o navegador lembrar do escuro do CRM para o site
        // abrir com metade de cada tema.
        if (paginaDoSite($arquivo->getRelativePathname())) {
            continue;
        }

        preg_match_all('/class="([^"]*)"/', $arquivo->getContents(), $atributos);

        foreach ($atributos[1] as $classes) {
            // Cinza escuro ou preto declarado sem o par do tema escuro. Some
            // no fundo escuro exatamente como sumia antes.
            $escuroSemPar = preg_match('/(?<![\w:-])text-(gray-[6789]00|black)(?![\w-])/', $classes)
                && ! str_contains($classes, 'dark:text');

            // Fundo branco sem o par: a outra metade do mesmo defeito, que
            // acende um retangulo no meio da tela escura.
            $fundoSemPar = preg_match('/(?<![\w:-])bg-white(?![\w\/-])/', $classes)
                && ! str_contains($classes, 'dark:bg');

            if ($escuroSemPar || $fundoSemPar) {
                $problemas[] = $arquivo->getRelativePathname().' -> class="'.$classes.'"';
            }
        }
    }

    expect($problemas)->toBe([]);
});
