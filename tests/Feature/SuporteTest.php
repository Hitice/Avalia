<?php

use App\Support\Suporte;

it('monta o link com o assunto ja escrito', function () {
    // Quem pergunta raramente sabe dizer de que tela veio, e essa ida e volta
    // e o que faz o atendimento demorar.
    $link = Suporte::whatsapp('Fatura de julho', 'Fatura 12');

    expect($link)->toStartWith('https://wa.me/')
        ->and(urldecode($link))->toContain('Assunto: Fatura de julho')
        ->and(urldecode($link))->toContain('Referência: Fatura 12');
});

it('funciona sem assunto nenhum', function () {
    expect(urldecode(Suporte::whatsapp()))->toContain('preciso de ajuda');
});

it('leva so digitos do telefone', function () {
    config()->set('services.suporte.whatsapp', '+55 (34) 99117-6599');

    expect(Suporte::whatsapp())->toStartWith('https://wa.me/5534991176599?');
});
