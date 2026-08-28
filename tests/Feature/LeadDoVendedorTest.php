<?php

use App\Actions\Prospeccao\CompartilharLeads;
use App\Enums\SituacaoLead;
use App\Models\Auditoria;
use App\Models\Cliente;
use App\Models\Lead;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * O lead na mao de quem prospecta, e o caminho dele ate virar cliente.
 *
 * Duas regras respondem por quase tudo aqui:
 *
 *   o recorte sai do vinculo, nunca da URL, entao nao existe endereco que peca
 *   o lead de outro vendedor;
 *   converter nao e um segundo cadastro, e o mesmo formulario de empresa com a
 *   ficha copiada, entao a validacao, o convite e a carteira sao os de sempre.
 */

/** Um lead compartilhado com o vendedor, ja com a ficha completa. */
function leadDe(Staff $vendedor, array $atributos = []): Lead
{
    $lead = Lead::factory()->pronto()->create($atributos);

    app(CompartilharLeads::class)([$lead->id], $vendedor);

    return $lead->fresh();
}

/*
|--------------------------------------------------------------------------
| So os proprios
|--------------------------------------------------------------------------
*/

it('abre a ficha do lead que e dele', function () {
    [$vendedor] = carteira();
    $lead = leadDe($vendedor, ['nome' => 'PADARIA DO BAIRRO LTDA']);

    comoVendedor($vendedor)->get(route('carteira.leads.editar', $lead))
        ->assertOk()
        ->assertSee('PADARIA DO BAIRRO LTDA')
        ->assertSee('Situação');
});

it('nao abre nem grava a ficha de lead que nao e dele', function () {
    [$vendedor] = carteira();
    $outro = Staff::factory()->create(['papel' => 'vendedor']);
    $doOutro = leadDe($outro);

    comoVendedor($vendedor)->get(route('carteira.leads.editar', $doOutro))->assertNotFound();
    comoVendedor($vendedor)->put(route('carteira.leads.atualizar', $doOutro), [
        'nome' => 'INVADIDO LTDA', 'situacao' => 'atendendo',
    ])->assertNotFound();
    comoVendedor($vendedor)->get(route('carteira.leads.converter', $doOutro))->assertNotFound();

    expect($doOutro->fresh()->nome)->not->toBe('INVADIDO LTDA');
});

it('fecha a ficha do lead para a empresa contratante', function () {
    [$vendedor] = carteira();
    $lead = leadDe($vendedor);

    comoEmpresa(empresaComPlano())->get(route('carteira.leads.editar', $lead))
        ->assertRedirect(route('entrar'));
});

/*
|--------------------------------------------------------------------------
| Corrigir a ficha
|--------------------------------------------------------------------------
*/

/**
 * Quem liga e quem descobre o que a base nao tinha. Sem esta porta o dado
 * morria no caderno do vendedor, e a venda fechada virava entrevista de
 * cadastro com o cliente esperando na linha.
 */
it('corrige o cadastro que a base trouxe errado ou incompleto', function () {
    [$vendedor] = carteira();
    $lead = leadDe($vendedor, ['telefone' => '(31) 0000-0000', 'responsavel_nome' => null]);

    comoVendedor($vendedor)->put(route('carteira.leads.atualizar', $lead), [
        'nome' => 'PADARIA DO BAIRRO LTDA',
        'cnpj' => '08.876.860/0001-03',
        'email' => 'FALEI@Padaria.com.br',
        'telefone' => '(31) 99887-7665',
        'responsavel_nome' => 'Dona Marta',
        'responsavel_cpf' => '123.456.789-09',
        'cep' => '30110-005',
        'logradouro' => 'Rua das Flores',
        'numero' => '120',
        'bairro' => 'Centro',
        'cidade' => 'Belo Horizonte',
        'uf' => 'mg',
        'situacao' => 'atendendo',
        'observacao' => 'Falei com a Dona Marta. Pediu proposta por e-mail.',
    ])->assertRedirect(route('carteira.leads'));

    $lead->refresh();

    expect($lead->telefone)->toBe('(31) 99887-7665')
        // Normalizacoes que o cadastro de cliente espera, feitas aqui para a
        // conversao ser copia e nao limpeza de dado.
        ->and($lead->email)->toBe('falei@padaria.com.br')
        ->and($lead->cnpj)->toBe('08876860000103')
        ->and($lead->responsavel_cpf)->toBe('12345678909')
        ->and($lead->cep)->toBe('30110005')
        ->and($lead->uf)->toBe('MG')
        ->and($lead->situacao)->toBe(SituacaoLead::Atendendo);
});

