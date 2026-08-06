<?php

use App\Models\Campanha;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * O interruptor da campanha.
 *
 * Desde que a vigente veste o banner da pagina publica, encerrar precisa ser
 * um clique da administracao com efeito imediato la fora.
 */
function campanhaVigente(): Campanha
{
    return Campanha::create([
        'nome' => 'Adesão de agosto',
        'oferta' => 'Taxa de adesão facilitada para quem contratar até o fim do mês.',
        'inicio' => today()->subDay(),
        'fim' => null,
        'ativa' => true,
    ]);
}

it('encerra a campanha e ela sai da pagina publica no mesmo instante', function () {
    $campanha = campanhaVigente();

    $this->get('/')->assertSee('Adesão de agosto');

    admin()->from(route('campanhas.index'))
        ->post(route('campanhas.alternar', $campanha))
        ->assertRedirect(route('campanhas.index'))
        ->assertSessionHas('ok', 'Campanha encerrada.');

    expect($campanha->fresh()->ativa)->toBeFalse();
    $this->get('/')->assertDontSee('Adesão de agosto');
});

it('reabre campanha encerrada', function () {
    $campanha = campanhaVigente();
    $campanha->update(['ativa' => false]);

    admin()->post(route('campanhas.alternar', $campanha))
        ->assertSessionHas('ok', 'Campanha reaberta.');

    expect($campanha->fresh()->ativa)->toBeTrue();
});

it('nao deixa o vendedor mexer no interruptor', function () {
    $campanha = campanhaVigente();

    [$vendedor] = carteira();

    comoVendedor($vendedor)->post(route('campanhas.alternar', $campanha))
        ->assertForbidden();

    expect($campanha->fresh()->ativa)->toBeTrue();
});
