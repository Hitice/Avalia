<?php

use App\Enums\SituacaoEtiqueta;
use App\Models\Auditoria;
use App\Models\DestinoEtiqueta;
use App\Models\Etiqueta;
use App\Models\RenovacaoEtiqueta;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| A gestao de uma plaquinha
|--------------------------------------------------------------------------
|
| Cada acao daqui muda o que um desconhecido ve ao encostar o celular numa
| placa que esta no balcao de um cliente. Nao ha "desfazer" do lado de fora: a
| placa continua la, e quem leu ja foi para onde ela mandou.
|
*/

it('poe a plaquinha no ar na primeira venda', function () {
    $etiqueta = Etiqueta::factory()->create();

    admin()->put(route('etiquetas.apontar', $etiqueta), ['vendedor_id' => App\Models\Staff::factory()->create(['papel' => 'vendedor'])->id,
        'destino' => 'padariadoze.com.br',
        'cliente_nome' => 'Padaria do Zé',
    ])->assertRedirect();

    $etiqueta->refresh();

    expect($etiqueta->situacao)->toBe(SituacaoEtiqueta::Ativa)
        // O https que ninguem digita entra sozinho: sem ele o navegador leria
        // o endereco como caminho relativo.
        ->and($etiqueta->destino)->toBe('https://padariadoze.com.br')
        ->and($etiqueta->vendida_em)->not->toBeNull()
        // Prazo do config, e nao do formulario: validade digitada a mao um dia
        // sai com dois anos por engano.
        ->and($etiqueta->vence_em->toDateString())->toBe(now()->addYear()->toDateString())
        ->and($etiqueta->valor_cents)->toBe((int) config('etiquetas.precos.placa_cents'));
});

it('guarda para onde a plaquinha apontava antes', function () {
    // "Para onde essa placa apontava em março" e uma pergunta que vai aparecer,
    // e a coluna `destino` so sabe responder pelo presente.
    $etiqueta = Etiqueta::factory()->create();

    admin()->put(route('etiquetas.apontar', $etiqueta), ['vendedor_id' => App\Models\Staff::factory()->create(['papel' => 'vendedor'])->id, 'destino' => 'https://antigo.com.br']);
    admin()->put(route('etiquetas.apontar', $etiqueta), ['vendedor_id' => App\Models\Staff::factory()->create(['papel' => 'vendedor'])->id, 'destino' => 'https://novo.com.br']);

    expect(DestinoEtiqueta::count())->toBe(2)
        ->and(DestinoEtiqueta::vigente()->count())->toBe(1)
        ->and(DestinoEtiqueta::vigente()->value('destino'))->toBe('https://novo.com.br')
        ->and($etiqueta->refresh()->destino)->toBe('https://novo.com.br');
});

it('nao abre linha nova quando o destino nao mudou', function () {
    $etiqueta = Etiqueta::factory()->create();

    admin()->put(route('etiquetas.apontar', $etiqueta), ['vendedor_id' => App\Models\Staff::factory()->create(['papel' => 'vendedor'])->id, 'destino' => 'https://igual.com.br']);
    admin()->put(route('etiquetas.apontar', $etiqueta), ['vendedor_id' => App\Models\Staff::factory()->create(['papel' => 'vendedor'])->id, 'destino' => 'https://igual.com.br']);

    expect(DestinoEtiqueta::count())->toBe(1);
});

it('nao deixa a plaquinha virar porta para o navegador executar coisa', function () {
    $etiqueta = Etiqueta::factory()->create();

    admin()->put(route('etiquetas.apontar', $etiqueta), ['vendedor_id' => App\Models\Staff::factory()->create(['papel' => 'vendedor'])->id, 'destino' => 'javascript:alert(1)'])
        ->assertSessionHasErrors('destino');

    expect($etiqueta->refresh()->destino)->toBeNull()
        ->and($etiqueta->situacao)->toBe(SituacaoEtiqueta::EmBranco);
});