it('nao deixa o vendedor mexer na procedencia do lead', function () {
    [$vendedor] = carteira();
    $lead = leadDe($vendedor, ['codigo' => '13694', 'origem' => '1.pdf']);

    comoVendedor($vendedor)->put(route('carteira.leads.atualizar', $lead), [
        'nome' => $lead->nome,
        'situacao' => 'atendendo',
        'codigo' => '99999',
        'origem' => 'inventada',
    ])->assertRedirect(route('carteira.leads'));

    $lead->refresh();

    expect($lead->codigo)->toBe('13694')
        ->and($lead->origem)->toBe('1.pdf');
});

/*
|--------------------------------------------------------------------------
| O funil
|--------------------------------------------------------------------------
*/

it('registra o agendamento com a data da reuniao', function () {
    [$vendedor] = carteira();
    $lead = leadDe($vendedor);

    comoVendedor($vendedor)->put(route('carteira.leads.atualizar', $lead), [
        'nome' => $lead->nome,
        'situacao' => 'agendado',
        'agendado_para' => '2026-09-03T14:30',
        'observacao' => 'Reunião com o sócio.',
    ])->assertSessionHas('ok');

    $lead->refresh();

    expect($lead->situacao)->toBe(SituacaoLead::Agendado)
        ->and($lead->agendado_para->format('d/m/Y H:i'))->toBe('03/09/2026 14:30');
});

/** "Agendado" sem quando nao serve a quem abre a tela amanha, que e o dono dela. */
it('recusa agendamento sem data', function () {
    [$vendedor] = carteira();
    $lead = leadDe($vendedor);

    comoVendedor($vendedor)->from(route('carteira.leads.editar', $lead))
        ->put(route('carteira.leads.atualizar', $lead), [
            'nome' => $lead->nome,
            'situacao' => 'agendado',
            'agendado_para' => '',
        ])
        ->assertSessionHasErrors(['agendado_para' => 'Informe a data do agendamento.']);

    expect($lead->fresh()->situacao)->toBe(SituacaoLead::Novo);
});

/** Reuniao que nao aconteceu nao pode ficar para sempre na fila de atrasados. */
it('limpa a data quando o lead sai de agendado', function () {
    [$vendedor] = carteira();
    $lead = leadDe($vendedor);
    $lead->update(['situacao' => SituacaoLead::Agendado, 'agendado_para' => now()->subDay()]);

    comoVendedor($vendedor)->put(route('carteira.leads.atualizar', $lead), [
        'nome' => $lead->nome,
        'situacao' => 'recusado',
        'observacao' => 'Disse que já tem fornecedor.',
    ])->assertSessionHas('ok');

    $lead->refresh();

    expect($lead->situacao)->toBe(SituacaoLead::Recusado)
        ->and($lead->agendado_para)->toBeNull();
});

/**
 * "Nao atender" e decisao da casa e esconde o lead da distribuicao; "virou
 * cliente" quem marca e a conversao. Nas maos de quem prospecta, o segundo
 * criaria lead convertido sem cliente do outro lado.
 */
it('nao deixa o vendedor bloquear nem declarar cliente por conta propria', function () {
    [$vendedor] = carteira();
    $lead = leadDe($vendedor);

    foreach (['bloqueado', 'convertido'] as $proibida) {
        comoVendedor($vendedor)->from(route('carteira.leads.editar', $lead))
            ->put(route('carteira.leads.atualizar', $lead), [
                'nome' => $lead->nome, 'situacao' => $proibida,
            ])
            ->assertSessionHasErrors('situacao');
    }

    expect($lead->fresh()->situacao)->toBe(SituacaoLead::Novo);

    // A administracao alcanca os seis.
    admin()->put(route('leads.atualizar', $lead), [
        'nome' => $lead->nome, 'situacao' => 'bloqueado',
    ])->assertRedirect(route('leads.index'));

    expect($lead->fresh()->situacao)->toBe(SituacaoLead::Bloqueado);
});

