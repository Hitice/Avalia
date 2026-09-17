<?php

use App\Mail\PreCadastroRecebido;
use App\Models\InteressadoCobranca;
use App\Support\Documento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

/** Um pedido de contato completo, como a tela manda. */
function pedidoDeContato(array $ajustes = []): array
{
    return array_merge([
        'nome' => 'Marina Costa',
        'email' => 'marina@escola.com.br',
        'whatsapp' => '(34) 99911-2233',
        'instagram' => 'escolacosta',
        'vende' => 'Cursos online',
        'papel' => 'Sou o dono do negócio',
        'prazo' => 'Quero começar agora',
        'faturamento_ano' => 'R$ 100 mil a R$ 500 mil',
    ], $ajustes);
}

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
        ->assertSee('Fale com a Avalia 360')
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

    $this->from(route('cobranca'))->post(route('cobranca.pre-cadastro'), pedidoDeContato([
        'email' => 'Marina@Escola.com.br',
    ]))->assertRedirect(route('cobranca'))->assertSessionHas('cobranca_ok');

    $produtor = InteressadoCobranca::sole();

    expect($produtor->nome)->toBe('Marina Costa')
        // E-mail normalizado, senao "Marina@" e "marina@" viram dois cadastros.
        ->and($produtor->email)->toBe('marina@escola.com.br')
        ->and($produtor->whatsapp)->toBe('34999112233')
        // O arroba entra sempre, digitado ou nao: a equipe procura das duas
        // formas e as duas tem que achar.
        ->and($produtor->instagram)->toBe('@escolacosta')
        ->and($produtor->vende)->toBe('Cursos online')
        ->and($produtor->papel)->toBe('Sou o dono do negócio')
        // Documento nao e pedido aqui: quem preenche ainda esta decidindo.
        ->and($produtor->documento)->toBeNull()
        ->and($produtor->atendido_em)->toBeNull();

    Mail::assertSent(PreCadastroRecebido::class);
});

it('guarda o telefone cifrado no banco', function () {
    // A regra que justifica o cast: quem abrir um backup nao le CPF nem
    // telefone de ninguem. O e-mail fica em claro de proposito, porque e a
    // chave que impede cadastro repetido.
    $this->post(route('cobranca.pre-cadastro'), pedidoDeContato());

    $linha = DB::table('interessados_cobranca')->sole();

    // O e-mail fica em claro de proposito: e a chave que impede o mesmo
    // produtor pedir contato cinco vezes, e coluna cifrada nao se pesquisa.
    expect($linha->whatsapp)->not->toContain('34999112233')
        ->and($linha->email)->toBe('marina@escola.com.br');
});

it('descarta robo em silencio', function () {
    $this->from(route('cobranca'))->post(route('cobranca.pre-cadastro'), pedidoDeContato([
        'nome' => 'Robo Qualquer',
        'email' => 'spam@spam.com',
        'site' => 'https://spam.example',
    ]))->assertRedirect(route('cobranca'))->assertSessionHas('cobranca_ok');

    expect(InteressadoCobranca::count())->toBe(0);
});

it('nao pede documento a quem ainda esta decidindo', function () {
    // CPF antes de falar com alguem e o campo que mais faz gente desistir no
    // meio. Ele e pedido no cadastro da conta, quando ja existe interesse.
    $this->get(route('cobranca'))->assertOk()
        ->assertDontSee('name="documento"', false)
        ->assertSee('name="instagram"', false);
});

it('exige as perguntas que dizem a quem ligar primeiro', function () {
    // Instagram, o que vende e o papel no negocio: sao elas que separam o
    // produtor de curso com faturamento do curioso que passou pela pagina.
    $this->from(route('cobranca'))->post(route('cobranca.pre-cadastro'), [
        'nome' => 'Marina Costa',
        'email' => 'marina@escola.com.br',
        'whatsapp' => '34999112233',
    ])->assertSessionHasErrors(['instagram', 'vende', 'papel']);

    expect(InteressadoCobranca::count())->toBe(0);
});

it('guarda o que ajuda a priorizar o contato', function () {
    $this->post(route('cobranca.pre-cadastro'), pedidoDeContato([
        'prazo' => 'Quero começar agora',
        'faturamento_ano' => 'Mais de R$ 2 milhões',
    ]));

    $interessado = InteressadoCobranca::sole();

    expect($interessado->prazo)->toBe('Quero começar agora')
        ->and($interessado->faturamento_ano)->toBe('Mais de R$ 2 milhões');
});

it('nao deixa o mesmo e-mail se cadastrar duas vezes', function () {
    $this->post(route('cobranca.pre-cadastro'), pedidoDeContato());

    $this->from(route('cobranca'))
        ->post(route('cobranca.pre-cadastro'), pedidoDeContato())
        ->assertSessionHasErrors('email');

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