it('nao guarda a primeira venda duas vezes', function () {
    // Trocar o destino dois anos depois nao pode reiniciar o prazo pago.
    $etiqueta = Etiqueta::factory()->create();

    admin()->put(route('etiquetas.apontar', $etiqueta), ['vendedor_id' => App\Models\Staff::factory()->create(['papel' => 'vendedor'])->id, 'destino' => 'https://um.com.br']);
    $vencimento = $etiqueta->refresh()->vence_em;

    $this->travel(40)->days();
    admin()->put(route('etiquetas.apontar', $etiqueta), ['vendedor_id' => App\Models\Staff::factory()->create(['papel' => 'vendedor'])->id, 'destino' => 'https://dois.com.br']);

    expect($etiqueta->refresh()->vence_em->toDateString())->toBe($vencimento->toDateString());
});

/*
|--------------------------------------------------------------------------
| Ligar, desligar e encerrar
|--------------------------------------------------------------------------
*/

it('suspende e reativa sem perder o destino', function () {
    $etiqueta = Etiqueta::factory()->ativa('https://loja.com.br')->create();

    admin()->post(route('etiquetas.alternar', $etiqueta));
    expect($etiqueta->refresh()->situacao)->toBe(SituacaoEtiqueta::Suspensa)
        ->and($etiqueta->destino)->toBe('https://loja.com.br');

    admin()->post(route('etiquetas.alternar', $etiqueta));
    expect($etiqueta->refresh()->situacao)->toBe(SituacaoEtiqueta::Ativa);
});

/*
|--------------------------------------------------------------------------
| Renovacao
|--------------------------------------------------------------------------
*/

it('renova a partir do vencimento antigo, e nao de hoje', function () {
    // Quem paga com quinze dias de atraso comprou um ano, e nao um ano menos
    // quinze dias.
    $etiqueta = Etiqueta::factory()->vencidaHa(15)->create();
    $vencia = $etiqueta->vence_em->copy();

    admin()->post(route('etiquetas.renovar', $etiqueta));

    expect($etiqueta->refresh()->vence_em->toDateString())
        ->toBe($vencia->addYear()->toDateString())
        ->and(RenovacaoEtiqueta::sole()->valor_cents)->toBe((int) config('etiquetas.precos.renovacao_cents'));
});

it('libera o aviso de novo depois de renovar', function () {
    // Sem isso, a plaquinha renovada nunca mais avisaria o proximo vencimento.
    $etiqueta = Etiqueta::factory()->ativa()->create(['avisada_em' => now()]);

    admin()->post(route('etiquetas.renovar', $etiqueta));

    expect($etiqueta->refresh()->avisada_em)->toBeNull();
});

/*
|--------------------------------------------------------------------------
| A lista
|--------------------------------------------------------------------------
*/

it('acha a plaquinha pelo codigo digitado errado', function () {
    // Quem le "1" no acrilico digita "I". A busca normaliza antes de procurar.
    Etiqueta::factory()->ativa()->create(['codigo' => 'K7M2P1']);

    admin()->get(route('etiquetas.index', ['busca' => 'k7m2pi']))
        ->assertOk()
        ->assertSee('K7M2P1');
});

it('abre campanha tambem quando gera um codigo so', function () {
    // Mesmo roteiro para um e para cem: a pessoa fica na tabela, escolhe a
    // campanha e baixa o pacote. Dois caminhos para a mesma tarefa fariam o de
    // uma unidade ser o que ninguem lembra.
    admin()->post(route('etiquetas.gerar'), ['quantidade' => 1, 'titulo' => 'Cliente sem placa'])
        ->assertRedirect();

    $etiqueta = Etiqueta::sole();

    expect($etiqueta->lote_id)->not->toBeNull()
        ->and($etiqueta->sequencia)->toBe(1)
        ->and($etiqueta->situacao)->toBe(SituacaoEtiqueta::EmBranco)
        ->and($etiqueta->lote->titulo)->toBe('Cliente sem placa');
});

it('batiza a campanha sozinho quando ninguem digita o nome', function () {
    // A campanha precisa de rotulo para aparecer no seletor.
    admin()->post(route('etiquetas.gerar'), ['quantidade' => 3]);

    expect(App\Models\LoteEtiqueta::sole()->titulo)->toBe('Campanha 1');
});

it('guarda o cliente junto com o destino', function () {
    Etiqueta::factory()->create(['codigo' => 'K7M2PX']);

    admin()->post(route('etiquetas.apontar-codigo'), ['vendedor_id' => App\Models\Staff::factory()->create(['papel' => 'vendedor'])->id,
        'codigo' => 'K7M2PX',
        'destino' => 'https://padaria.com.br',
        'cliente_nome' => 'Padaria do Zé',
    ])->assertRedirect();

    expect(Etiqueta::sole()->cliente_nome)->toBe('Padaria do Zé');
});

