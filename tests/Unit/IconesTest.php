<?php

use App\Helpers\MenuHelper;
use App\Support\Icones;

it('tem um icone no mapa para todo item de menu', function () {
    $itens = array_merge(MenuHelper::getMainNavItems(), MenuHelper::getItensDaSales(), MenuHelper::getItensDaEmpresa());

    foreach ($itens as $item) {
        expect(Icones::nomes())->toContain($item['icon']);
    }
});

it('recusa nome que nao existe, em vez de desenhar vazio', function () {
    expect(fn () => Icones::miolo('inexistente'))->toThrow(InvalidArgumentException::class)
        ->and(Icones::miolo('lapis'))->toContain('<path')
        ->and(Icones::miolo('lapis'))->not->toContain('stroke="none"')
        // Preenchido nao leva o traco do componente por cima.
        ->and(Icones::miolo('inicio'))->toStartWith('<g stroke="none">');
});
