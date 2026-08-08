<?php

use App\Models\CobrancaAsaas;
use App\Models\Fatura;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Para onde o e-mail de cobranca leva.
 *
 * O botao tem que abrir a pagina de pagamento do provedor, com boleto, Pix e
 * cartao e sem pedir login: mandar o cliente para uma tela de senha na hora de
 * pagar e perder pagamento. Quando a cobranca nao foi emitida, o portal
 * responde, porque botao que nao abre nada e pior que uma tela que explica.
 */
function faturaParaPagar(?string $url): Fatura
{
    $empresa = empresaComPlano();

    // Fatura pelo caminho de producao: o e-mail vai falar de uma fatura de
    // verdade, com o mesmo formato de valor e vencimento que o cliente recebe.
    $servico = App\Models\Servico::firstWhere('codigo', 'scpc-bvs');
    app(App\Actions\Consumo\RegistrarConsulta::class)($empresa, $servico, 10);

    $fatura = app(App\Actions\Consumo\FecharCompetencia::class)(
        $empresa, App\Models\Consulta::competenciaDe(),
    )['fatura'];

    if ($url !== null) {
        CobrancaAsaas::updateOrCreate(
            ['fatura_id' => $fatura->id],
            ['cliente_id' => $empresa->id, 'asaas_charge_id' => 'pay_teste', 'invoice_url' => $url],
        );
    }

    return $fatura->fresh();
}

it('abre a fatura do provedor quando a cobranca esta emitida', function () {
    $fatura = faturaParaPagar('https://www.asaas.com/i/abc123');

    $corpo = (new App\Mail\FaturaEmitida($fatura))->render();

    expect($corpo)->toContain('https://www.asaas.com/i/abc123')
        ->and($corpo)->toContain('Pagar minha fatura')
        ->and($corpo)->not->toContain(route('empresa.faturas'));
});

it('cai no portal quando a cobranca nao foi emitida', function () {
    $fatura = faturaParaPagar(null);

    $corpo = (new App\Mail\FaturaEmitida($fatura))->render();

    expect($corpo)->toContain(route('empresa.faturas'))
        ->and($corpo)->toContain('Ver minha fatura');
});

it('leva ao pagamento em todos os e-mails que cobram', function () {
    $fatura = faturaParaPagar('https://www.asaas.com/i/abc123');

    $emails = [
        new App\Mail\FaturaEmitida($fatura),
        new App\Mail\FaturaVencida($fatura),
        new App\Mail\VencimentoProximo($fatura),
        new App\Mail\ConsultasBloqueadas($fatura),
    ];

    foreach ($emails as $email) {
        expect($email->render())->toContain('https://www.asaas.com/i/abc123');
    }
});
