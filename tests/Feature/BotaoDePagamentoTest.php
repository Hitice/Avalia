<?php

use App\Models\CobrancaAsaas;
use App\Models\Fatura;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Para onde o e-mail de cobranca leva.
 *
 * O botao abre o boleto em PDF, que e o que a pessoa espera ver e o que ela
 * anexa ao pagamento. Sem boleto, a pagina de pagamento do provedor; sem
 * cobranca nenhuma, o portal. Nenhum dos dois primeiros pede login: mandar o
 * cliente para uma tela de senha na hora de pagar e perder pagamento.
 */
function faturaParaPagar(?string $url, ?string $boleto = null): Fatura
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
            ['cliente_id' => $empresa->id, 'asaas_charge_id' => 'pay_teste',
                'invoice_url' => $url, 'bank_slip_url' => $boleto],
        );
    }

    return $fatura->fresh();
}

it('abre o boleto em PDF quando o provedor devolveu um', function () {
    $fatura = faturaParaPagar('https://www.asaas.com/i/abc123', 'https://www.asaas.com/b/pdf/abc123');

    $corpo = (new App\Mail\FaturaEmitida($fatura))->render();

    expect($corpo)->toContain('https://www.asaas.com/b/pdf/abc123')
        ->and($corpo)->toContain('Ver minha fatura')
        ->and($corpo)->not->toContain(route('empresa.faturas'));
});

it('cai na pagina de pagamento quando ainda nao ha boleto', function () {
    $fatura = faturaParaPagar('https://www.asaas.com/i/abc123');

    expect((new App\Mail\FaturaEmitida($fatura))->render())
        ->toContain('https://www.asaas.com/i/abc123');
});

it('cai no portal quando a cobranca nao foi emitida', function () {
    $fatura = faturaParaPagar(null);

    $corpo = (new App\Mail\FaturaEmitida($fatura))->render();

    expect($corpo)->toContain(route('empresa.faturas'))
        ->and($corpo)->toContain('Ver minha fatura');
});

it('leva ao pagamento em todos os e-mails que cobram', function () {
    $fatura = faturaParaPagar('https://www.asaas.com/i/abc123', 'https://www.asaas.com/b/pdf/abc123');

    $emails = [
        new App\Mail\FaturaEmitida($fatura),
        new App\Mail\FaturaVencida($fatura),
        new App\Mail\VencimentoProximo($fatura),
        new App\Mail\ConsultasBloqueadas($fatura),
    ];

    foreach ($emails as $email) {
        expect($email->render())->toContain('https://www.asaas.com/b/pdf/abc123');
    }
});
