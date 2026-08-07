<?php

use App\Models\AceiteDocumento;
use App\Models\DocumentoLegal;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * PDF dos documentos e a robustez do aceite.
 *
 * O PDF e gerado a mao, sem dependencia, entao o teste confere o que importa
 * num arquivo: comeca com a assinatura do formato e termina com o EOF. O
 * aceite virou evidencia: nome de quem clicou, confirmacao de leitura e o
 * hash do texto que estava na tela, conferido no ato.
 */
function documentoVigente(array $extra = []): DocumentoLegal
{
    return DocumentoLegal::create($extra + [
        'tipo' => 'contrato',
        'versao' => '1.0',
        'titulo' => 'Contrato de prestação de serviços',
        'conteudo' => "## Objeto\n\nA Avalia presta serviços de consulta à contratante.\n\n## Vigência\n\nPrazo definido na proposta comercial.",
        'exige_aceite' => true,
        'ativo' => true,
    ]);
}

it('recebe a empresa nos documentos enquanto ha aceite pendente', function () {
    // O contrato e a primeira tela: o aceite nao espera a pessoa achar o menu.
    $empresa = empresaComPlano(['email' => 'fin@pendente.com.br']);
    $documento = documentoVigente();

    $this->post('/entrar', ['email' => 'fin@pendente.com.br', 'senha' => 'senha-valida-123'])
        ->assertRedirect(route('empresa.documentos'));

    // Aceito o documento, a proxima entrada volta ao painel.
    comoEmpresa($empresa)->post(route('empresa.documentos.aceitar', $documento), aceiteValido($documento));

    $this->flushSession();
    app('auth')->forgetGuards();

    $this->post('/entrar', ['email' => 'fin@pendente.com.br', 'senha' => 'senha-valida-123'])
        ->assertRedirect(route('empresa.painel'));
});

it('abre a leitura no popup com o aceite destravando no fim', function () {
    // Como termo de aparelho novo: o texto abre em popup com rolagem vigiada
    // e o aceite so destrava no fim da leitura. O texto exibido e o mesmo que
    // gera o PDF, e o hash fecha a janela entre ler e aceitar.
    $empresa = empresaComPlano();
    documentoVigente();

    comoEmpresa($empresa)->get(route('empresa.documentos'))->assertOk()
        ->assertSee('Ler e aceitar')
        ->assertSee('Role até o fim para aceitar')
        ->assertSee('A Avalia presta serviços de consulta à contratante.');
});

it('entrega o documento em PDF valido para a empresa', function () {
    $empresa = empresaComPlano();
    $documento = documentoVigente();

    $resposta = comoEmpresa($empresa)->get(route('empresa.documentos.pdf', $documento))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    $bytes = $resposta->getContent();

    expect(str_starts_with($bytes, '%PDF-1.4'))->toBeTrue()
        ->and(str_ends_with($bytes, '%%EOF'))->toBeTrue();
});

it('entrega o mesmo PDF para a administracao', function () {
    $documento = documentoVigente();

    $bytes = admin()->get(route('documentos.pdf', $documento))->assertOk()->getContent();

    expect(str_starts_with($bytes, '%PDF-1.4'))->toBeTrue();
});

it('recusa aceite sem nome, sem confirmacao de leitura ou sem hash', function () {
    $empresa = empresaComPlano();
    $documento = documentoVigente();

    comoEmpresa($empresa)->from(route('empresa.documentos'))
        ->post(route('empresa.documentos.aceitar', $documento), [])
        ->assertSessionHasErrors(['responsavel', 'li', 'hash']);

    expect(AceiteDocumento::count())->toBe(0);
});

it('recusa aceite quando o documento mudou entre a leitura e o clique', function () {
    // O hash enviado e o do texto que estava na tela. Texto novo, hash velho:
    // a pessoa estaria aceitando as cegas um documento que nao leu.
    $empresa = empresaComPlano();
    $documento = documentoVigente();

    $payload = aceiteValido($documento);

    $documento->update(['conteudo' => $documento->conteudo."\n\n## Cláusula nova\n\nIncluída depois da leitura."]);

    comoEmpresa($empresa)->post(route('empresa.documentos.aceitar', $documento), $payload)
        ->assertSessionHas('erro');

    expect(AceiteDocumento::count())->toBe(0);
});

it('grava no aceite quem aceitou', function () {
    $empresa = empresaComPlano();
    $documento = documentoVigente();

    comoEmpresa($empresa)->post(route('empresa.documentos.aceitar', $documento), aceiteValido($documento))
        ->assertSessionHas('ok');

    $aceite = AceiteDocumento::sole();

    expect($aceite->responsavel)->toBe('Fulana Responsável de Teste')
        ->and($aceite->hash_conteudo)->toBe($documento->hashConteudo());
});

it('emite o comprovante em PDF depois do aceite, e so depois', function () {
    $empresa = empresaComPlano();
    $documento = documentoVigente();

    // Sem aceite, nao ha o que comprovar.
    comoEmpresa($empresa)->get(route('empresa.documentos.comprovante', $documento))->assertNotFound();

    comoEmpresa($empresa)->post(route('empresa.documentos.aceitar', $documento), aceiteValido($documento));

    $bytes = comoEmpresa($empresa)->get(route('empresa.documentos.comprovante', $documento))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->getContent();

    expect(str_starts_with($bytes, '%PDF-1.4'))->toBeTrue();
});

it('nao entrega comprovante de aceite alheio', function () {
    $minha = empresaComPlano();
    $alheia = empresaComPlano();
    $documento = documentoVigente();

    comoEmpresa($alheia)->post(route('empresa.documentos.aceitar', $documento), aceiteValido($documento));

    // O aceite existe, mas e da outra empresa: para a minha, nao ha comprovante.
    comoEmpresa($minha)->get(route('empresa.documentos.comprovante', $documento))->assertNotFound();
});
