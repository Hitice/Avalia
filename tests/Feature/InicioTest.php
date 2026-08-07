<?php

use App\Models\Cliente;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * A unica pagina publica do sistema.
 *
 * O que estes testes guardam nao e o layout: e o que a pagina NAO pode conter.
 * Ela fica exposta a qualquer visitante, entao numero interno, nome de
 * fornecedor ou preco de tabela aparecendo aqui e vazamento para concorrente,
 * nao bug de tela.
 */
it('apresenta a Avalia com as duas saidas', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Venda a prazo')
        ->assertSee('Quero contratar')
        ->assertSee('Já sou cliente')
        ->assertSee('Campanha de adesão');
});

it('vende pesquisa de score com o aviso de uso responsavel, sem termo nublado', function () {
    // O vocabulario da pagina e juridico antes de ser comercial. A Avalia nao
    // empresta, nao garante credito e nao e bureau: "analise de credito" e
    // "consulta de credito" sao promessas juridicamente nubladas e ficam
    // banidas; o que se vende e pesquisa de score amarrada a negocio do
    // proprio contratante, com a decisao declarada como sendo do cliente.
    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('Pesquisa de score')
        ->toContain('venda a prazo')
        ->toContain('Lei 12.414/2011')
        ->toContain('não concede empréstimos')
        // As duas grafias (maiuscula e minuscula) caem na mesma rede.
        ->not->toContain('nálise de crédito')
        ->not->toContain('onsulta de crédito')
        ->not->toContain('onsultas de crédito');
});

it('marca o carrossel de consultas como simulacao e mascara os documentos', function () {
    // A vitrine mostra consultas acontecendo, e por isso mesmo precisa dizer
    // que e simulacao: pagina publica exibindo consulta que parecesse real
    // seria exatamente o vazamento que o produto promete impedir.
    $resposta = $this->get('/')->assertOk()
        ->assertSee('Simulação')
        ->assertSee('Casa Sul Materiais');

    // Nenhum documento inteiro: todo CPF e CNPJ do carrossel leva mascara.
    $html = $resposta->getContent();

    expect(preg_match('/\d{3}\.\d{3}\.\d{3}-\d{2}/', $html))->toBe(0)
        ->and($html)->toContain('***.')
        ->toContain('/0001-**');
});

it('nao vaza fornecedor, preco nem numero interno', function () {
    $html = $this->get('/')->assertOk()->getContent();

    // Nomes de fornecedor nunca aparecem fora da administracao (PDD, secao 7),
    // e a pagina publica e o pior lugar possivel para o primeiro deslize.
    expect($html)->not->toContain('Boa Vista')
        ->not->toContain('Equifax')
        ->not->toContain('SPC')
        ->not->toContain('Serasa')
        // Preco e proposta moram atras do login, com contrato e catalogo.
        ->not->toContain('R$')
        ->not->toContain('Custo')
        ->not->toContain('Margem');
});

it('recolhe o interesse da campanha por formulario, e nao por URL de conversa', function () {
    $html = $this->get('/')->assertOk()->getContent();

    // A campanha abre um formulario que grava no nosso banco: nome e telefone
    // sao dado pessoal e nao viajam em URL de WhatsApp. Os links de conversa
    // que restam levam so o assunto.
    expect($html)->toContain(route('interesse.salvar'))
        ->toContain('Enviar pedido de contato')
        ->toContain('wa.me/')
        ->toContain(rawurlencode('Quero contratar a Avalia'));
});

it('veste o banner com a campanha vigente', function () {
    App\Models\Campanha::create([
        'nome' => 'Adesão de agosto',
        'oferta' => 'Taxa de adesão facilitada para quem contratar até o fim do mês.',
        'inicio' => today()->subDay(),
        'fim' => today()->addDays(20),
        'ativa' => true,
    ]);

    $this->get('/')->assertOk()
        ->assertSee('Adesão de agosto')
        ->assertSee('Taxa de adesão facilitada para quem contratar até o fim do mês.');
});

it('ignora campanha desligada ou fora do periodo', function () {
    App\Models\Campanha::create([
        'nome' => 'Campanha desligada',
        'oferta' => 'Não deveria aparecer.',
        'inicio' => today()->subDay(),
        'fim' => null,
        'ativa' => false,
    ]);
    App\Models\Campanha::create([
        'nome' => 'Campanha encerrada',
        'oferta' => 'Também não.',
        'inicio' => today()->subMonths(2),
        'fim' => today()->subMonth(),
        'ativa' => true,
    ]);

    $this->get('/')->assertOk()
        ->assertDontSee('Campanha desligada')
        ->assertDontSee('Campanha encerrada')
        ->assertSee('Campanha de adesão aberta');
});

it('segura fora da vitrine campanha cujo texto vaza preco ou fornecedor', function () {
    // A regra da pagina publica precisa valer em execucao, nao so no teste:
    // campanha e texto livre da administracao, e um "adesao por R$ 99" bem
    // intencionado nao pode furar a vitrine.
    App\Models\Campanha::create([
        'nome' => 'Promoção de adesão',
        'oferta' => 'Adesão por R$ 99 para novos clientes.',
        'inicio' => today()->subDay(),
        'fim' => null,
        'ativa' => true,
    ]);

    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->not->toContain('R$')
        ->toContain('Campanha de adesão aberta');
});

it('leva cada sessao para o proprio painel sem mostrar a apresentacao', function () {
    $staff = Staff::factory()->admin()->create();
    $this->actingAs($staff, 'staff')->get('/')->assertRedirect(route('painel'));

    $this->flushSession();
    app('auth')->forgetGuards();

    $empresa = Cliente::factory()->create();
    $this->actingAs($empresa, 'empresa')->get('/')->assertRedirect(route('empresa.painel'));
});
