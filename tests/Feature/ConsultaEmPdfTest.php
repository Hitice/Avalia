<?php

use App\Models\Servico;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * O resultado da consulta em arquivo, no portal do cliente.
 *
 * O PDF e o unico jeito aprovado de o resultado sair da tela: leva protocolo,
 * documento mascarado e carimbo de quem emitiu, o que a captura de tela nao
 * tem. O que se protege aqui e o cerco: so a empresa dona da consulta baixa, e
 * consulta expurgada nao vira arquivo, porque o conteudo ja nao existe.
 */
it('entrega o resultado em PDF para a empresa que consultou', function () {
    $empresa = empresaComPlano();
    $servico = Servico::firstWhere('codigo', 'scpc-bvs');
    $consulta = consultaDe($empresa, $servico);

    $resposta = comoEmpresa($empresa)->get(route('empresa.consultas.pdf', $consulta))->assertOk();

    expect($resposta->headers->get('content-type'))->toContain('application/pdf')
        ->and($resposta->getContent())->toStartWith('%PDF');
});

it('nao entrega o PDF da consulta de outra empresa', function () {
    $dona = empresaComPlano();
    $outra = empresaComPlano(['cnpj' => '11222333000181', 'email' => 'outra@teste.com.br']);
    $servico = Servico::firstWhere('codigo', 'scpc-bvs');
    $consulta = consultaDe($dona, $servico);

    comoEmpresa($outra)->get(route('empresa.consultas.pdf', $consulta))->assertForbidden();
});

it('nao entrega PDF de consulta expurgada', function () {
    $empresa = empresaComPlano();
    $servico = Servico::firstWhere('codigo', 'scpc-bvs');
    $consulta = consultaDe($empresa, $servico);

    $consulta->update(['resposta' => null, 'expurgada_em' => now()]);

    comoEmpresa($empresa)->get(route('empresa.consultas.pdf', $consulta))->assertNotFound();
});

it('oferece o download na tela do resultado', function () {
    $empresa = empresaComPlano();
    $servico = Servico::firstWhere('codigo', 'scpc-bvs');
    $consulta = consultaDe($empresa, $servico);

    comoEmpresa($empresa)->get(route('empresa.consultas.ver', $consulta))
        ->assertOk()
        ->assertSee(route('empresa.consultas.pdf', $consulta), false);
});

/** Uma consulta concluida da empresa, pelo caminho de producao. */
function consultaDe(App\Models\Cliente $empresa, Servico $servico): App\Models\Consulta
{
    app(App\Actions\Consumo\RegistrarConsulta::class)($empresa, $servico, 1);

    return App\Models\Consulta::where('cliente_id', $empresa->id)->latest('id')->firstOrFail();
}