it('poe a mudanca de estagio na trilha, e a correcao de telefone nao', function () {
    [$vendedor] = carteira();
    $lead = leadDe($vendedor);

    // Só o telefone muda: nada na trilha.
    comoVendedor($vendedor)->put(route('carteira.leads.atualizar', $lead), [
        'nome' => $lead->nome, 'situacao' => 'novo', 'telefone' => '(31) 3333-3333',
    ]);

    expect(Auditoria::where('acao', 'lead.situacao')->count())->toBe(0);

    comoVendedor($vendedor)->put(route('carteira.leads.atualizar', $lead), [
        'nome' => $lead->nome, 'situacao' => 'recusado',
    ]);

    $trilha = Auditoria::where('acao', 'lead.situacao')->sole();

    expect($trilha->dados)->toBe(['de' => 'Novo', 'para' => 'Recusado'])
        ->and($trilha->staff_id)->toBe($vendedor->id)
        ->and(App\Support\Rotulos::acao('lead.situacao'))->toBe('Situação do lead alterada');
});

/*
|--------------------------------------------------------------------------
| Virar cliente
|--------------------------------------------------------------------------
*/

/**
 * A regra: o lead so abre cadastro com CNPJ valido e e-mail. Sao os dois campos
 * que a empresa precisa ter e que ninguem inventa, e o CNPJ tem o digito
 * conferido porque documento errado reaparece na primeira cobranca.
 */
it('diz o que falta em vez de deixar converter pela metade', function () {
    [$vendedor] = carteira();

    $semNada = leadDe($vendedor, ['cnpj' => null, 'email' => null]);
    $cnpjTorto = leadDe($vendedor, ['cnpj' => '11111111111111', 'email' => 'a@b.com']);

    expect($semNada->faltaParaVirarCliente())->toBe(['o CNPJ', 'o e-mail'])
        ->and($semNada->podeVirarCliente())->toBeFalse()
        ->and($cnpjTorto->faltaParaVirarCliente())->toBe(['um CNPJ válido']);

    comoVendedor($vendedor)->get(route('carteira.leads.editar', $semNada))
        ->assertOk()
        ->assertSee('para abrir o cadastro de cliente');

    comoVendedor($vendedor)->get(route('carteira.leads.converter', $semNada))
        ->assertRedirect(route('carteira.leads.editar', $semNada))
        ->assertSessionHas('erro', 'Antes de abrir o cadastro, preencha o CNPJ e o e-mail.');
});

it('abre o cadastro de cliente ja preenchido pela ficha do lead', function () {
    [$vendedor] = carteira();
    $lead = leadDe($vendedor, [
        'nome' => 'PADARIA DO BAIRRO LTDA',
        'cidade' => 'Belo Horizonte',
        'uf' => 'MG',
        'responsavel_nome' => 'Dona Marta',
    ]);

    comoVendedor($vendedor)->get(route('carteira.leads.converter', $lead))
        ->assertOk()
        ->assertSee('PADARIA DO BAIRRO LTDA')
        ->assertSee('Dona Marta')
        ->assertSee('08.876.860/0001-03')
        ->assertSee('contato@exemplo.com.br')
        // O vinculo viaja escondido: e ele que fecha o lead ao salvar.
        ->assertSee('name="lead_id" value="'.$lead->id.'"', false);
});

/** O ciclo inteiro, que e a razao de o modulo existir. */
it('converte o lead em cliente ativo da carteira dele, e o lead aponta para a empresa', function () {
    [$vendedor] = carteira();
    $lead = leadDe($vendedor, ['nome' => 'PADARIA DO BAIRRO LTDA']);

    comoVendedor($vendedor)->post(route('empresas.salvar'), [
        'razao_social' => 'PADARIA DO BAIRRO LTDA',
        'cnpj' => '08.876.860/0001-03',
        'email' => 'contato@exemplo.com.br',
        'situacao' => 'ativo',
        'lead_id' => $lead->id,
    ])->assertRedirect(route('carteira.leads'))
        ->assertSessionHas('ok');

    $empresa = Cliente::firstWhere('cnpj', '08876860000103');
    $lead->refresh();

    expect($empresa)->not->toBeNull()
        // Nasce ativa, que no cliente quer dizer permissao de consultar e ser
        // cobrada, e na carteira de quem vendeu.
        ->and($empresa->situacao)->toBe('ativo')
        ->and($empresa->podeConsultar())->toBeTrue()
        ->and($empresa->vendedor_id)->toBe($vendedor->id)
        // E o lead nao e apagado: ele responde "de onde veio este cliente".
        ->and($lead->situacao)->toBe(SituacaoLead::Convertido)
        ->and($lead->cliente_id)->toBe($empresa->id)
        ->and($lead->convertido_em)->not->toBeNull()
        ->and($lead->cliente->razao_social)->toBe('PADARIA DO BAIRRO LTDA');

    $trilha = Auditoria::where('acao', 'lead.convertido')->sole();
    expect($trilha->dados['cliente_id'])->toBe($empresa->id)
        ->and(App\Support\Rotulos::acao('lead.convertido'))->toBe('Lead convertido em cliente');
});

