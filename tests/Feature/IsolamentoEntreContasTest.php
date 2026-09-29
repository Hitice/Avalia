<?php

use App\Models\Oferta360;
use App\Models\Operador;
use App\Models\Pedido360;
use App\Models\Produto360;
use App\Models\Produtor;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Nenhuma conta alcanca o que e de outra
|--------------------------------------------------------------------------
|
| RegistroAlheioTest ja cobre empresa contra empresa e vendedor contra
| carteira alheia. O que faltava era o eixo mais novo: produtor contra
| produtor, e operador contra empresa que nao e a dele.
|
| Painel que filtra certo hoje pode passar a nao filtrar amanha sem ninguem
| notar, porque o sintoma e dado de outro aparecendo numa tela que continua
| carregando normalmente. Estes testes existem para o sintoma virar falha.
|
*/

function produtorCom(string $nome, string $email): Produtor
{
    return Produtor::create([
        'nome' => $nome,
        'documento' => '12345678909',
        'whatsapp' => '34999112233',
        'email' => $email,
        'situacao' => 'aprovado',
        'asaas_wallet_id' => 'wallet-'.$email,
    ]);
}

function pedidoDe(Produtor $produtor, string $cliente): Pedido360
{
    $produto = Produto360::create([
        'produtor_id' => $produtor->id,
        'nome' => 'Curso',
        'valor_cents' => 300_000,
    ]);

    $oferta = Oferta360::create([
        'produto_360_id' => $produto->id,
        'titulo' => 'Curso em 12x',
        'valor_cents' => 300_000,
        'parcelas' => 12,
        'entrada_cents' => 30_000,
        'entrada_em_dias' => 7,
    ]);

    return Pedido360::create([
        'oferta_360_id' => $oferta->id,
        'produtor_id' => $produtor->id,
        'cliente_nome' => $cliente,
        'cliente_documento' => '12345678909',
        'cliente_email' => 'aluno@exemplo.com.br',
        'cliente_telefone' => '34999887766',
        'valor_total_cents' => 300_000,
        'entrada_cents' => 30_000,
        'parcelas' => 12,
        'valor_parcela_cents' => 22_500,
        'taxa_bps' => 500,
    ]);
}

/*
|--------------------------------------------------------------------------
| Produtor contra produtor
|--------------------------------------------------------------------------
*/

it('nao mostra ao produtor o pedido de outro', function () {
    $meu = produtorCom('Escola Costa', 'costa@escola.com.br');
    $outro = produtorCom('Escola Rival', 'rival@escola.com.br');

    $meuPedido = pedidoDe($meu, 'Cliente meu');
    $pedidoAlheio = pedidoDe($outro, 'Cliente do rival');

    $painel = test()->actingAs($meu, 'produtor')->withSession(['versao_produtor' => 1])
        ->get(route('produtor.painel'))->assertOk();

    $ids = $painel->viewData('pedidos')->pluck('id');

    expect($ids)->toContain($meuPedido->id)
        ->and($ids)->not->toContain($pedidoAlheio->id);
});

it('nao mostra na tela o nome do cliente de outro produtor', function () {
    // O filtro pode estar certo na consulta e errado na view: um `with` sem
    // escopo, um total somado fora do recorte. A tela e o que a pessoa le.
    $meu = produtorCom('Escola Costa', 'costa@escola.com.br');
    $outro = produtorCom('Escola Rival', 'rival@escola.com.br');

    pedidoDe($meu, 'Cliente meu');
    pedidoDe($outro, 'Cliente do rival');

    test()->actingAs($meu, 'produtor')->withSession(['versao_produtor' => 1])
        ->get(route('produtor.painel'))
        ->assertOk()
        ->assertSee('Cliente meu')
        ->assertDontSee('Cliente do rival');
});

it('nao soma no a receber do produtor a parcela de outro', function () {
    // Dinheiro somado fora do recorte e o pior dos vazamentos: ninguem ve nome
    // nenhum, e o numero esta errado.
    $meu = produtorCom('Escola Costa', 'costa@escola.com.br');
    $outro = produtorCom('Escola Rival', 'rival@escola.com.br');

    pedidoDe($meu, 'Cliente meu');
    pedidoDe($outro, 'Cliente do rival');

    $doMeu = test()->actingAs($meu, 'produtor')->withSession(['versao_produtor' => 1])
        ->get(route('produtor.painel'));

    $doOutro = test()->actingAs($outro, 'produtor')->withSession(['versao_produtor' => 1])
        ->get(route('produtor.painel'));

    // Pedidos identicos: se um enxergasse o do outro, os totais divergiriam do
    // de quem tem um pedido so.
    expect($doMeu->viewData('pedidos'))->toHaveCount(1)
        ->and($doOutro->viewData('pedidos'))->toHaveCount(1);
});

it('nao deixa o produtor entrar nas telas de staff nem de empresa', function () {
    $produtor = produtorCom('Escola Costa', 'costa@escola.com.br');

    $sessao = test()->actingAs($produtor, 'produtor')->withSession(['versao_produtor' => 1]);

    $sessao->get(route('painel'))->assertRedirect();
    $sessao->get(route('empresa.painel'))->assertRedirect();
});

/*
|--------------------------------------------------------------------------
| Operador contra empresa que nao e a dele
|--------------------------------------------------------------------------
*/

it('derruba a sessao quando o operador nao e da empresa autenticada', function () {
    // O caso que a guarda existe para pegar: marca de operador de uma empresa
    // dentro de sessao aberta por OUTRA. Sem a conferencia, a pessoa consultaria
    // em nome de quem ela nao atende, e a consulta ficaria gravada com o nome
    // dela na empresa errada.
    $minha = empresaComPlano();
    $outra = empresaComPlano(['email' => 'outra@empresa.com.br']);

    $meuOperador = Operador::factory()->create(['cliente_id' => $minha->id]);

    test()->actingAs($outra, 'empresa')->withSession([
        'versao_empresa' => $outra->fresh()->sessao_versao,
        'operador_id' => $meuOperador->id,
        'versao_operador' => $meuOperador->fresh()->sessao_versao,
    ])->get(route('empresa.painel'))->assertRedirect();
});

it('deixa o operador da propria empresa entrar', function () {
    // O lado positivo da mesma regra: sem ele, o teste acima passaria com a
    // area do cliente quebrada para todo mundo.
    $empresa = empresaComPlano();
    $operador = Operador::factory()->create(['cliente_id' => $empresa->id]);

    $painel = test()->actingAs($empresa, 'empresa')->withSession([
        'versao_empresa' => $empresa->fresh()->sessao_versao,
        'operador_id' => $operador->id,
        'versao_operador' => $operador->fresh()->sessao_versao,
    ])->get(route('empresa.painel'))->assertOk();

    expect($painel->viewData('empresa')->id)->toBe($empresa->id);
});

it('derruba o operador desativado sem tocar na conta master', function () {
    $empresa = empresaComPlano();
    $operador = Operador::factory()->create(['cliente_id' => $empresa->id]);

    $operador->update(['ativo' => false]);

    test()->actingAs($empresa, 'empresa')->withSession([
        'versao_empresa' => $empresa->fresh()->sessao_versao,
        'operador_id' => $operador->id,
        'versao_operador' => $operador->fresh()->sessao_versao,
    ])->get(route('empresa.painel'))->assertRedirect();

    // A conta master continua entrando: desativar uma pessoa nao fecha a empresa.
    comoEmpresa($empresa)->get(route('empresa.painel'))->assertOk();
});
