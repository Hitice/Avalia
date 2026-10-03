<?php

namespace App\Actions\Etiquetas;

use App\Enums\SituacaoEtiqueta;
use App\Exceptions\Recusa;
use App\Models\Etiqueta;
use App\Models\Negocio;
use App\Support\Auditar;
use App\Support\SociosDaPlaquinha;
use Illuminate\Support\Facades\DB;

/**
 * Poe a plaquinha em campo: dono, destino e prazo.
 *
 * O valor fica GRAVADO na etiqueta, e nao lido da tabela de precos na hora de
 * mostrar. A tabela muda; o que foi cobrado nao. E a mesma regra da consulta e
 * da fatura da casa.
 *
 * O prazo sai de config, e nao do formulario: venda com validade digitada a
 * mao e venda que um dia sai com dois anos por engano, e a plaquinha fica dois
 * anos de pe sem ninguem cobrar.
 */
class VenderEtiqueta
{
    public function __construct(
        private readonly ApontarEtiqueta $apontar,
        private readonly VendaNoRazao $contabil,
    ) {}

    /**
     * @param  array{destino: string, cliente_nome: ?string, cliente_contato: ?string, titulo: ?string, valor_cents: ?int}  $dados
     */
    public function __invoke(Etiqueta $etiqueta, array $dados): Etiqueta
    {
        if ($etiqueta->situacao === SituacaoEtiqueta::Baixada) {
            throw new Recusa('Esta etiqueta foi encerrada: o código fica reservado para sempre e não volta a circular.');
        }

        ($this->apontar)($etiqueta, $dados['destino']);

        return DB::transaction(function () use ($etiqueta, $dados) {
            $primeiraVenda = $etiqueta->vendida_em === null;

            $etiqueta->update([
                'situacao' => SituacaoEtiqueta::Ativa,
                'titulo' => $dados['titulo'] ?? $etiqueta->titulo,
                'cliente_nome' => $dados['cliente_nome'] ?? $etiqueta->cliente_nome,
                'cliente_contato' => $dados['cliente_contato'] ?? $etiqueta->cliente_contato,
            ] + ($primeiraVenda ? [
                'vendida_em' => now(),
                'vence_em' => now()->addMonths((int) config('etiquetas.validade_meses')),
                'valor_cents' => $dados['valor_cents'] ?? (int) config('etiquetas.precos.placa_cents'),

                // O custo entra congelado junto com o preco, pelo mesmo motivo:
                // o fornecedor reajusta, e o lucro de um mes ja repassado nao
                // pode mudar de numero por causa disso.
                'custo_cents' => (int) config('etiquetas.custo_cents'),

                // Quem vendeu. Nulo quando quem poe a placa em campo nao e da
                // casa: cliente e produtor mexem no proprio codigo, e isso nao
                // e venda de ninguem. `staff_id` nao serve aqui porque responde
                // quem gerou a tiragem, que e sempre a administracao.
                'vendedor_id' => auth('staff')->id(),
            ] : []));

            if ($primeiraVenda) {
                // A venda E o cadastro: nome e contato viram negocio na base, e a
                // placa aponta para ele. Era isso que o link de indicacao fingia fazer.
                $this->registrarNaBase($etiqueta);

                Auditar::registrar('etiquetas.vendida', $etiqueta, [
                    'cliente' => $etiqueta->cliente_nome,
                    'vence_em' => $etiqueta->vence_em?->toDateString(),
                ]);

                // Na mesma transacao: venda sem linha no razao e divergencia
                // que so aparece na conferencia do mes.
                $this->contabil->registrar($etiqueta, SociosDaPlaquinha::resolver()['ids']);
            }

            return $etiqueta->refresh();
        });
    }

    /**
     * Acha ou cria o negocio pelo contato, e na falta dele pelo nome.
     *
     * Pelo contato primeiro: nome de loja repete, telefone nao. Sem nome nao ha o
     * que cadastrar.
     */
    private function registrarNaBase(Etiqueta $etiqueta): void
    {
        $nome = trim((string) $etiqueta->cliente_nome);

        if ($nome === '') {
            return;
        }

        $digitos = preg_replace('/\D/', '', (string) $etiqueta->cliente_contato);
        $whatsapp = strlen($digitos) >= 8 ? $digitos : null;

        $negocio = ($whatsapp
            ? Negocio::where('whatsapp', $whatsapp)->first()
            : Negocio::whereRaw('LOWER(nome) = ?', [mb_strtolower($nome)])->first())
            ?? new Negocio(['nome' => $nome, 'situacao' => 'recebido', 'origem' => 'venda']);

        $negocio->fill([
            'whatsapp' => $negocio->whatsapp ?: $whatsapp,
            'responsavel' => $negocio->responsavel ?: ($whatsapp ? null : (trim((string) $etiqueta->cliente_contato) ?: null)),
            'vendedor_id' => $negocio->vendedor_id ?: auth('staff')->id(),
        ])->save();

        $etiqueta->update(['negocio_id' => $negocio->id]);

        if ($negocio->contato_id) {
            \App\Crm\Contatos::anotar($negocio->fresh()->contato ?? \App\Crm\Contatos::vincular($negocio, 'venda'), 'venda', 'Plaquinha '.$etiqueta->codigo.' vendida');
        }
    }
}
