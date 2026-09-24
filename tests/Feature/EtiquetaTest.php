<?php

use App\Models\Etiqueta;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| O estado da plaquinha
|--------------------------------------------------------------------------
|
| Vencimento nao e coluna: e conta de data. O que estes testes guardam sao os
| dois erros que essa conta permite, e os dois derrubam a loja de um cliente
| que esta em dia. O primeiro e matar a placa na manha do dia do vencimento,
| porque `vence_em` e data e a comparacao caiu na meia-noite. O segundo e
| ignorar a carencia, que existe justamente para o atraso de boleto nao virar
| cliente perdido.
|
*/

it('mantem a plaquinha viva no dia inteiro do vencimento', function () {
    // Vence hoje. Quem vence hoje tem o dia de hoje.
    $etiqueta = Etiqueta::factory()->ativa()->create(['vence_em' => now()]);

    expect($etiqueta->estado())->toBe('ativa')
        ->and($etiqueta->redireciona())->toBeTrue();
});

it('continua redirecionando durante a carencia', function () {
    $etiqueta = Etiqueta::factory()->vencidaHa(10)->create();

    expect($etiqueta->estado())->toBe('carencia')
        ->and($etiqueta->emCarencia())->toBeTrue()
        // O ponto inteiro da carencia: o dono e avisado, a loja continua de pe.
        ->and($etiqueta->redireciona())->toBeTrue();
});

it('para de redirecionar quando a carencia acaba', function () {
    $etiqueta = Etiqueta::factory()->vencidaHa(31)->create();

    expect($etiqueta->estado())->toBe('vencida')
        ->and($etiqueta->vencida())->toBeTrue()
        ->and($etiqueta->redireciona())->toBeFalse();
});

it('nao vence a plaquinha sem prazo', function () {
    // Cortesia, demonstracao, brinde: vendida sem data para acabar.
    $etiqueta = Etiqueta::factory()->ativa()->create(['vence_em' => null]);

    expect($etiqueta->estado())->toBe('ativa')
        ->and($etiqueta->redireciona())->toBeTrue();
});

it('respeita a decisao de quem suspendeu, mesmo dentro do prazo', function () {
    $etiqueta = Etiqueta::factory()->suspensa()->create();

    expect($etiqueta->estado())->toBe('suspensa')
        ->and($etiqueta->redireciona())->toBeFalse();
});

it('nao redireciona plaquinha em branco nem baixada', function () {
    expect(Etiqueta::factory()->create()->redireciona())->toBeFalse()
        ->and(Etiqueta::factory()->baixada()->create()->redireciona())->toBeFalse();
});

it('nao redireciona etiqueta ativa que ficou sem destino', function () {
    // Estado que so aparece por engano, e que mandaria o visitante para
    // lugar nenhum se o redirecionador confiasse so na situacao.
    $etiqueta = Etiqueta::factory()->ativa()->create(['destino' => null]);

    expect($etiqueta->estado())->toBe('ativa')
        ->and($etiqueta->redireciona())->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Nomes e enderecos
|--------------------------------------------------------------------------
*/

it('numera o arquivo pela sequencia do lote', function () {
    $etiqueta = Etiqueta::factory()->create(['codigo' => 'K7M2PX', 'sequencia' => 7]);

    // O Print Merge do Corel casa o arquivo com a linha do CSV pelo nome.
    expect($etiqueta->nomeDeArquivo())->toBe('0007-K7M2PX');
});

it('usa so o codigo quando a etiqueta e avulsa', function () {
    expect(Etiqueta::factory()->create(['codigo' => 'K7M2PX'])->nomeDeArquivo())->toBe('K7M2PX');
});

it('separa o aviso ja enviado do que ainda falta enviar', function () {
    $avisar = Etiqueta::factory()->ativa()->create(['vence_em' => now()->addDays(15)]);
    Etiqueta::factory()->ativa()->create(['vence_em' => now()->addDays(15), 'avisada_em' => now()]);
    Etiqueta::factory()->ativa()->create(['vence_em' => now()->addDays(16)]);

    // Cron repetido nao pode mandar o mesmo e-mail duas vezes: e assim que o
    // cliente aprende a criar regra de spam para a gente.
    $encontradas = Etiqueta::avisarEm(now()->addDays(15))->pluck('id');

    expect($encontradas)->toHaveCount(1)->and($encontradas->first())->toBe($avisar->id);
});
