<?php

use App\Contracts\ConectorBureau;
use App\Models\Conexao;
use App\Models\Servico;
use App\Services\Conectores\ConectorSerasa;
use App\Services\Conectores\ConectorSimulado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * O conector real da Serasa, contra o desenho oficial do portal.
 *
 * O que se protege: o token Basic vira Bearer cacheado, o documento vai no
 * header X-Document-Id e o relatorio no query reportName, a resposta vira o
 * laudo canonico da tela, e NENHUMA falha do fornecedor vira excecao. O
 * container so escolhe este conector quando a conexao esta ativa na tela.
 */
function conexaoSerasa(): Conexao
{
    return Conexao::create([
        'fornecedor' => 'serasa',
        'ambiente' => 'homologacao',
        'credenciais' => ['client_id' => 'cliente-teste', 'client_secret' => 'segredo-teste'],
        'ativa' => true,
    ]);
}

function servicoSerasa(): Servico
{
    return Servico::factory()->create(['codigo_fornecedor' => 'RELATORIO_AVANCADO_PF']);
}

it('consulta o relatorio e traduz a resposta para o laudo', function () {
    conexaoSerasa();
    Http::fake([
        'uat-api.serasaexperian.com.br/security/iam/v1/client-identities/login' => Http::response(['accessToken' => 'token-teste', 'expiresIn' => 3600]),
        'uat-api.serasaexperian.com.br/credit-services/person-information-report/*' => Http::response([
            'reports' => [[
                'registration' => ['consumerName' => 'Fulana de Teste', 'statusRegistration' => 'REGULAR'],
                'score' => ['score' => 712, 'scoreModel' => 'HSPN', 'range' => 'B', 'defaultRate' => '6.2'],
                'negativeData' => [
                    'pefin' => ['summary' => ['count' => 2, 'balance' => 1534.50]],
                    'notary' => ['summary' => ['count' => 1]],
                ],
            ]],
        ]),
    ]);

    $resposta = app(ConectorSerasa::class)->consultar(servicoSerasa(), '12345678901', 'Pesquisa de score');

    expect($resposta->sucesso)->toBeTrue()
        ->and($resposta->dados['score'])->toBe(712)
        ->and($resposta->dados['modelo_do_score'])->toBe('HSPN')
        ->and($resposta->dados['pendencias_pefin'])->toBe(2)
        ->and($resposta->dados['valor_pefin_cents'])->toBe(153_450)
        ->and($resposta->dados['protestos'])->toBe(1);

    // O desenho oficial: Basic no login, documento no header, produto no query.
    Http::assertSent(fn ($r) => str_contains($r->url(), 'client-identities/login')
        && str_starts_with($r->header('Authorization')[0] ?? '', 'Basic '));
    Http::assertSent(fn ($r) => str_contains($r->url(), 'reportName=RELATORIO_AVANCADO_PF')
        && ($r->header('X-Document-Id')[0] ?? null) === '12345678901');
});

it('recusa servico sem relatorio do fornecedor configurado, sem chamar ninguem', function () {
    conexaoSerasa();
    Http::fake();

    $servico = Servico::factory()->create(['codigo_fornecedor' => null]);
    $resposta = app(ConectorSerasa::class)->consultar($servico, '12345678901', 'Pesquisa de score');

    expect($resposta->sucesso)->toBeFalse()
        ->and($resposta->erro)->toContain('relatório do fornecedor');
    Http::assertNothingSent();
});

it('traduz erro do fornecedor em falha, nunca em excecao', function () {
    conexaoSerasa();
    Http::fake([
        '*login*' => Http::response(['accessToken' => 'token-teste']),
        '*creditreport*' => Http::response(null, 404),
    ]);

    $resposta = app(ConectorSerasa::class)->consultar(servicoSerasa(), '12345678901', 'Pesquisa de score');

    expect($resposta->sucesso)->toBeFalse()
        ->and($resposta->erro)->toContain('não encontrado');
});

it('escolhe o conector pela conexao ativa, e o simulado sem nenhuma', function () {
    expect(app(ConectorBureau::class))->toBeInstanceOf(ConectorSimulado::class);

    conexaoSerasa();

    expect(app(ConectorBureau::class))->toBeInstanceOf(ConectorSerasa::class);
});
