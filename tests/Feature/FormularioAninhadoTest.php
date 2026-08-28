<?php

use App\Actions\Consumo\FecharCompetencia;
use App\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Nenhuma tela pode ter formulario dentro de formulario.
 *
 * O HTML nao aninha: o navegador descarta o `<form>` de dentro e os botoes dele
 * passam a enviar o de fora. Aconteceu no financeiro, onde a tabela vive dentro
 * do formulario da selecao em lote e cada linha tinha o proprio formulario de
 * emitir cobranca, reenviar e confirmar pagamento. Os quatro botoes enviavam o
 * lote, que responde por outra rota.
 *
 * A suite inteira passou por cima disso porque teste de feature posta direto na
 * rota, sem passar pelo HTML. Este arquivo olha o HTML.
 *
 * O conserto e o atributo `form`: o formulario e declarado fora da tabela e o
 * botao se liga a ele pelo id. O campo tambem precisa do atributo, senao o valor
 * dele nao vai junto.
 */

/**
 * Onde o HTML abre um `<form>` com outro ainda aberto.
 *
 * @return list<string> trecho ao redor de cada aninhamento
 */
function formulariosAninhados(string $html): array
{
    $achados = [];
    $abertos = 0;

    preg_match_all('/<\/?form\b[^>]*>/i', $html, $casos, PREG_OFFSET_CAPTURE);

    foreach ($casos[0] as [$marca, $onde]) {
        if (str_starts_with(mb_strtolower($marca), '</form')) {
            $abertos = max(0, $abertos - 1);

            continue;
        }

        if ($abertos > 0) {
            $achados[] = trim(substr($html, max(0, $onde - 90), 200));
        }

        $abertos++;
    }

    return $achados;
}

/*
|--------------------------------------------------------------------------
| O detector, antes das telas
|--------------------------------------------------------------------------
*/

it('reconhece formulario aninhado e deixa passar o que esta lado a lado', function () {
    expect(formulariosAninhados('<form><input></form><form><input></form>'))->toBe([])
        ->and(formulariosAninhados('<form><div><form><button></form></div></form>'))->toHaveCount(1)
        // O atributo `form` num botao solto e o conserto, e nao um aninhamento.
        ->and(formulariosAninhados('<form><button form="outro"></form><form id="outro"></form>'))->toBe([]);
});

/*
|--------------------------------------------------------------------------
| As telas com tabela dentro de formulario
|--------------------------------------------------------------------------
*/

it('nao aninha formulario na tela do financeiro, com fatura em aberto e paga', function () {
    $emAberto = app(FecharCompetencia::class)(contrato()[0], '2026-06')['fatura'];
    $paga = app(FecharCompetencia::class)(contrato()[0], '2026-07')['fatura'];
    $paga->update(['situacao_pagamento' => App\Models\Fatura::PAGAMENTO_LIQUIDADO, 'liquidada_em' => now()]);

    $html = admin()->get(route('financeiro.index'))->assertOk()->getContent();

    expect(formulariosAninhados($html))->toBe([])
        // E os quatro botoes continuam ligados aos formularios certos.
        ->and($html)->toContain('id="liquidar-'.$emAberto->id.'"')
        ->and($html)->toContain('form="liquidar-'.$emAberto->id.'"')
        ->and($html)->toContain('id="estornar-'.$paga->id.'"')
        ->and($html)->toContain('form="estornar-'.$paga->id.'"');
});

it('nao aninha formulario na base de leads, nem na aba de removidos', function () {
    Lead::factory()->count(3)->create();
    Lead::factory()->create(['nome' => 'REMOVIDO LTDA'])->delete();

    expect(formulariosAninhados(admin()->get(route('leads.index'))->assertOk()->getContent()))->toBe([])
        ->and(formulariosAninhados(
            admin()->get(route('leads.index', ['removidos' => 1]))->assertOk()->getContent()
        ))->toBe([]);
});

it('nao aninha formulario nas demais telas de gestao', function () {
    $lead = Lead::factory()->pronto()->create();
    [$vendedor] = carteira();
    app(App\Actions\Prospeccao\CompartilharLeads::class)([$lead->id], $vendedor);

    foreach (['empresas.index', 'consultas', 'equipe.index', 'campanhas.index', 'catalogo.index', 'auditoria'] as $rota) {
        expect(formulariosAninhados(admin()->get(route($rota))->assertOk()->getContent()))
            ->toBe([], "formulario aninhado em {$rota}");
    }

    foreach (['carteira', 'carteira.leads'] as $rota) {
        expect(formulariosAninhados(comoVendedor($vendedor)->get(route($rota))->assertOk()->getContent()))
            ->toBe([], "formulario aninhado em {$rota}");
    }

    expect(formulariosAninhados(
        comoVendedor($vendedor)->get(route('carteira.leads.editar', $lead))->assertOk()->getContent()
    ))->toBe([]);
});
