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
    $tudo = implode(' ', $ressalvas);

    // Cada uma fecha uma porta diferente, e nenhuma pode sumir num ajuste de
    // redacao: sao as frases que separam a Avalia do uso que se faz do laudo.
    expect($ressalvas)->toHaveCount(3)
        // A decisao de credito e de quem consulta.
        ->and($ressalvas[0])->toContain('exclusiva de quem consulta')
        // Confidencialidade, vedacao de repasse e responsabilidade de quem usa.
        ->and($tudo)->toContain('confidenciais')
        ->and($tudo)->toContain('vedado o repasse')
        ->and($tudo)->toContain('civil e criminalmente')
        // Consultar nao e negativar.
        ->and($tudo)->toContain('não se confunde com anotação negativa')
        ->and($tudo)->toContain('123***901')
        // Registro que o provedor nao enviou nao aparece, e a ausencia aqui
        // nao prova ausencia la fora.
        ->and($tudo)->toContain('não prova que ele não exista')
        // A declaracao de finalidade da LGPD, no documento que o cliente arquiva.
        ->and($tudo)->toContain('Lei 13.709/2018');
});

it('nao escreve as ressalvas em caixa alta', function () {
    // Texto todo maiusculo se le mais devagar e sinaliza "pule isto". Aviso
    // feito para nao ser lido nao protege ninguem.
    foreach (Laudo::ressalvas('123***901') as $ressalva) {
        expect($ressalva)->not->toBe(mb_strtoupper($ressalva));
    }
});

it('leva marca, ressalvas e blocos para o PDF', function () {
    $empresa = empresaComPlano();
    $servico = App\Models\Servico::firstWhere('codigo', 'scpc-bvs');
    app(App\Actions\Consumo\RegistrarConsulta::class)($empresa, $servico, 1);

    $consulta = App\Models\Consulta::latest('id')->firstOrFail();
    $consulta->update(['resposta' => laudoCompleto()]);

    $pdf = App\Support\ConsultaPdf::resultado($consulta->fresh(), 'Fulana Operadora');

    expect($pdf)->toStartWith('%PDF')
        // A marca entra como imagem embutida, e nao como palavra.
        ->and($pdf)->toContain('/DCTDecode')
        ->and($pdf)->toContain('avaliaone.com.br')
        ->and($pdf)->toContain('Informa')         // "Informações importantes", em CP1252
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

it('a tela do resultado e casca do visor do relatorio', function () {
    $empresa = empresaComPlano();
    $servico = App\Models\Servico::firstWhere('codigo', 'scpc-bvs');
    app(App\Actions\Consumo\RegistrarConsulta::class)($empresa, $servico, 1);

    $consulta = App\Models\Consulta::latest('id')->firstOrFail();
    $consulta->update(['resposta' => ['score' => 700, 'nome' => 'Fulana de Teste']]);

    // A tela e casca do visor: o conteudo vive no relatorio, e repeti-lo
    // atras do popup era ler o mesmo laudo em duas diagramacoes.
    comoEmpresa($empresa)->get(route('empresa.consultas.ver', $consulta))
        ->assertOk()
        ->assertSee(route('empresa.consultas.pdf', $consulta), false)
        ->assertDontSee('Score e risco')
        ->assertDontSee('modelo_do_score');
});
