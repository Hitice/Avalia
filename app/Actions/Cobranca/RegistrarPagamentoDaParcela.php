<?php

namespace App\Actions\Cobranca;

use App\Models\EventoAsaas;
use App\Models\Lancamento360;
use App\Models\Parcela360;
use App\Support\Cascata;
use Illuminate\Support\Facades\DB;

/**
 * Da baixa numa parcela e escreve o razao dela, linha a linha.
 *
 * Com a rede, uma parcela paga varia gente: o vendedor, quem o trouxe, o topo,
 * e a casa com o que sobra. Cada um ganha o proprio lancamento, porque extrato
 * de parceiro que e um numero agregado ninguem consegue conferir.
 *
 * As linhas continuam somando ZERO. O bruto entra positivo; a taxa do
 * provedor, cada repasse e a parte da casa saem negativos. Conferir um pedido e
 * somar a coluna: se nao der zero, faltou lancamento ou sobrou.
 *
 * Idempotente pela presenca do lancamento bruto, com a checagem dentro da
 * transacao e a parcela travada: o provedor reenvia o mesmo pagamento sob ids
 * de evento diferentes ao passar de PAYMENT_CONFIRMED para PAYMENT_RECEIVED, e
 * dois workers simultaneos dobrariam o razao sem que a soma zero denunciasse.
 */
class RegistrarPagamentoDaParcela
{
    public function __construct(private readonly GerarCarne $gerarCarne) {}

    public function __invoke(Parcela360 $parcela, array $pagamento, ?EventoAsaas $evento = null): void
    {
        $pedido = $parcela->pedido;

        $pagoCents = isset($pagamento['value'])
            ? (int) round(((float) $pagamento['value']) * 100)
            : $parcela->valor_cents;

        // netValue e o que sobrou depois da taxa do provedor, e e sobre ele que
        // o split incide. Usar o bruto aqui faria o razao contar uma historia
        // diferente da que o extrato do parceiro conta.
        $liquidoCents = isset($pagamento['netValue'])
            ? (int) round(((float) $pagamento['netValue']) * 100)
            : $pagoCents;

        $taxaProvedor = max(0, $pagoCents - $liquidoCents);

        $linha = $pedido->produtor->linhaDeComissao();
        $divisao = Cascata::emCentavos($linha, $liquidoCents);

        $quando = isset($pagamento['paymentDate'])
            ? new \DateTimeImmutable($pagamento['paymentDate'])
            : now()->toDateTimeImmutable();

        $rotulo = $parcela->ehEntrada()
            ? 'Entrada do pedido '.$pedido->id
            : 'Parcela '.$parcela->numero.' do pedido '.$pedido->id;

        DB::transaction(function () use ($parcela, $pedido, $divisao, $taxaProvedor, $pagoCents, $quando, $evento, $rotulo) {
            $parcela = $parcela->newQuery()->lockForUpdate()->find($parcela->id);

            if (Lancamento360::where('parcela_360_id', $parcela->id)->where('tipo', 'bruto')->exists()) {
                return;
            }

            $lancar = fn (string $tipo, int $valor, ?int $beneficiario = null, ?string $descricao = null) => Lancamento360::create([
                'pedido_360_id' => $pedido->id,
                'parcela_360_id' => $parcela->id,
                'beneficiario_id' => $beneficiario,
                'tipo' => $tipo,
                'valor_cents' => $valor,
                'ocorrido_em' => $quando,
                'evento_asaas_id' => $evento?->id,
                'descricao' => $descricao ?? $rotulo,
            ]);

            $lancar('bruto', $pagoCents);

            if ($taxaProvedor > 0) {
                $lancar('taxa_provedor', -$taxaProvedor);
            }

            // Um repasse por parceiro da linha, na ordem em que ela sobe.
            foreach ($divisao['beneficiarios'] as $b) {
                if ($b['valor_cents'] <= 0) {
                    continue;
                }

                $lancar('repasse', -$b['valor_cents'], $b['id'] ?? null, $rotulo.' para '.$b['nome']);
            }

            if ($divisao['casa_cents'] !== 0) {
                $lancar('taxa_plataforma', -$divisao['casa_cents']);
            }

            $parcela->update(['situacao' => 'paga', 'paga_em' => $quando]);

            // Entrada paga com contrato ja assinado fecha a venda na hora: o
            // carne nasce aqui, e nao numa rotina noturna.
            if ($parcela->ehEntrada()) {
                ($this->gerarCarne)($pedido->fresh());
            }

            if ($pedido->parcelas()->emAberto()->doesntExist()) {
                $pedido->update(['situacao_financeira' => 'quitado']);
            } elseif ($pedido->situacao_financeira === 'inadimplente') {
                $pedido->update(['situacao_financeira' => 'adimplente']);
            }
        });
    }
}
