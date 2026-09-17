<?php

namespace App\Actions\Cobranca;

use App\Models\Oferta360;
use App\Models\Parcela360;
use App\Models\Pedido360;
use App\Support\AnaliseDeCredito;
use App\Support\Documento;
use Illuminate\Support\Facades\DB;

/**
 * Transforma um checkout preenchido em pedido analisado.
 *
 * A analise roda ANTES de existir cobranca: proposta recusada nao gera boleto,
 * e boleto gerado por engano vira cliente ligando para perguntar de uma compra
 * que o sistema ja tinha negado.
 *
 * O pedido copia valor, parcelas e taxa da oferta. A oferta pode ser editada
 * amanha; o que este cliente contratou hoje nao muda com ela.
 *
 * Quem emite a cobranca da entrada e outra acao, chamada depois: separar deixa
 * o pedido gravado mesmo quando o provedor esta fora do ar, e o boleto pode
 * ser emitido de novo sem refazer a venda.
 */
class AbrirPedido
{
    public function __invoke(Oferta360 $oferta, array $cliente): Pedido360
    {
        $documento = Documento::normalizarCnpj($cliente['documento'] ?? '');
        $nascimento = $cliente['nascimento'] ?? null;

        // Uma compra em aberto por documento. A contagem olha o pedido, e nao
        // a parcela: quem deve a compra anterior nao abre a proxima.
        $emAberto = Pedido360::query()
            ->whereIn('situacao', ['em_analise', 'aguardando_contrato', 'aguardando_entrada', 'efetivado'])
            ->where('situacao_financeira', '!=', 'quitado')
            ->get()
            ->filter(fn (Pedido360 $p) => Documento::normalizarCnpj($p->cliente_documento) === $documento)
            ->count();

        $decisao = AnaliseDeCredito::decidir([
            'documento' => $documento,
            'nascimento' => $nascimento,
            'financiado_cents' => $oferta->valor_cents - $oferta->entrada_cents,
            'parcelas' => $oferta->parcelas,
            'pedidos_em_aberto' => $emAberto,
            'restricao' => $cliente['restricao'] ?? false,
            'na_lista_negra' => $cliente['na_lista_negra'] ?? false,
        ]);

        $aprovado = $decisao['decisao'] === AnaliseDeCredito::APROVADO;

        // Teto menor que o pedido nao recusa a venda: reduz o parcelamento. E
        // a diferenca entre perder o cliente e vender em seis vezes.
        $parcelas = $aprovado ? min($oferta->parcelas, $decisao['teto_parcelas']) : $oferta->parcelas;

        return DB::transaction(function () use ($oferta, $cliente, $documento, $nascimento, $decisao, $aprovado, $parcelas) {
            $valorParcela = $parcelas > 0
                ? intdiv($oferta->valor_cents - $oferta->entrada_cents, $parcelas)
                : 0;

            $pedido = Pedido360::create([
                'oferta_360_id' => $oferta->id,
                'produtor_id' => $oferta->produto->produtor_id,
                'cliente_nome' => $cliente['nome'],
                'cliente_documento' => $documento,
                'cliente_email' => mb_strtolower(trim((string) $cliente['email'])),
                'cliente_telefone' => preg_replace('/\D/', '', (string) ($cliente['telefone'] ?? '')),
                'cliente_nascimento' => $nascimento,
                'cliente_endereco' => $cliente['endereco'] ?? null,
                'situacao' => $aprovado ? 'aguardando_contrato' : 'reprovado',
                'valor_total_cents' => $oferta->valor_cents,
                'entrada_cents' => $oferta->entrada_cents,
                'parcelas' => $parcelas,
                'valor_parcela_cents' => $valorParcela,
                'taxa_bps' => (int) config('cobranca.taxa_bps'),
                'melhor_dia' => $cliente['melhor_dia'] ?? null,
                'analise_versao' => $decisao['versao'],
                'analise_motivo' => $decisao['motivo'],
                'analise_em' => now(),
            ]);

            // A entrada nasce junto com o pedido aprovado, ainda sem cobranca:
            // ela e a parcela zero, e existir desde ja e o que permite emitir
            // o boleto numa segunda tentativa sem refazer a venda.
            if ($aprovado && $oferta->entrada_cents > 0) {
                Parcela360::create([
                    'pedido_360_id' => $pedido->id,
                    'numero' => 0,
                    'valor_cents' => $oferta->entrada_cents,
                    'vencimento' => today()->addDays($oferta->entrada_em_dias),
                ]);
            }

            return $pedido->fresh();
        });
    }
}
