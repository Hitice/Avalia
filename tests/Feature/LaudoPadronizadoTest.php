<?php

use App\Support\Laudo;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * A forma canonica do laudo, que a tela e o PDF leem da mesma fonte.
 *
 * O problema de origem: cada fornecedor entrega uma coisa diferente, e cada
 * produto do mesmo fornecedor entrega um recorte diferente ainda. Sem forma
 * comum, o resultado virava despejo de campos crus em ordem aleatoria.
 */
function laudoCompleto(): array
{
    return [
        'laudo' => 1,
        'fornecido_em' => '2026-08-10T12:00:00-03:00',
        'nome' => 'Fulana de Teste',
        'score' => 688,
        'modelo_do_score' => 'SCORE_PF',
        'protestos' => 2,
        'pendencias_financeiras' => 3,
        'consultas_recentes' => 5,
        'campo_que_o_mapa_nao_conhece' => 'valor cru',
    ];
}

it('ordena os blocos como quem decide credito le', function () {
    $titulos = collect(Laudo::blocos(laudoCompleto()))->pluck('titulo')->all();

    // Decisao antes de identidade, identidade antes de restricao, restricao
    // antes de contexto: quem le de cima para baixo conclui antes do fim.
    expect($titulos)->toBe(['Score e risco', 'Identificação', 'Restrições', 'Contexto', 'Outras informações']);
});

it('nao perde o campo que o mapa ainda nao reconhece', function () {
    $blocos = collect(Laudo::blocos(laudoCompleto()))->keyBy('titulo');

    expect($blocos['Outras informações']['linhas'][0]['rotulo'])->toBe('Campo que o mapa nao conhece')
        ->and($blocos['Outras informações']['linhas'][0]['valor'])->toBe('valor cru');
});

it('esconde bloco vazio em vez de mostrar bloco sem linha', function () {
    $titulos = collect(Laudo::blocos(['score' => 700]))->pluck('titulo')->all();

    expect($titulos)->toBe(['Score e risco']);
});

it('diz o que a consulta nao contemplou', function () {
    // Um produto de score puro nao pesquisa restricao nenhuma, e isso precisa
    // estar escrito: ausencia de pendencia e ausencia de informacao sao coisas
    // opostas, e concluir a primeira pela segunda e o erro mais caro possivel.
    $ausentes = Laudo::ausentes(['score' => 700, 'nome' => 'Fulana']);

    expect($ausentes)->toBe(['restrições', 'contexto']);

    // Laudo completo nao acusa ausencia nenhuma.
    expect(Laudo::ausentes(laudoCompleto()))->toBe([]);
});

it('escreve valor como pessoa le', function () {
    expect(Laudo::valor('tem_restricao', true))->toBe('Sim')
        ->and(Laudo::valor('tem_restricao', false))->toBe('Não')
        ->and(Laudo::valor('valor_total_das_restricoes_cents', 193_102))->toBe(App\Support\Dinheiro::brl(193_102))
        ->and(Laudo::valor('score', 688))->toBe('688');
});

it('carimba as ressalvas em todo laudo', function () {
    $ressalvas = Laudo::ressalvas('123***901');

    expect(count($ressalvas))->toBe(3)
        // A que separa a Avalia da decisao de credito de quem consulta.
        ->and($ressalvas[0])->toContain('exclusiva responsabilidade de quem consulta')
        // A que impede a consulta de ser lida como anotacao negativa.
        ->and($ressalvas[1])->toContain('não se confunde com anotação negativa')
        ->and($ressalvas[1])->toContain('123***901')
        ->and($ressalvas[2])->toContain('vedado o repasse');
});

it('leva marca, ressalvas e blocos para o PDF', function () {
    $empresa = empresaComPlano();
    $servico = App\Models\Servico::firstWhere('codigo', 'scpc-bvs');
    app(App\Actions\Consumo\RegistrarConsulta::class)($empresa, $servico, 1);

    $consulta = App\Models\Consulta::latest('id')->firstOrFail();
    $consulta->update(['resposta' => laudoCompleto()]);

    $pdf = App\Support\ConsultaPdf::resultado($consulta->fresh(), 'Fulana Operadora');

    expect($pdf)->toStartWith('%PDF')
        ->and($pdf)->toContain('Avalia')
        ->and($pdf)->toContain('Aten')            // "Atenção", escapado em CP1252
        ->and($pdf)->toContain('Score e risco')
        ->and($pdf)->toContain('Fulana Operadora');

    // O documento nunca sai inteiro no arquivo que circula: so a mascara.
    expect($pdf)->toContain(App\Support\Documento::mascarar($consulta->documento));
});

it('avisa no PDF o que o produto nao contemplou', function () {
    $empresa = empresaComPlano();
    $servico = App\Models\Servico::firstWhere('codigo', 'scpc-bvs');
    app(App\Actions\Consumo\RegistrarConsulta::class)($empresa, $servico, 1);

    $consulta = App\Models\Consulta::latest('id')->firstOrFail();
    $consulta->update(['resposta' => ['score' => 700]]);

    expect(App\Support\ConsultaPdf::resultado($consulta->fresh()))
        ->toContain('n')
        ->toContain('contempla');
});

it('mostra os mesmos blocos na tela do cliente', function () {
    $empresa = empresaComPlano();
    $servico = App\Models\Servico::firstWhere('codigo', 'scpc-bvs');
    app(App\Actions\Consumo\RegistrarConsulta::class)($empresa, $servico, 1);

    $consulta = App\Models\Consulta::latest('id')->firstOrFail();
    $consulta->update(['resposta' => ['score' => 700, 'nome' => 'Fulana de Teste']]);

    comoEmpresa($empresa)->get(route('empresa.consultas.ver', $consulta))
        ->assertOk()
        ->assertSee('Score e risco')
        ->assertSee('Score')
        ->assertSee('não contempla', false)
        // Nome de chave de API nunca aparece para quem le.
        ->assertDontSee('modelo_do_score');
});
