<?php

use App\Exceptions\Recusa;
use App\Models\Servico;
use App\Services\Conectores\ConectorSimulado;
use App\Services\Conectores\EscolherConector;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Dado inventado nao responde consulta que cobra
|--------------------------------------------------------------------------
|
| Sem credencial ativa, a cascata terminava no simulado: a empresa recebia laudo
| fabricado, era cobrada pelo preco cheio (ExecutarConsulta cobra quando a
| resposta vem com sucesso, e a do simulado vem) e a tela filtrava a palavra
| "simulado" da linha de bases. Tres coisas que, juntas, entregam dado falso
| como se fosse do bureau.
|
| O comentario de EscolherConector::global ja dizia que isso nao podia
| acontecer. O codigo fazia.
|
*/

function emProducao(callable $oQue): void
{
    app()['env'] = 'production';
    config(['app.env' => 'production']);

    try {
        $oQue();
    } finally {
        app()['env'] = 'testing';
        config(['app.env' => 'testing']);
    }
}

it('recusa a consulta em producao quando nao ha fornecedor ativo', function () {
    config(['services.bureau.conector' => '']);

    emProducao(function () {
        expect(fn () => app(EscolherConector::class)->para(null))
            ->toThrow(Recusa::class);
    });
});

it('recusa tambem quando o cadastro do servico aponta para o simulado', function () {
    // Cadastro esquecido tem o mesmo efeito que credencial ausente: o cliente
    // recebe laudo fabricado e paga por ele.
    config(['services.bureau.conector' => '']);

    $servico = new Servico(['fornecedor' => 'simulado']);

    emProducao(function () use ($servico) {
        expect(fn () => app(EscolherConector::class)->para($servico))
            ->toThrow(Recusa::class);
    });
});

it('diz na recusa o que fazer, e que nada foi cobrado', function () {
    // Recusa que nao explica vira chamado no atendimento.
    config(['services.bureau.conector' => '']);

    emProducao(function () {
        try {
            app(EscolherConector::class)->para(null);
            $this->fail('deveria ter recusado');
        } catch (Recusa $e) {
            expect($e->getMessage())->toContain('Conexões')
                ->and($e->getMessage())->toContain('nada foi cobrado');
        }
    });
});

it('deixa o simulado responder quando a instalacao escolhe isso de proposito', function () {
    // A saida e decisao explicita de quem opera o ambiente, e e o que a
    // homologacao usa.
    config(['services.bureau.conector' => 'simulado']);

    emProducao(function () {
        expect(app(EscolherConector::class)->para(null))->toBeInstanceOf(ConectorSimulado::class);
    });
});

it('continua respondendo simulado fora de producao', function () {
    // Sem isto, a suite inteira e o ambiente local parariam de consultar.
    config(['services.bureau.conector' => '']);

    expect(app(EscolherConector::class)->para(null))->toBeInstanceOf(ConectorSimulado::class);
});
