<?php

namespace App\Actions\Planilha;

use App\Models\Cliente;
use App\Support\Dinheiro;
use App\Support\Documento;
use App\Support\Planilha;
use App\Support\Rotulos;
use Illuminate\Support\Collection;

/**
 * A carteira de clientes numa planilha.
 *
 * Exporta exatamente o que o filtro da tela selecionou, e nao a base inteira:
 * quem exporta acabou de montar um recorte, e receber outra coisa obriga a
 * refazer o filtro no Excel.
 *
 * Cadastro e situacao, nada de dinheiro interno. Custo, lucro, margem e
 * comissao ficam de fora mesmo sendo uma planilha da administracao, porque
 * arquivo circula: ele vai para o e-mail, para o WhatsApp e para o computador
 * de quem nao deveria ver (PDD.md, secao 6). Faturamento do proprio cliente
 * entra, porque e o que ele paga e ele mesmo sabe.
 *
 * CPF do responsavel fica de fora pelo mesmo motivo: e dado de pessoa, e a
 * planilha existe para trabalhar a carteira, nao para carregar documento de
 * ninguem.
 */
class MontarPlanilhaClientes
{
    /** @param  Collection<int, Cliente>  $clientes */
    public function __invoke(Collection $clientes): string
    {
        return Planilha::xlsx([
            'Clientes' => [
                [
                    'Razão social', 'CNPJ', 'Situação', 'Plano', 'Consumo mínimo',
                    'Mensalidade', 'Vendedor', 'Responsável', 'E-mail', 'Telefone',
                    'Cidade', 'UF', 'Cliente desde',
                ],
                $clientes->map(fn (Cliente $cliente) => [
                    $cliente->razao_social,
                    Documento::formatarCnpj($cliente->cnpj),
                    Rotulos::empresa($cliente->situacao),
                    $cliente->plano?->nome ?? 'Sem plano',
                    $cliente->plano ? Dinheiro::brl($cliente->plano->consumo_minimo_cents) : '',
                    $cliente->plano ? Dinheiro::brl($cliente->plano->mensalidade_cents) : '',
                    $cliente->vendedor?->nome ?? '',
                    $cliente->responsavel_nome ?? '',
                    $cliente->email,
                    $cliente->telefone ?? '',
                    $cliente->cidade ?? '',
                    $cliente->uf ?? '',
                    $cliente->created_at?->format('d/m/Y') ?? '',
                ])->values()->all(),
            ],
        ]);
    }
}
