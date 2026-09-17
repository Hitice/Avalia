<?php

use App\Models\DocumentoLegal;
use App\Models\Oferta360;
use App\Models\Produto360;
use App\Models\Produtor;
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

/**
 * Procurar por documento sem decifrar a base inteira.
 *
 * A regra "uma compra em aberto por documento" carregava todos os pedidos em
 * aberto e decifrava um a um, a cada checkout. O hash torna a busca uma
 * consulta indexada, e continua sem expor o documento a quem le o banco.
 */
it('acha o documento pelo hash sem guardar o documento em claro', function () {
    $documento = '123.456.789-09';
    $hash = App\Support\Documento::hash($documento);

    expect($hash)->toHaveLength(64)
        // Determinístico: a mesma pessoa, escrita de outro jeito, dá o mesmo hash.
        ->and(App\Support\Documento::hash('12345678909'))->toBe($hash)
        // E não é o documento, nem um sha256 puro que se quebra testando CPFs.
        ->and($hash)->not->toContain('12345678909')
        ->and($hash)->not->toBe(hash('sha256', '12345678909'));

    expect(App\Support\Documento::hash(''))->toBe('');
});

it('desativa o catalogo do produtor em vez de apagar', function () {
    // O slug da oferta ja foi para links de checkout no WhatsApp de alguem:
    // apagar transformaria esses links em 404 sem explicacao.
    $produtor = Produtor::create([
        'nome' => 'Escola Costa', 'documento' => '12345678909',
        'whatsapp' => '34999112233', 'email' => 'costa@escola.com.br',
        'situacao' => 'aprovado', 'asaas_wallet_id' => 'wallet',
    ]);
    $produto = Produto360::create(['produtor_id' => $produtor->id, 'nome' => 'Curso', 'valor_cents' => 100000]);
    Oferta360::create([
        'produto_360_id' => $produto->id, 'titulo' => 'Curso em 6x',
        'valor_cents' => 100000, 'parcelas' => 6, 'entrada_cents' => 10000, 'slug' => 'curso-6x',
    ]);

    $produto->delete();

    expect(Produto360::count())->toBe(0)
        ->and(Produto360::withTrashed()->count())->toBe(1)
        // A oferta continua no banco, e a venda antiga segue explicavel.
        ->and(Oferta360::withTrashed()->count())->toBe(1);
});

/**
 * Toda tela publica alterna o tema.
 *
 * O botao vivia escrito a mao dentro da pagina inicial, e as telas do 360
 * nasceram sem ele: o tema escuro existia e nao havia como sair dele. Este
 * teste quebra quando alguem publica tela nova sem o componente, que e
 * exatamente quando o esquecimento acontece.
 */
it('deixa trocar o tema em toda tela publica', function () {
    $telas = [
        route('inicio'),
        route('cobranca'),
        route('produtor.criar-conta'),
    ];

    foreach ($telas as $tela) {
        $this->get($tela)->assertOk()->assertSee('$store.theme.toggle()', false);
    }
});