it('gera cem de uma vez, e ai abre tiragem', function () {
    admin()->post(route('etiquetas.gerar'), ['quantidade' => 100])->assertRedirect();

    expect(Etiqueta::count())->toBe(100)
        ->and(App\Models\LoteEtiqueta::count())->toBe(1)
        // A tiragem e o que da o pacote numerado para a grafica.
        ->and(Etiqueta::whereNull('lote_id')->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| O passo de depois da venda
|--------------------------------------------------------------------------
|
| Quem acabou de vender tem a plaquinha na mao e le o codigo dela. Procurar
| essa placa numa lista de mil seria o caminho longo para a unica coisa que ele
| quer fazer.
|
*/

it('cadastra a url pelo codigo impresso', function () {
    Etiqueta::factory()->create(['codigo' => 'K7M2PX']);

    admin()->post(route('etiquetas.apontar-codigo'), ['vendedor_id' => App\Models\Staff::factory()->create(['papel' => 'vendedor'])->id,
        'codigo' => 'k7m2px',
        'destino' => 'wa.me/5531999999999',
    ])->assertRedirect();

    $etiqueta = Etiqueta::sole();

    expect($etiqueta->destino)->toBe('https://wa.me/5531999999999')
        ->and($etiqueta->situacao)->toBe(SituacaoEtiqueta::Ativa);
});

it('conserta a letra parecida no codigo digitado', function () {
    // Quem le "1" no acrilico digita "I". O alfabeto exclui as duas de
    // proposito, e a leitura desfaz a troca.
    Etiqueta::factory()->create(['codigo' => 'K7M2P1']);

    admin()->post(route('etiquetas.apontar-codigo'), ['vendedor_id' => App\Models\Staff::factory()->create(['papel' => 'vendedor'])->id,
        'codigo' => 'K7M2PI', 'destino' => 'https://loja.com.br',
    ])->assertRedirect();

    expect(Etiqueta::sole()->destino)->toBe('https://loja.com.br');
});

it('diz o que conferir quando o codigo nao existe', function () {
    admin()->post(route('etiquetas.apontar-codigo'), ['vendedor_id' => App\Models\Staff::factory()->create(['papel' => 'vendedor'])->id,
        'codigo' => 'ZZZZZZ', 'destino' => 'https://loja.com.br',
    ])->assertRedirect()->assertSessionHas('erro', fn (string $aviso) => str_contains($aviso, 'I, L, O e U'));
});

it('registra na auditoria tudo que muda a plaquinha', function () {
    $etiqueta = Etiqueta::factory()->create();

    admin()->put(route('etiquetas.apontar', $etiqueta), ['vendedor_id' => App\Models\Staff::factory()->create(['papel' => 'vendedor'])->id, 'destino' => 'https://loja.com.br']);
    admin()->post(route('etiquetas.alternar', $etiqueta));
    admin()->post(route('etiquetas.renovar', $etiqueta));

    expect(Auditoria::pluck('acao')->all())->toContain(
        'etiquetas.destino.trocado',
        'etiquetas.vendida',
        'etiquetas.alternada',
        'etiquetas.renovada',
    );
});

it('deixa o vendedor entrar e enxergar a tiragem inteira', function () {
    // A equipe ve tudo. Antes o vendedor via so o que era dele pelo dono, e
    // abria a tela sem estoque para vender. Quem continua limitado pelo dono e
    // quem esta fora da equipe, e disso cuidam os testes de cliente abaixo.
    $vendedor = Staff::factory()->create(['papel' => 'vendedor']);
    $daAdministracao = Etiqueta::factory()->ativa()->create(['codigo' => 'AAAAAA']);

    // Quem gera e a administracao; o vendedor so ve e aponta.
    admin()->post(route('etiquetas.gerar'), ['quantidade' => 2, 'titulo' => 'Do vendedor']);

    comoVendedor($vendedor)->get(route('etiquetas.index'))
        ->assertOk()
        ->assertSee('Do vendedor')
        ->assertSee('AAAAAA');

    comoVendedor($vendedor)->get(route('etiquetas.ficha', $daAdministracao))->assertOk();
    comoVendedor($vendedor)->post(route('etiquetas.alternar', $daAdministracao))->assertRedirect();
});

it('deixa o cliente entrar pela conta da empresa dele, e ver so o que e dele', function () {
    // O cliente NAO gera codigo: o grupo de rotas aceita empresa e produtor, e
    // gerar ficava aberto a qualquer conta logada. Esse era o furo, e este
    // teste o usava como setup. O que vale conferir e a visibilidade pelo dono.
    $empresa = empresaComPlano();
    $admin = Staff::factory()->admin()->create();

    Etiqueta::factory()->ativa()->create(['codigo' => 'DELA00', 'dono_tipo' => 'empresa', 'dono_id' => $empresa->id]);
    Etiqueta::factory()->ativa()->create(['codigo' => 'CASA00', 'dono_tipo' => 'staff', 'dono_id' => $admin->id]);

    comoEmpresa($empresa)->post(route('etiquetas.gerar'), ['quantidade' => 1])->assertForbidden();

    comoEmpresa($empresa)->get(route('etiquetas.index'))
        ->assertOk()
        ->assertSee('DELA00')
        ->assertDontSee('CASA00');
});

it('nao deixa um cliente cadastrar destino no codigo de outro', function () {
    // O codigo esta impresso e qualquer um pode ler um. A resposta e a mesma
    // de codigo inexistente.
    Etiqueta::factory()->create(['codigo' => 'K7M2PX']);

    comoEmpresa(empresaComPlano())->post(route('etiquetas.apontar-codigo'), [
        'codigo' => 'K7M2PX', 'destino' => 'https://invasor.com.br',
    ])->assertRedirect()->assertSessionHas('erro');

    expect(Etiqueta::sole()->destino)->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Recusa nao e erro de sistema
|--------------------------------------------------------------------------
|
| "Esta plaquinha foi baixada" e um pedido que o sistema entendeu e negou, com
| um motivo que a pessoa consegue ler. Servido como 500, ele ensina o operador
| que a tela quebra sozinha, e esconde no log a unica frase que resolveria a
| duvida dele. Foi exatamente o que aconteceu em producao.
|
*/

it('recusa renovar plaquinha que nunca foi vendida, sem quebrar a tela', function () {
    $etiqueta = Etiqueta::factory()->create();

    admin()->post(route('etiquetas.renovar', $etiqueta))
        ->assertRedirect()
        ->assertSessionHas('erro');
});

it('recusa ligar e desligar plaquinha em branco, sem quebrar a tela', function () {
    // Suspender o que nunca apontou trocaria a pagina que explica o produto a
    // quem leu a placa pela de "fora do ar", que assusta sem motivo.
    $etiqueta = Etiqueta::factory()->create();

    admin()->post(route('etiquetas.alternar', $etiqueta))
        ->assertRedirect()
        ->assertSessionHas('erro');
});

it('desenha o codigo da plaquinha na propria ficha', function () {
    // A ficha e onde se reimprime a placa que quebrou, e onde se ve o codigo
    // de uma etiqueta avulsa que nunca teve tiragem. Sem isto, o unico lugar
    // com QR era a tela do lote.
    $etiqueta = Etiqueta::factory()->create(['codigo' => 'K7M2PX']);

    admin()->get(route('etiquetas.ficha', $etiqueta))
        ->assertOk()
        ->assertSee('Baixar SVG')
        ->assertSee('Baixar PNG')
        // O endereco impresso, legivel embaixo do codigo: quando o cliente
        // liga, a primeira pergunta e qual o codigo da plaquinha dele.
        ->assertSee('/q/K7M2PX');
});

it('abre no painel do Avalia Sales, com o menu do produto e nao o do credito', function () {
    // A casca sem lateral nasceu quando o produto tinha duas telas. Agora tem
    // seis, e lateral de 290px deixou de ser moldura maior que o quadro. O que
    // continua valendo e nao misturar os produtos: mesma moldura, menu do Sales.
    $conteudo = admin()->get(route('etiquetas.index'))->assertOk()->getContent();

    expect($conteudo)->toContain('Avalia Sales')
        ->and($conteudo)->toContain('Estoque')
        // Nenhum modulo do Avalia One no menu daqui.
        ->and($conteudo)->not->toContain('Catálogo')
        ->and($conteudo)->not->toContain('Simulador');
});

it('tem o interruptor de tema, como o resto do painel', function () {
    // Ele saiu quando a casca era propria e sem lateral. No boilerplate ele
    // existe em toda tela, e quem marcou escuro no CRM continua no escuro aqui
    // em vez de trocar de tema ao trocar de produto.
    $conteudo = admin()->get(route('etiquetas.index'))->assertOk()->getContent();

    expect($conteudo)->toContain('$store.theme');
});

it('resolve a operacao inteira numa pagina so', function () {
    // Gerar, filtrar por campanha, baixar o pacote e cadastrar destino: tudo
    // na mesma tela. Cada uma dessas tarefas numa tela propria fazia o
    // operador procurar em quatro lugares o que e um fluxo so.
    admin()->post(route('etiquetas.gerar'), ['quantidade' => 3, 'titulo' => 'Campanha Floripa 2026']);

    $campanha = App\Models\LoteEtiqueta::sole();

    admin()->get(route('etiquetas.index', ['lote' => $campanha->id]))
        ->assertOk()
        // O seletor de campanha e o botao do ZIP, juntos.
        ->assertSee('Campanha Floripa 2026')
        ->assertSee('Baixar 3 em ZIP')
        // As colunas tem nome.
        ->assertSee('>QR<', false)
        ->assertSee('>Cliente<', false)
        ->assertSee('>Baixar<', false)
        // E a area de cadastro pede o cliente junto do codigo.
        ->assertSee('Código impresso')
        ->assertSee('Padaria do Zé', false);
});

it('so apaga tudo quando alguem confirma por escrito', function () {
    // Comando destrutivo que roda so com o nome e comando que um dia entra
    // numa lista de rotina por engano.
    admin()->post(route('etiquetas.gerar'), ['quantidade' => 5]);
    $etiqueta = Etiqueta::first();
    admin()->put(route('etiquetas.apontar', $etiqueta), ['vendedor_id' => App\Models\Staff::factory()->create(['papel' => 'vendedor'])->id, 'destino' => 'https://loja.com.br']);

    $this->artisan('avalia:etiquetas-limpar')->assertSuccessful();

    expect(Etiqueta::count())->toBe(5);

    $this->artisan('avalia:etiquetas-limpar --confirmar')->assertSuccessful();

    // Some tudo, inclusive o que pendurava nas chaves estrangeiras.
    expect(Etiqueta::count())->toBe(0)
        ->and(App\Models\LoteEtiqueta::count())->toBe(0)
        ->and(DestinoEtiqueta::count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Gerar e producao, e nao venda
|--------------------------------------------------------------------------
*/

it('nao mostra ao vendedor o bloco de gerar codigos', function () {
    // Gerar cem codigos e tarefa de producao. O vendedor recebe as placas pelo
    // estoque e aponta uma a uma; o botao de gerar ao lado do campo de apontar
    // so confundia.
    $vendedor = Staff::factory()->create(['papel' => 'vendedor']);

    $html = test()->actingAs($vendedor, 'staff')->withSession(['versao_staff' => 1])
        ->get(route('etiquetas.index'))->assertOk()->getContent();

    expect($html)->not->toContain('Gerar novo QR Code')
        ->and($html)->not->toContain(route('etiquetas.criar'));
});

it('fecha a geracao para o vendedor no servidor, e nao so na tela', function () {
    $vendedor = Staff::factory()->create(['papel' => 'vendedor']);
    $como = test()->actingAs($vendedor, 'staff')->withSession(['versao_staff' => 1]);

    $como->get(route('etiquetas.criar'))->assertForbidden();
    $como->post(route('etiquetas.gerar'), ['quantidade' => 5])->assertForbidden();

    expect(Etiqueta::count())->toBe(0);
});

it('da a geracao uma pagina propria, fora da busca e do cadastro', function () {
    $html = admin()->get(route('etiquetas.criar'))->assertOk()->getContent();

    expect($html)->toContain('name="quantidade"')
        ->and($html)->toContain(route('etiquetas.gerar'));

    // E a tela de cadastro nao carrega mais o formulario de gerar.
    $indice = admin()->get(route('etiquetas.index'))->assertOk()->getContent();

    expect($indice)->not->toContain('name="quantidade"')
        ->and($indice)->toContain('Área de cadastro');
});
