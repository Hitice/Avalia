<?php

use App\Models\Consulta;
use App\Models\Servico;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * A administracao abrindo o resultado de uma consulta de cliente.
 *
 * E a unica tela em que ela ve o dado de um titular que nao e cliente dela, e
 * por isso a abertura e um EVENTO, nao uma leitura qualquer: quem abriu, de
 * qual empresa e quando ficam na trilha. E essa trilha que responde a pergunta
 * que o titular tem o direito de fazer.
 */
function consultaDeCliente(): Consulta
{
    $empresa = empresaComPlano();
    app(App\Actions\Consumo\RegistrarConsulta::class)($empresa, Servico::firstWhere('codigo', 'scpc-bvs'), 1);

    $consulta = Consulta::latest('id')->firstOrFail();
    $consulta->update([
        'documento' => '12345678901',
        'resposta' => ['score' => 704, 'nome' => 'Fulana de Teste', 'protestos' => 1],
    ]);

    return $consulta->fresh();
}

it('oferece abrir e emitir o laudo na lista', function () {
    $consulta = consultaDeCliente();

    $html = admin()->get(route('consultas'))->assertOk()->getContent();

    expect($html)->toContain(route('consultas.ver', $consulta))
        ->toContain(route('consultas.pdf', $consulta))
        ->toContain('title="Abrir o resultado (a abertura fica na trilha)"');
});

it('abre o resultado e registra quem abriu', function () {
    $consulta = consultaDeCliente();

    admin()->get(route('consultas.ver', $consulta))
        ->assertOk()
        ->assertSee('Score e risco')
        // O documento aparece mascarado, nunca inteiro.
        ->assertSee('123******01')
        ->assertDontSee('12345678901')
        // A tela avisa que a abertura ficou registrada.
        ->assertSee('ficou registrada na trilha', false);

    $trilha = App\Models\Auditoria::where('acao', 'consulta.aberta')->sole();

    expect($trilha->staff_id)->not->toBeNull()
        ->and($trilha->dados['cliente_id'])->toBe($consulta->cliente_id)
        ->and(App\Support\Rotulos::acao('consulta.aberta'))->toBe('Resultado de consulta aberto');
});

it('emite o laudo e registra a emissao', function () {
    $consulta = consultaDeCliente();

    $resposta = admin()->get(route('consultas.pdf', $consulta))->assertOk();

    expect($resposta->headers->get('content-type'))->toContain('application/pdf')
        ->and($resposta->getContent())->toStartWith('%PDF');

    expect(App\Models\Auditoria::where('acao', 'consulta.laudo_emitido')->exists())->toBeTrue();
});

it('nao abre consulta que falhou nem consulta expurgada', function () {
    $consulta = consultaDeCliente();
    $consulta->update(['situacao' => Consulta::FALHA]);

    admin()->get(route('consultas.ver', $consulta->fresh()))->assertNotFound();

    $consulta->update(['situacao' => Consulta::SUCESSO, 'expurgada_em' => now(), 'resposta' => null]);

    admin()->get(route('consultas.ver', $consulta->fresh()))->assertNotFound();
    admin()->get(route('consultas.pdf', $consulta->fresh()))->assertNotFound();
});

it('nao deixa vendedor abrir a consulta de qualquer empresa', function () {
    $consulta = consultaDeCliente();

    comoVendedor(Staff::factory()->create(['papel' => 'vendedor']))
        ->get(route('consultas.ver', $consulta))
        ->assertForbidden();
});

it('chama a consulta da casa pelo nome certo na lista', function () {
    // Sem cliente E com quem consultou e consulta da propria casa, e nao
    // empresa que sumiu. Rotulo errado em tela de operacao vira diagnostico
    // errado: alguem vai procurar uma empresa encerrada que nunca existiu.
    carteira();
    $chefe = Staff::factory()->admin()->create(['nome' => 'Chefe da Casa']);
    $servico = Servico::firstWhere('codigo', 'scpc-bvs');

    comoVendedor($chefe)->post(route('carteira.consultar.executar'), [
        'servico_id' => $servico->id, 'documento' => '12345678901',
    ]);

    admin()->get(route('consultas'))
        ->assertOk()
        ->assertSee('Consulta da casa')
        ->assertSee('Chefe da Casa')
        ->assertDontSee('Empresa encerrada');
});
