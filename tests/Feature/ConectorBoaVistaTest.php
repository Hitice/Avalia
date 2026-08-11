<?php

use App\Models\Conexao;
use App\Models\Servico;
use App\Services\Conectores\ConectorBoaVista;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * Conector da Equifax Boa Vista, sobre a referencia oficial do portal.
 *
 * Um endpoint so, e o PRODUTO decide o que volta: ampliar o portfolio e
 * cadastro de servico, nao codigo. O que se protege aqui e o desenho da
 * chamada (produto, documento, cabecalhos do contrato) e a promessa do
 * contrato de conector: falha do fornecedor nunca vira excecao.
 */
function conexaoBoaVista(array $extra = []): Conexao
{
    return Conexao::create([
        'fornecedor' => 'boa-vista',
        'ambiente' => 'sandbox',
        'credenciais' => $extra + [
            'client_id' => 'cliente-teste',
            'client_secret' => 'segredo-teste',
            'escopo_relatorios' => 'https://api.equifax.com/business/reporting-orchestrator/v1',
        ],
        'ativa' => true,
    ]);
}

it('consulta o produto do catalogo e traduz para o laudo', function () {
    conexaoBoaVista();
    $servico = Servico::factory()->create(['codigo_fornecedor' => 'ACERTA_COMPLETO_POSITIVO']);

    Http::fake([
        'api.sandbox.equifax.com/v2/oauth/token' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
        'api.sandbox.equifax.com/business/reporting-orchestrator/v1/consulta' => Http::response([
            'protocolo' => 'BV-123456',
            'nome' => 'Fulana de Teste',
            'score' => ['valor' => 688, 'modelo' => 'SCORE_PF'],
            'protestos' => [['valor' => 100], ['valor' => 200]],
        ]),
    ]);

    $resposta = app(ConectorBoaVista::class)->consultar($servico, '12345678901', 'Pesquisa de score');

    expect($resposta->sucesso)->toBeTrue()
        ->and($resposta->dados['score'])->toBe(688)
        ->and($resposta->dados['modelo_do_score'])->toBe('SCORE_PF')
        ->and($resposta->dados['protestos'])->toBe(2)
        ->and($resposta->referenciaExterna)->toBe('BV-123456')
        // O que o mapa ainda nao reconhece nao se perde.
        ->and($resposta->dados)->toHaveKey('informacoes_adicionais');

    // O desenho oficial: produto e documento no corpo, e os dois cabecalhos
    // que o orquestrador exige.
    Http::assertSent(function ($r) {
        if (! str_contains($r->url(), '/consulta')) {
            return false;
        }

        return $r['produto'] === 'ACERTA_COMPLETO_POSITIVO'
            && $r['documento'] === '12345678901'
            && $r['versao'] === 'v1'
            && ($r->header('app')[0] ?? null) === 'API'
            && ($r->header('secondaryCode')[0] ?? null) === '00000000';
    });
});

it('usa os cabecalhos do contrato quando a conexao os define', function () {
    conexaoBoaVista(['app' => 'AVALIA', 'codigo_secundario' => '12345678', 'tipo_credito' => 'CP']);
    $servico = Servico::factory()->create(['codigo_fornecedor' => 'SCPC_NET_PF']);

    Http::fake([
        '*oauth/token' => Http::response(['access_token' => 'tok']),
        '*consulta' => Http::response(['nome' => 'Fulana']),
    ]);

    app(ConectorBoaVista::class)->consultar($servico, '12345678901', 'Pesquisa de score');

    Http::assertSent(fn ($r) => ! str_contains($r->url(), 'oauth')
        && ($r->header('app')[0] ?? null) === 'AVALIA'
        && ($r->header('secondaryCode')[0] ?? null) === '12345678'
        && $r['tipoCredito'] === 'CP');
});

it('recusa servico sem produto configurado, sem chamar ninguem', function () {
    conexaoBoaVista();
    Http::fake();

    $servico = Servico::factory()->create(['codigo_fornecedor' => null]);
    $resposta = app(ConectorBoaVista::class)->consultar($servico, '12345678901', 'Pesquisa de score');

    expect($resposta->sucesso)->toBeFalse()
        ->and($resposta->erro)->toContain('produto do fornecedor');
    Http::assertNothingSent();
});

it('traduz erro do fornecedor em falha, nunca em excecao', function () {
    conexaoBoaVista();
    $servico = Servico::factory()->create(['codigo_fornecedor' => 'SCORE_PF']);

    Http::fake([
        '*oauth/token' => Http::response(['access_token' => 'tok']),
        '*consulta' => Http::response(null, 404),
    ]);

    $resposta = app(ConectorBoaVista::class)->consultar($servico, '12345678901', 'Pesquisa de score');

    expect($resposta->sucesso)->toBeFalse()
        ->and($resposta->erro)->toContain('não encontrado');
});

it('escolhe o conector da Boa Vista quando a conexao dela esta ativa', function () {
    conexaoBoaVista();

    expect(app(App\Contracts\ConectorBureau::class))->toBeInstanceOf(ConectorBoaVista::class);
});

it('nao confunde API ausente com documento inexistente', function () {
    // 404 do Equifax pode ser "documento nao existe" ou "esta API nao existe
    // neste ambiente", que sao problemas opostos. Traduzir os dois igual manda
    // o operador conferir um CNPJ correto enquanto o problema esta na
    // aprovacao da API no portal. Aconteceu.
    conexaoBoaVista();
    $servico = Servico::factory()->create(['codigo_fornecedor' => 'SCPC_NET_PJ']);

    Http::fake([
        '*oauth/token' => Http::response(['access_token' => 'tok']),
        '*consulta' => Http::response([
            'efxErrorCode' => 404.01,
            'description' => 'Not Found - The specified resource does not exist',
        ], 404),
    ]);

    $resposta = app(ConectorBoaVista::class)->consultar($servico, '39914870000101', 'Pesquisa de score');

    expect($resposta->sucesso)->toBeFalse()
        ->and($resposta->erro)->toContain('não está publicada no ambiente configurado')
        ->and($resposta->erro)->not->toContain('Documento não encontrado');
});

it('repassa a descricao do fornecedor quando o erro e desconhecido', function () {
    // Quem escreveu o erro sabe mais sobre ele do que nos, e frase generica
    // manda o operador abrir chamado para descobrir o que o corpo ja dizia.
    conexaoBoaVista();
    $servico = Servico::factory()->create(['codigo_fornecedor' => 'SCPC_NET_PF']);

    Http::fake([
        '*oauth/token' => Http::response(['access_token' => 'tok']),
        '*consulta' => Http::response([
            'efxErrorCode' => 422.07,
            'description' => 'Produto não habilitado para o contrato informado',
        ], 422),
    ]);

    expect(app(ConectorBoaVista::class)->consultar($servico, '12345678901', 'Pesquisa de score')->erro)
        ->toContain('Produto não habilitado para o contrato informado');
});
