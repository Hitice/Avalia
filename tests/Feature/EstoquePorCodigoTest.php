<?php

use App\Models\Etiqueta;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function entregarCodigos(string $codigos, ?Staff $para = null): Illuminate\Testing\TestResponse
{
    $para ??= Staff::factory()->create(['papel' => 'vendedor']);

    return admin()->post(route('sales.estoque.entregar-codigos'), ['vendedor_id' => $para->id, 'codigos' => $codigos]);
}

it('entrega as placas escolhidas pelos codigos, e corrige letra parecida', function () {
    $maria = Staff::factory()->create(['papel' => 'vendedor', 'nome' => 'Maria']);
    Etiqueta::factory()->create(['codigo' => 'AB1CD2', 'vendida_em' => null]);
    Etiqueta::factory()->create(['codigo' => 'EF0GH3', 'vendida_em' => null]);

    // "ab1cd2, EF O GH3": minuscula, espaco e O por 0, como quem le da placa.
    entregarCodigos('ab1cd2, EF O GH3', $maria)->assertSessionHas('ok');

    expect(Etiqueta::noEstoqueDe($maria->id)->pluck('codigo')->sort()->values()->all())
        ->toBe(['AB1CD2', 'EF0GH3']);
});

it('recusa o lote inteiro quando um codigo nao existe, e diz qual', function () {
    // Entregar nove e recusar uma em silencio faria o vendedor sair com dez
    // placas e nove no sistema.
    $maria = Staff::factory()->create(['papel' => 'vendedor']);
    Etiqueta::factory()->create(['codigo' => 'AB1CD2', 'vendida_em' => null]);

    entregarCodigos('AB1CD2, ZZZZZ9', $maria);

    expect(session('erro'))->toContain('ZZZZZ9')
        ->and(Etiqueta::noEstoqueDe($maria->id)->count())->toBe(0);
});

it('recusa placa ja vendida e placa na mao de outro, nomeando cada uma', function () {
    // Codigos sem I, L, O e U: o normalizador reescreve essas letras de proposito
    // (sao as lidas errado da placa), e um codigo real nunca as tem.
    $maria = Staff::factory()->create(['papel' => 'vendedor']);
    $joao = Staff::factory()->create(['papel' => 'vendedor']);
    Etiqueta::factory()->ativa()->create(['codigo' => 'VEND01']);
    Etiqueta::factory()->create(['codigo' => 'XJ7K2M', 'vendida_em' => null, 'consignada_para_id' => $joao->id]);
    Etiqueta::factory()->create(['codigo' => 'FR33Z1', 'vendida_em' => null]);

    entregarCodigos('VEND01, FR33Z1', $maria);
    expect(session('erro'))->toContain('VEND01');

    entregarCodigos('XJ7K2M, FR33Z1', $maria);
    expect(session('erro'))->toContain('XJ7K2M');

    // E a livre continua livre: tudo ou nada.
    expect(Etiqueta::noEstoqueDe($maria->id)->count())->toBe(0);
});

it('nao abre a entrega por codigo ao vendedor', function () {
    $vendedor = Staff::factory()->create(['papel' => 'vendedor']);

    test()->actingAs($vendedor, 'staff')->withSession(['versao_staff' => 1])
        ->post(route('sales.estoque.entregar-codigos'), ['vendedor_id' => $vendedor->id, 'codigos' => 'AB1CD2'])
        ->assertForbidden();
});
