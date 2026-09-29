<?php

use App\Models\Etiqueta;
use App\Models\LoteEtiqueta;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| A numeracao da producao
|--------------------------------------------------------------------------
|
| O numero serve para conferir producao contra a grafica. Recomecando a cada
| campanha, "a placa 3" nao identifica uma placa, identifica uma por tiragem.
|
*/

it('continua a numeracao na campanha seguinte', function () {
    admin()->post(route('etiquetas.gerar'), ['quantidade' => 3, 'titulo' => 'Primeira']);
    admin()->post(route('etiquetas.gerar'), ['quantidade' => 2, 'titulo' => 'Segunda']);

    expect(Etiqueta::orderBy('id')->pluck('sequencia')->all())->toBe([1, 2, 3, 4, 5]);
});

it('mantem a ordem dentro da campanha', function () {
    // O nome do arquivo no ZIP sai da sequencia, e a grafica imprime na ordem.
    admin()->post(route('etiquetas.gerar'), ['quantidade' => 2, 'titulo' => 'Primeira']);
    admin()->post(route('etiquetas.gerar'), ['quantidade' => 3, 'titulo' => 'Segunda']);

    $segunda = LoteEtiqueta::where('titulo', 'Segunda')->sole();

    expect($segunda->etiquetas()->pluck('sequencia')->all())->toBe([3, 4, 5]);
});

it('renumera as campanhas que ja existiam', function () {
    // Estado antigo: cada tiragem comecando do 1, com numero repetido entre
    // elas. E o que a migration conserta.
    $staff = Staff::factory()->admin()->create();

    foreach (['LT-0001' => 3, 'LT-0002' => 2] as $codigo => $quantas) {
        $lote = LoteEtiqueta::create([
            'codigo' => $codigo, 'titulo' => $codigo, 'quantidade' => $quantas,
            'tipo' => 'qr', 'staff_id' => $staff->id,
        ]);

        foreach (range(1, $quantas) as $n) {
            Etiqueta::factory()->create(['lote_id' => $lote->id, 'sequencia' => $n]);
        }
    }

    // Avulsa: sem lote e sem numero, e assim continua.
    $avulsa = Etiqueta::factory()->create(['lote_id' => null, 'sequencia' => null]);

    expect(DB::table('etiquetas')->whereNotNull('sequencia')->pluck('sequencia')->sort()->values()->all())
        ->toBe([1, 1, 2, 2, 3]);

    (require database_path('migrations/2026_09_29_000002_numeracao_continua_entre_campanhas.php'))->up();

    expect(DB::table('etiquetas')->whereNotNull('lote_id')->orderBy('lote_id')->orderBy('sequencia')->pluck('sequencia')->all())
        ->toBe([1, 2, 3, 4, 5])
        ->and($avulsa->refresh()->sequencia)->toBeNull();
});

it('nao colide com a numeracao recuperada ao gerar a proxima', function () {
    // A geracao le o maior numero existente, entao ela precisa enxergar o que a
    // migration deixou, e nao o que a tiragem tinha antes.
    $staff = Staff::factory()->admin()->create();
    $lote = LoteEtiqueta::create([
        'codigo' => 'LT-0001', 'titulo' => 'Antiga', 'quantidade' => 4,
        'tipo' => 'qr', 'staff_id' => $staff->id,
    ]);

    foreach (range(1, 4) as $n) {
        Etiqueta::factory()->create(['lote_id' => $lote->id, 'sequencia' => $n]);
    }

    admin()->post(route('etiquetas.gerar'), ['quantidade' => 2, 'titulo' => 'Nova']);

    expect(Etiqueta::whereNotNull('sequencia')->max('sequencia'))->toBe(6)
        ->and(Etiqueta::whereNotNull('sequencia')->count())
        ->toBe(Etiqueta::whereNotNull('sequencia')->distinct()->count('sequencia'));
});
