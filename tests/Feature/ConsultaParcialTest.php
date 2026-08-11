<?php

use App\Models\Conexao;
use App\Models\Consulta;
use App\Models\Servico;
use App\Support\Laudo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * Consulta que sai incompleta.
 *
 * Um servico do catalogo puxa de mais de uma base ("Base I e III"), e base e
 * fornecedor diferente. Falhar a consulta inteira porque UMA delas nao
 * respondeu e o pior comportamento possivel: o cliente fica sem a informacao
 * que estava disponivel por causa da que nao estava.
 *
 * Entao vale o que veio, e o que faltou e dito com todas as letras. A consulta
 * so falha inteira quando NENHUMA base responde, porque ai nao ha o que
 * entregar e cobrar seria cobrar por nada.
 */
function servicoDeDuasBases(): Servico
{
    Conexao::create([
        'fornecedor' => 'boa-vista',
        'ambiente' => 'sandbox',
        'credenciais' => [
            'client_id' => 'x', 'client_secret' => 'y',
            'escopo_relatorios' => 'https://api.equifax.com/business/reporting-orchestrator/v1',
        ],
        'ativa' => true,
    ]);

    $servico = Servico::firstWhere('codigo', 'scpc-bvs');
    $servico->update(['fornecedor' => 'simulado,boa-vista', 'codigo_fornecedor' => 'SCPC_NET_PF']);

    return $servico->fresh();
}

it('entrega o que veio quando uma base nao responde', function () {
    $empresa = empresaComPlano();
    $servico = servicoDeDuasBases();

    Http::preventStrayRequests();
    Http::fake([
        '*oauth/token' => Http::response(['access_token' => 'tok']),
        // A Boa Vista responde o mesmo 404.01 que o ambiente real responde hoje.
        '*consulta' => Http::response([
            'efxErrorCode' => 404.01,
            'description' => 'Not Found - The specified resource does not exist',
        ], 404),
    ]);

    app(App\Actions\Consumo\ExecutarConsulta::class)(
        $empresa, $servico, '12345678901', 'Pesquisa de score de crédito',
    );

    $consulta = Consulta::latest('id')->firstOrFail();

    // Concluida, e nao falha: uma base respondeu.
    expect($consulta->deuCerto())->toBeTrue()
        ->and($consulta->preco_cents)->toBeGreaterThan(0);

    // E o laudo diz o que faltou, com o motivo.
    $faltou = Laudo::fontesIndisponiveis($consulta->resposta);

    expect($faltou)->toHaveKey('boa-vista')
        ->and($faltou['boa-vista'])->toContain('não está publicada');
});

it('falha inteira so quando nenhuma base responde', function () {
    $empresa = empresaComPlano();

    // Uma base so, e ela nao responde: nao ha resultado parcial, ha resultado
    // nenhum, e nada e cobrado.
    $servico = Servico::firstWhere('codigo', 'scpc-bvs');
    $servico->update(['fornecedor' => 'boa-vista', 'codigo_fornecedor' => 'SCPC_NET_PF']);

    Conexao::create([
        'fornecedor' => 'boa-vista', 'ambiente' => 'sandbox', 'ativa' => true,
        'credenciais' => [
            'client_id' => 'x', 'client_secret' => 'y',
            'escopo_relatorios' => 'https://api.equifax.com/business/reporting-orchestrator/v1',
        ],
    ]);

    Http::preventStrayRequests();
    Http::fake([
        '*oauth/token' => Http::response(['access_token' => 'tok']),
        '*consulta' => Http::response(['efxErrorCode' => 404.01, 'description' => 'x'], 404),
    ]);

    app(App\Actions\Consumo\ExecutarConsulta::class)(
        $empresa, $servico->fresh(), '12345678901', 'Pesquisa de score de crédito',
    );

    $consulta = Consulta::latest('id')->firstOrFail();

    expect($consulta->deuCerto())->toBeFalse()
        ->and($consulta->preco_cents)->toBe(0)
        ->and($consulta->custo_cents)->toBe(0);
});

it('cala o aviso de incompleto quando o laudo e simulado', function () {
    $empresa = empresaComPlano();
    $servico = servicoDeDuasBases();

    Http::preventStrayRequests();
    Http::fake([
        '*oauth/token' => Http::response(['access_token' => 'tok']),
        '*consulta' => Http::response(['efxErrorCode' => 404.01, 'description' => 'x'], 404),
    ]);

    app(App\Actions\Consumo\ExecutarConsulta::class)(
        $empresa, $servico, '12345678901', 'Pesquisa de score de crédito',
    );

    $consulta = Consulta::latest('id')->firstOrFail();

    // Laudo simulado ja se declara simulado, e anunciar incompletude por
    // cima vira ruido. Num laudo real o aviso continua obrigatorio.
    expect(App\Support\ConsultaPdf::resultado($consulta->load('servico')))
        ->not->toContain('Resultado incompleto nesta base');

    // Tirando a marca de simulacao, o mesmo laudo passa a avisar.
    $resposta = $consulta->resposta;
    unset($resposta['simulado']);
    $consulta->update(['resposta' => $resposta]);

    expect(App\Support\ConsultaPdf::resultado($consulta->fresh()->load('servico')))
        ->toContain('incompleto');
});

it('a segunda base completa o que faltou, sem sobrescrever', function () {
    // A ordem declarada e a preferencia de quem montou o servico: quem chegou
    // primeiro fica, e a base seguinte so preenche buraco.
    $juntar = new ReflectionMethod(App\Services\Conectores\ConsultarFontes::class, 'juntar');
    $juntar->setAccessible(true);
    $fontes = app(App\Services\Conectores\ConsultarFontes::class);

    $resultado = $juntar->invoke($fontes,
        ['nome' => 'Veio da primeira', 'informacoes_adicionais' => ['a' => 1]],
        ['nome' => 'Veio da segunda', 'score' => 700, 'informacoes_adicionais' => ['b' => 2]],
    );

    expect($resultado['nome'])->toBe('Veio da primeira')
        ->and($resultado['score'])->toBe(700)
        // O deposito do que o mapa nao reconhece acumula de todas as bases.
        ->and($resultado['informacoes_adicionais'])->toBe(['a' => 1, 'b' => 2]);
});
