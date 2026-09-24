<?php

use App\Enums\SituacaoEtiqueta;
use App\Models\Auditoria;
use App\Models\DestinoEtiqueta;
use App\Models\Etiqueta;
use App\Models\RenovacaoEtiqueta;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| A gestao de uma plaquinha
|--------------------------------------------------------------------------
|
| Cada acao daqui muda o que um desconhecido ve ao encostar o celular numa
| placa que esta no balcao de um cliente. Nao ha "desfazer" do lado de fora: a
| placa continua la, e quem leu ja foi para onde ela mandou.
|
*/

it('poe a plaquinha no ar na primeira venda', function () {
    $etiqueta = Etiqueta::factory()->create();

    admin()->put(route('etiquetas.apontar', $etiqueta), [
        'destino' => 'padariadoze.com.br',
        'cliente_nome' => 'Padaria do Zé',
    ])->assertRedirect();

    $etiqueta->refresh();

    expect($etiqueta->situacao)->toBe(SituacaoEtiqueta::Ativa)
        // O https que ninguem digita entra sozinho: sem ele o navegador leria
        // o endereco como caminho relativo.
        ->and($etiqueta->destino)->toBe('https://padariadoze.com.br')
        ->and($etiqueta->vendida_em)->not->toBeNull()
        // Prazo do config, e nao do formulario: validade digitada a mao um dia
        // sai com dois anos por engano.
        ->and($etiqueta->vence_em->toDateString())->toBe(now()->addYear()->toDateString())
        ->and($etiqueta->valor_cents)->toBe((int) config('etiquetas.precos.placa_cents'));
});

it('guarda para onde a plaquinha apontava antes', function () {
    // "Para onde essa placa apontava em março" e uma pergunta que vai aparecer,
    // e a coluna `destino` so sabe responder pelo presente.
    $etiqueta = Etiqueta::factory()->create();

    admin()->put(route('etiquetas.apontar', $etiqueta), ['destino' => 'https://antigo.com.br']);
    admin()->put(route('etiquetas.apontar', $etiqueta), ['destino' => 'https://novo.com.br']);

    expect(DestinoEtiqueta::count())->toBe(2)
        ->and(DestinoEtiqueta::vigente()->count())->toBe(1)
        ->and(DestinoEtiqueta::vigente()->value('destino'))->toBe('https://novo.com.br')
        ->and($etiqueta->refresh()->destino)->toBe('https://novo.com.br');
});

it('nao abre linha nova quando o destino nao mudou', function () {
    $etiqueta = Etiqueta::factory()->create();

    admin()->put(route('etiquetas.apontar', $etiqueta), ['destino' => 'https://igual.com.br']);
    admin()->put(route('etiquetas.apontar', $etiqueta), ['destino' => 'https://igual.com.br']);

    expect(DestinoEtiqueta::count())->toBe(1);
});

it('nao deixa a plaquinha virar porta para o navegador executar coisa', function () {
    $etiqueta = Etiqueta::factory()->create();

    admin()->put(route('etiquetas.apontar', $etiqueta), ['destino' => 'javascript:alert(1)'])
        ->assertSessionHasErrors('destino');

    expect($etiqueta->refresh()->destino)->toBeNull()
        ->and($etiqueta->situacao)->toBe(SituacaoEtiqueta::EmBranco);
});

it('nao guarda a primeira venda duas vezes', function () {
    // Trocar o destino dois anos depois nao pode reiniciar o prazo pago.
    $etiqueta = Etiqueta::factory()->create();

    admin()->put(route('etiquetas.apontar', $etiqueta), ['destino' => 'https://um.com.br']);
    $vencimento = $etiqueta->refresh()->vence_em;

    $this->travel(40)->days();
    admin()->put(route('etiquetas.apontar', $etiqueta), ['destino' => 'https://dois.com.br']);

    expect($etiqueta->refresh()->vence_em->toDateString())->toBe($vencimento->toDateString());
});

/*
|--------------------------------------------------------------------------
| Ligar, desligar e encerrar
|--------------------------------------------------------------------------
*/

it('suspende e reativa sem perder o destino', function () {
    $etiqueta = Etiqueta::factory()->ativa('https://loja.com.br')->create();

    admin()->post(route('etiquetas.alternar', $etiqueta));
    expect($etiqueta->refresh()->situacao)->toBe(SituacaoEtiqueta::Suspensa)
        ->and($etiqueta->destino)->toBe('https://loja.com.br');

    admin()->post(route('etiquetas.alternar', $etiqueta));
    expect($etiqueta->refresh()->situacao)->toBe(SituacaoEtiqueta::Ativa);
});