it('sai da fila de quem prospecta depois de virar cliente', function () {
    [$vendedor] = carteira();
    $lead = leadDe($vendedor, ['nome' => 'JA CONVERTIDO LTDA']);

    app(App\Actions\Prospeccao\RegistrarConversaoDoLead::class)($lead, empresaComPlano());

    comoVendedor($vendedor)->get(route('carteira.leads', ['situacao' => 'em_aberto']))
        ->assertOk()
        ->assertDontSee('JA CONVERTIDO LTDA');

    comoVendedor($vendedor)->get(route('carteira.leads.converter', $lead->fresh()))
        ->assertRedirect(route('carteira.leads'))
        ->assertSessionHas('erro', 'JA CONVERTIDO LTDA já virou cliente.');
});

/** Converter duas vezes nao pode reescrever a origem nem a data da primeira. */
it('nao reconverte lead que ja e cliente', function () {
    [$vendedor] = carteira();
    $lead = leadDe($vendedor);
    $primeira = empresaComPlano();
    $registrar = app(App\Actions\Prospeccao\RegistrarConversaoDoLead::class);

    expect($registrar($lead, $primeira))->toBeTrue();

    $lead->refresh();
    $convertidoEm = $lead->convertido_em;

    expect($registrar($lead, empresaComPlano()))->toBeFalse()
        ->and($lead->fresh()->cliente_id)->toBe($primeira->id)
        ->and($lead->fresh()->convertido_em->eq($convertidoEm))->toBeTrue();
});

/**
 * O `lead_id` chega escondido no formulario, entao ele e conferido: sem isso um
 * POST montado a mao fecharia lead de outro vendedor.
 */
it('ignora lead alheio vindo no POST do cadastro', function () {
    [$vendedor] = carteira();
    $outro = Staff::factory()->create(['papel' => 'vendedor']);
    $doOutro = leadDe($outro, ['nome' => 'LEAD DO COLEGA LTDA']);

    comoVendedor($vendedor)->post(route('empresas.salvar'), [
        'razao_social' => 'EMPRESA QUALQUER LTDA',
        'cnpj' => '08.876.860/0001-03',
        'email' => 'qualquer@exemplo.com.br',
        'situacao' => 'ativo',
        'lead_id' => $doOutro->id,
    ])->assertSessionHas('ok');

    expect($doOutro->fresh()->situacao)->toBe(SituacaoLead::Novo)
        ->and($doOutro->fresh()->cliente_id)->toBeNull();
});

/*
|--------------------------------------------------------------------------
| A fila do dia
|--------------------------------------------------------------------------
*/

it('poe o agendamento vencido na frente e separa o que da trabalho', function () {
    [$vendedor] = carteira();

    $atrasado = leadDe($vendedor, ['nome' => 'ATRASADO LTDA']);
    $atrasado->update(['situacao' => SituacaoLead::Agendado, 'agendado_para' => now()->subDays(3)]);

    $futuro = leadDe($vendedor, ['nome' => 'SEMANA QUE VEM LTDA']);
    $futuro->update(['situacao' => SituacaoLead::Agendado, 'agendado_para' => now()->addDays(5)]);

    $recusado = leadDe($vendedor, ['nome' => 'RECUSADO LTDA']);
    $recusado->update(['situacao' => SituacaoLead::Recusado]);

    $resposta = comoVendedor($vendedor)->get(route('carteira.leads'))->assertOk();

    // Prazo ordena sozinho: o que passou da hora vem antes do que ainda vai
    // acontecer, e os dois antes de quem nao tem data.
    $html = $resposta->getContent();
    expect(strpos($html, 'ATRASADO LTDA'))->toBeLessThan(strpos($html, 'SEMANA QUE VEM LTDA'))
        ->and(strpos($html, 'SEMANA QUE VEM LTDA'))->toBeLessThan(strpos($html, 'RECUSADO LTDA'));

    comoVendedor($vendedor)->get(route('carteira.leads', ['situacao' => 'atrasado']))
        ->assertSee('ATRASADO LTDA')
        ->assertDontSee('SEMANA QUE VEM LTDA');

    comoVendedor($vendedor)->get(route('carteira.leads', ['situacao' => 'em_aberto']))
        ->assertSee('ATRASADO LTDA')
        ->assertSee('SEMANA QUE VEM LTDA')
        ->assertDontSee('RECUSADO LTDA');
});
