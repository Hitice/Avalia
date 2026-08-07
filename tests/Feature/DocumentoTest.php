<?php

use App\Models\AceiteDocumento;
use App\Models\Auditoria;
use App\Models\Cliente;
use App\Models\DocumentoLegal;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('mostra o documento e preserva a prova do aceite da empresa', function () {
    $cliente = Cliente::factory()->create();
    $documento = DocumentoLegal::create([
        'titulo' => 'Termo de confidencialidade',
        'tipo' => 'confidencialidade',
        'versao' => '2026.08',
        'conteudo' => 'Texto do termo para leitura antes do aceite.',
        'exige_aceite' => true,
        'ativo' => true,
    ]);

    $sessao = $this->actingAs($cliente, 'empresa')->withSession(['versao_empresa' => $cliente->sessao_versao]);
    // A leitura e o PDF, o mesmo arquivo que fica de evidencia: a tela lista
    // e aponta, nao duplica o texto.
    $sessao->get(route('empresa.documentos'))
        ->assertOk()
        ->assertSee('Termo de confidencialidade')
        ->assertSee(route('empresa.documentos.pdf', $documento));

    $sessao->post(route('empresa.documentos.aceitar', $documento), aceiteValido($documento))
        ->assertRedirect()
        ->assertSessionHas('ok');

    $aceite = AceiteDocumento::first();

    expect($aceite->cliente_id)->toBe($cliente->id)
        ->and($aceite->versao)->toBe('2026.08')
        ->and($aceite->hash_conteudo)->toBe(hash('sha256', $documento->conteudo))
        ->and(Auditoria::where('acao', 'documento.aceito')->count())->toBe(1);
});
