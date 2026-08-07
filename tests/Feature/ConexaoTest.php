<?php

use App\Models\Auditoria;
use App\Models\Conexao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * O cofre de credenciais dos servicos externos.
 *
 * O que se protege: credencial entra criptografada e nunca aparece em claro
 * (nem no banco, nem na tela, nem na trilha), segredo em branco significa
 * manter e nao apagar, e o AsaasClient passa a ler daqui quando ha conexao
 * ativa, com o .env de reserva.
 */
function conexaoAsaas(array $credenciais = [], bool $ativa = true): Conexao
{
    return Conexao::create([
        'fornecedor' => 'asaas',
        'ambiente' => 'homologacao',
        'credenciais' => $credenciais + ['api_key' => '$aact_hmlg_chave-de-teste', 'webhook_token' => 'token-webhook-teste'],
        'ativa' => $ativa,
    ]);
}

it('abre a tela de conexoes com os fornecedores suportados', function () {
    admin()->get(route('conexoes.index'))->assertOk()
        ->assertSee('Asaas')
        ->assertSee('Serasa Experian')
        ->assertSee('SPC Brasil')
        ->assertSee('Equifax | Boa Vista')
        ->assertSee('Consulta veicular')
        ->assertSee('SCR · Banco Central');
});

it('nao deixa o vendedor abrir nem mexer nas conexoes', function () {
    [$vendedor] = carteira();

    comoVendedor($vendedor)->get(route('conexoes.index'))->assertForbidden();
    comoVendedor($vendedor)->put(route('conexoes.atualizar', 'asaas'), ['campo_api_key' => 'x'])->assertForbidden();
});

it('grava a credencial criptografada e nunca em claro', function () {
    admin()->put(route('conexoes.atualizar', 'asaas'), [
        'campo_api_key' => '$aact_prod_chave-secreta-123',
        'ambiente' => 'producao',
    ])->assertRedirect()->assertSessionHas('ok');

    $conexao = Conexao::sole();

    expect($conexao->credenciais['api_key'])->toBe('$aact_prod_chave-secreta-123')
        ->and($conexao->ambiente)->toBe('producao');

    // No banco, so o blob criptografado.
    $cru = (string) DB::table('conexoes')->value('credenciais');
    expect($cru)->not->toContain('chave-secreta-123');

    // Na trilha, quais campos mudaram e nunca o valor.
    $registro = Auditoria::where('acao', 'conexao.atualizada')->sole();
    expect(json_encode($registro->dados))->toContain('api_key')
        ->not->toContain('chave-secreta-123');
});

it('mantem o segredo quando o campo vem em branco', function () {
    conexaoAsaas(ativa: false);

    admin()->put(route('conexoes.atualizar', 'asaas'), [
        'campo_api_key' => '',
        'campo_webhook_token' => '',
        'ambiente' => 'homologacao',
    ])->assertSessionHas('ok');

    expect(Conexao::sole()->credenciais['api_key'])->toBe('$aact_hmlg_chave-de-teste');
});

it('so ativa conexao que tem credencial', function () {
    admin()->post(route('conexoes.alternar', 'serasa'))->assertSessionHas('erro');
    expect(Conexao::where('fornecedor', 'serasa')->first()?->ativa)->not->toBeTrue();

    conexaoAsaas(ativa: false);
    admin()->post(route('conexoes.alternar', 'asaas'))->assertSessionHas('ok');
    expect(Conexao::where('fornecedor', 'asaas')->sole()->ativa)->toBeTrue();
});

it('faz o AsaasClient usar a credencial e o ambiente da conexao ativa', function () {
    conexaoAsaas();
    Http::fake(['*' => Http::response(['id' => 'cus_1'], 200)]);

    app(App\Services\AsaasClient::class)->criarCliente(['name' => 'Empresa X']);

    Http::assertSent(function ($requisicao) {
        return str_starts_with($requisicao->url(), 'https://api-sandbox.asaas.com/v3')
            && $requisicao->header('access_token') === ['$aact_hmlg_chave-de-teste'];
    });
});

it('testa a conexao com o Asaas e grava o resultado', function () {
    conexaoAsaas();
    Http::fake(['*' => Http::response(['data' => []], 200)]);

    admin()->post(route('conexoes.testar', 'asaas'))->assertSessionHas('ok');

    $conexao = Conexao::sole();
    expect($conexao->teste_ok)->toBeTrue()
        ->and($conexao->testada_em)->not->toBeNull();
});

it('valida o webhook com o token guardado na conexao', function () {
    conexaoAsaas();

    $this->postJson(route('webhooks.asaas'), ['event' => 'PAYMENT_RECEIVED'])
        ->assertForbidden();

    $this->withHeaders(['asaas-access-token' => 'token-webhook-teste'])
        ->postJson(route('webhooks.asaas'), ['event' => 'PAYMENT_RECEIVED'])
        ->assertOk();
});
