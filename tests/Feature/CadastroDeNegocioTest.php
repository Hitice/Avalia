<?php

use App\Enums\SituacaoNegocio;
use App\Models\Negocio;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** O que o formulario publico exige, e nada mais. */
function fichaDeNegocio(array $troca = []): array
{
    return array_merge([
        'nome' => 'Barbearia Dom',
        'responsavel' => 'Rafael Dias',
        'email' => 'Rafael@Barbearia.com',
        'whatsapp' => '(34) 99999-0000',
    ], $troca);
}

/*
|--------------------------------------------------------------------------
| O formulario aberto
|--------------------------------------------------------------------------
*/

it('a administracao exclui de vez; a placa vendida fica, sem o vinculo', function () {
    $negocio = App\Models\Negocio::factory()->create(['nome' => 'Teste ME']);
    $placa = App\Models\Etiqueta::factory()->ativa()->create(['negocio_id' => $negocio->id]);

    admin()->from(route('negocios'))->delete(route('negocios.excluir', $negocio))->assertRedirect(route('negocios'))->assertSessionHas('ok');

    expect(App\Models\Negocio::find($negocio->id))->toBeNull()
        ->and($placa->fresh()->negocio_id)->toBeNull()
        ->and(App\Models\Vinculo::where('entidade_tipo', 'App\\Models\\Negocio')->where('entidade_id', $negocio->id)->count())->toBe(0);

    $maria = App\Models\Staff::factory()->create(['papel' => 'vendedor']);
    comoVendedor($maria)->withHeaders(['referer' => ''])->delete(route('negocios.excluir', App\Models\Negocio::factory()->create()))->assertForbidden();
});

it('abre sem login, porque o link vai por whatsapp', function () {
    $this->get(route('cadastro-negocio'))->assertOk()->assertSee('Cadastro do seu negócio');
});

it('cadastra com o minimo e guarda quem distribuiu o link', function () {
    // Exigir pouco e deliberado: formulario que recusa a primeira tentativa e
    // formulario que o dono da loja nao termina.
    $this->post(route('cadastro-negocio.enviar'), fichaDeNegocio(['origem' => 'warley']))
        ->assertRedirect();

    $negocio = Negocio::first();

    expect($negocio->nome)->toBe('Barbearia Dom')
        ->and($negocio->situacao)->toBe(SituacaoNegocio::Recebido)
        ->and($negocio->origem)->toBe('warley');
});

it('normaliza o e-mail e tira a mascara do whatsapp', function () {
    $this->post(route('cadastro-negocio.enviar'), fichaDeNegocio());

    $negocio = Negocio::first();

    expect($negocio->email)->toBe('rafael@barbearia.com')
        ->and($negocio->whatsapp)->toBe('34999990000');
});

it('recusa o robo que preenche o campo escondido', function () {
    $this->post(route('cadastro-negocio.enviar'), fichaDeNegocio(['assunto' => 'seo services']))
        ->assertSessionHasErrors('assunto');

    expect(Negocio::count())->toBe(0);
});

it('exige o e-mail, que e por onde o google manda o acesso ao perfil', function () {
    $ficha = fichaDeNegocio();
    unset($ficha['email']);

    $this->post(route('cadastro-negocio.enviar'), $ficha)->assertSessionHasErrors('email');
});

it('entende que desmarcar o endereco significa atender no cliente', function () {
    // Sem isso o perfil de quem atende em casa publica o endereco residencial.
    $this->post(route('cadastro-negocio.enviar'), fichaDeNegocio());

    expect(Negocio::first()->atende_no_endereco)->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| A base, do lado da casa
|--------------------------------------------------------------------------
*/

it('nao abre a base para vendedor, que ve e-mail e telefone de todo cliente', function () {
    $vendedor = Staff::factory()->create(['papel' => 'vendedor']);

    test()->actingAs($vendedor, 'staff')->withSession(['versao_staff' => 1])
        ->get(route('negocios'))
        ->assertForbidden();
});

it('nao oferece mais o link de cadastro para a casa enviar', function () {
    // Saiu em 02/10/2026: a venda ja cadastra nome e contato, e o link estava
    // inerte, com um codigo na URL que controller nenhum lia. O formulario
    // publico de /cadastro continua, pelo que a venda nao colhe.
    $html = admin()->get(route('negocios'))->assertOk()->getContent();

    expect($html)->not->toContain('Link de cadastro para enviar')
        ->and($this->get(route('cadastro-negocio'))->status())->toBe(200);
});

it('diz o que falta para conseguir cadastrar no google', function () {
    Negocio::factory()->incompleto()->create(['nome' => 'Mercado Novo']);

    $html = admin()->get(route('negocios'))->assertOk()->getContent();

    expect($html)->toContain('categoria')
        ->and($html)->toContain('horários');
});

it('nao reclama de nada quando a ficha esta completa', function () {
    $negocio = Negocio::factory()->create();

    expect($negocio->faltaPara())->toBe([]);
});

it('marca a data quando o perfil entra no ar, e tira se voltar atras', function () {
    $negocio = Negocio::factory()->create();

    admin()->put(route('negocios.atualizar', $negocio), ['situacao' => 'publicado']);
    expect($negocio->fresh()->cadastrado_no_google_em)->not->toBeNull();

    // Data de publicacao em negocio que nao esta publicado engana a conferencia.
    admin()->put(route('negocios.atualizar', $negocio), ['situacao' => 'em_cadastro']);
    expect($negocio->fresh()->cadastrado_no_google_em)->toBeNull();
});

it('monta o endereco em uma linha, e vazio quando nao ha endereco', function () {
    expect(Negocio::factory()->create()->enderecoEmLinha())
        ->toBe('Rua das Flores 120, Centro, Uberlândia - MG, 38400000');

    expect(Negocio::factory()->create(['logradouro' => null, 'numero' => null, 'bairro' => null,
        'cidade' => null, 'uf' => null, 'cep' => null])->enderecoEmLinha())->toBe('');
});
