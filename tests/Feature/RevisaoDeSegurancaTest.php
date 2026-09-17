<?php

use App\Models\DocumentoLegal;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Documento legal e de quem ele fala.
 *
 * O PDF conferia so se o documento estava ativo. Os termos da equipe tratam de
 * comissao, e os ids sao sequenciais e curtos: uma empresa logada lia o
 * contrato dos vendedores trocando o numero na URL.
 */
it('nao entrega a empresa o documento que e so da equipe', function () {
    $daEquipe = DocumentoLegal::create([
        'tipo' => 'termo-comissao',
        'titulo' => 'Termo de comissão',
        'versao' => '1.0',
        'conteudo' => '## Comissão\n\nO vendedor recebe metade da adesão.',
        'ativo' => true,
        'para_empresa' => false,
        'para_operador' => false,
        'para_vendedor' => true,
    ]);

    $daEmpresa = DocumentoLegal::create([
        'tipo' => 'termo-uso',
        'titulo' => 'Termos de uso',
        'versao' => '1.0',
        'conteudo' => '## Objeto\n\nA Avalia One presta serviços de consulta.',
        'ativo' => true,
        'para_empresa' => true,
        'para_operador' => false,
        'para_vendedor' => false,
    ]);

    $empresa = comoEmpresa(empresaComPlano());

    $empresa->get(route('empresa.documentos.pdf', $daEquipe))->assertNotFound();
    $empresa->get(route('empresa.documentos.pdf', $daEmpresa))->assertOk();
});
