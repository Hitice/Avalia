<?php

use App\Mail\PreCadastroRecebido;
use App\Models\InteressadoCobranca;
use App\Support\Documento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

/**
 * A pagina publica do Avalia 360 e o pre-cadastro do produtor.
 *
 * O que estes testes guardam: o pedido chega inteiro a quem vai retornar, o
 * dado pessoal nao fica legivel no banco, e o segundo POST publico do sistema
 * nao vira porta de robo nem de cadastro repetido.
 */
it('apresenta o Avalia 360 com a promessa e o caminho do pre-cadastro', function () {
    $this->get(route('cobranca'))->assertOk()
        ->assertSee('Avalia 360')
        ->assertSee('sem depender do cartão', false)
        ->assertSee('Pré-cadastro de produtor', false)
        ->assertSee('Como funciona');
});

it('chama o Avalia 360 na pagina inicial', function () {
    // A porta do dominio precisa levar ao produto novo, senao ele so existe
    // para quem ja sabe o endereco.
    $this->get('/')->assertOk()
        ->assertSee('Avalia 360')
        ->assertSee(route('cobranca'));
});

it('grava o pre-cadastro e avisa o comercial', function () {
    Mail::fake();

    $this->from(route('cobranca'))->post(route('cobranca.pre-cadastro'), [
        'nome' => 'Marina Costa',
        'documento' => '123.456.789-09',
        'email' => 'Marina@Escola.com.br',
        'whatsapp' => '(34) 99911-2233',
        'ticket_medio' => '2.500,00',
        'volume_mensal' => 'R$ 10 mil a R$ 50 mil',
    ])->assertRedirect(route('cobranca'))->assertSessionHas('cobranca_ok');

    $produtor = InteressadoCobranca::sole();

    expect($produtor->nome)->toBe('Marina Costa')
        ->and($produtor->documento)->toBe('12345678909')
        // Dinheiro em centavos da entrada ate o banco: 2.500,00 sao 250000.
        ->and($produtor->ticket_medio_cents)->toBe(250000)
        // E-mail normalizado, senao "Marina@" e "marina@" viram dois cadastros.
        ->and($produtor->email)->toBe('marina@escola.com.br')
        ->and($produtor->whatsapp)->toBe('34999112233')
        ->and($produtor->atendido_em)->toBeNull();

    Mail::assertSent(PreCadastroRecebido::class);
});

it('guarda documento e telefone cifrados no banco', function () {
    // A regra que justifica o cast: quem abrir um backup nao le CPF nem
    // telefone de ninguem. O e-mail fica em claro de proposito, porque e a
    // chave que impede cadastro repetido.
    InteressadoCobranca::create([
        'nome' => 'Marina Costa',
        'documento' => '12345678909',
        'email' => 'marina@escola.com.br',
        'whatsapp' => '34999112233',
        'ticket_medio_cents' => 250000,
        'volume_mensal' => 'Até R$ 10 mil',
    ]);

    $linha = DB::table('interessados_cobranca')->sole();

    expect($linha->documento)->not->toContain('12345678909')
        ->and($linha->whatsapp)->not->toContain('34999112233')
        ->and($linha->email)->toBe('marina@escola.com.br');
});

it('descarta robo em silencio', function () {
    $this->from(route('cobranca'))->post(route('cobranca.pre-cadastro'), [
        'nome' => 'Robo Qualquer',
        'documento' => '12345678909',
        'email' => 'spam@spam.com',
        'whatsapp' => '34999112233',
        'ticket_medio' => '100,00',
        'volume_mensal' => 'Até R$ 10 mil',
        'site' => 'https://spam.example',
    ])->assertRedirect(route('cobranca'))->assertSessionHas('cobranca_ok');

    expect(InteressadoCobranca::count())->toBe(0);
});

it('recusa documento que nao fecha os digitos', function () {
    // Verificador errado e erro de digitacao, e o produtor descobre agora, e
    // nao quando a subconta do provedor for recusada la na frente.
    $this->from(route('cobranca'))->post(route('cobranca.pre-cadastro'), [
        'nome' => 'Marina Costa',
        'documento' => '111.111.111-11',
        'email' => 'marina@escola.com.br',
        'whatsapp' => '34999112233',
        'ticket_medio' => '2.500,00',
        'volume_mensal' => 'Até R$ 10 mil',
    ])->assertSessionHasErrors('documento');

    expect(InteressadoCobranca::count())->toBe(0);
});

it('aceita CNPJ no lugar do CPF', function () {
    // Produtor pessoa juridica entra pelo mesmo campo: o tamanho diz qual
    // conta de verificador usar, e a tela nao pergunta o tipo.
    $this->post(route('cobranca.pre-cadastro'), [
        'nome' => 'Escola Costa',
        'documento' => '39.914.870/0001-01',
        'email' => 'contato@escola.com.br',
        'whatsapp' => '34999112233',
        'ticket_medio' => '4.000,00',
        'volume_mensal' => 'Mais de R$ 200 mil',
    ])->assertSessionHas('cobranca_ok');

    expect(InteressadoCobranca::sole()->documento)->toBe('39914870000101');
});

it('nao deixa o mesmo e-mail se cadastrar duas vezes', function () {
    InteressadoCobranca::create([
        'nome' => 'Marina Costa',
        'documento' => '12345678909',
        'email' => 'marina@escola.com.br',
        'whatsapp' => '34999112233',
        'ticket_medio_cents' => 250000,
        'volume_mensal' => 'Até R$ 10 mil',
    ]);

    $this->from(route('cobranca'))->post(route('cobranca.pre-cadastro'), [
        'nome' => 'Marina Costa',
        'documento' => '12345678909',
        'email' => 'marina@escola.com.br',
        'whatsapp' => '34999112233',
        'ticket_medio' => '2.500,00',
        'volume_mensal' => 'Até R$ 10 mil',
    ])->assertSessionHasErrors('email');

    expect(InteressadoCobranca::count())->toBe(1);
});

it('valida CPF e CNPJ pela mesma porta', function () {
    expect(Documento::documentoValido('123.456.789-09'))->toBeTrue()
        ->and(Documento::documentoValido('111.111.111-11'))->toBeFalse()
        ->and(Documento::documentoValido('39914870000101'))->toBeTrue()
        ->and(Documento::documentoValido('39914870000102'))->toBeFalse()
        // Doze digitos nao sao nem CPF nem CNPJ.
        ->and(Documento::documentoValido('123456789012'))->toBeFalse();
});