it('baixa a plaquinha sem apagar nem soltar o codigo', function () {
    $etiqueta = Etiqueta::factory()->ativa()->create(['codigo' => 'K7M2PX']);

    admin()->post(route('etiquetas.baixar', $etiqueta), ['motivo' => 'Placa quebrada'])->assertRedirect();

    // O registro continua, e o codigo segue reservado: reciclado, ele mandaria
    // a freguesia do cliente antigo para a loja de um estranho.
    expect(Etiqueta::where('codigo', 'K7M2PX')->exists())->toBeTrue()
        ->and($etiqueta->refresh()->situacao)->toBe(SituacaoEtiqueta::Baixada);
});

it('nao revende plaquinha baixada', function () {
    $etiqueta = Etiqueta::factory()->baixada()->create();

    expect(fn () => app(App\Actions\Etiquetas\VenderEtiqueta::class)($etiqueta, ['destino' => 'https://x.com.br']))
        ->toThrow(RuntimeException::class);
});

/*
|--------------------------------------------------------------------------
| Renovacao
|--------------------------------------------------------------------------
*/

it('renova a partir do vencimento antigo, e nao de hoje', function () {
    // Quem paga com quinze dias de atraso comprou um ano, e nao um ano menos
    // quinze dias.
    $etiqueta = Etiqueta::factory()->vencidaHa(15)->create();
    $vencia = $etiqueta->vence_em->copy();

    admin()->post(route('etiquetas.renovar', $etiqueta));

    expect($etiqueta->refresh()->vence_em->toDateString())
        ->toBe($vencia->addYear()->toDateString())
        ->and(RenovacaoEtiqueta::sole()->valor_cents)->toBe((int) config('etiquetas.precos.renovacao_cents'));
});

it('libera o aviso de novo depois de renovar', function () {
    // Sem isso, a plaquinha renovada nunca mais avisaria o proximo vencimento.
    $etiqueta = Etiqueta::factory()->ativa()->create(['avisada_em' => now()]);

    admin()->post(route('etiquetas.renovar', $etiqueta));

    expect($etiqueta->refresh()->avisada_em)->toBeNull();
});

/*
|--------------------------------------------------------------------------
| A lista
|--------------------------------------------------------------------------
*/

it('acha a plaquinha pelo codigo digitado errado', function () {
    // Quem le "1" no acrilico digita "I". A busca normaliza antes de procurar.
    Etiqueta::factory()->ativa()->create(['codigo' => 'K7M2P1']);

    admin()->get(route('etiquetas.index', ['busca' => 'k7m2pi']))
        ->assertOk()
        ->assertSee('K7M2P1');
});

it('cria um codigo avulso, sem tiragem', function () {
    admin()->post(route('etiquetas.avulsa'), ['titulo' => 'Cliente sem placa', 'tipo' => 'qr'])
        ->assertRedirect();

    $etiqueta = Etiqueta::sole();

    expect($etiqueta->lote_id)->toBeNull()
        ->and($etiqueta->sequencia)->toBeNull()
        ->and($etiqueta->situacao)->toBe(SituacaoEtiqueta::EmBranco);
});

it('registra na auditoria tudo que muda a plaquinha', function () {
    $etiqueta = Etiqueta::factory()->create();

    admin()->put(route('etiquetas.apontar', $etiqueta), ['destino' => 'https://loja.com.br']);
    admin()->post(route('etiquetas.alternar', $etiqueta));
    admin()->post(route('etiquetas.renovar', $etiqueta));

    expect(Auditoria::pluck('acao')->all())->toContain(
        'etiquetas.destino.trocado',
        'etiquetas.vendida',
        'etiquetas.alternada',
        'etiquetas.renovada',
    );
});

it('mantem o vendedor fora da gestao de plaquinhas', function () {
    $vendedor = Staff::factory()->create(['papel' => 'vendedor']);
    $etiqueta = Etiqueta::factory()->ativa()->create();

    comoVendedor($vendedor)->get(route('etiquetas.index'))->assertForbidden();
    comoVendedor($vendedor)->get(route('etiquetas.ficha', $etiqueta))->assertForbidden();
    comoVendedor($vendedor)->post(route('etiquetas.alternar', $etiqueta))->assertForbidden();
});
