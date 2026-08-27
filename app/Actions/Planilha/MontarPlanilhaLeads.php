<?php

namespace App\Actions\Planilha;

use App\Models\Lead;
use App\Support\Documento;
use App\Support\Planilha;
use Illuminate\Support\Collection;

/**
 * O recorte de leads que esta na tela, numa planilha.
 *
 * Exporta o que o filtro selecionou, e nao a base inteira: quem exporta acabou
 * de montar um recorte, e receber outra coisa obriga a refazer o filtro no
 * Excel. E o mesmo princípio da planilha de clientes.
 *
 * Sai com a coluna de quem esta trabalhando o lead, porque e a pergunta que a
 * administracao leva para a reuniao de venda: quem ficou com o que.
 */
class MontarPlanilhaLeads
{
    /** @param  Collection<int, Lead>  $leads */
    public function __invoke(Collection $leads): string
    {
        return Planilha::xlsx([
            'Leads' => [
                [
                    'Código', 'Nome', 'CNPJ', 'Cidade', 'UF', 'Telefone',
                    'E-mail', 'Responsável', 'Situação', 'Agendado para', 'Origem', 'Compartilhado com',
                ],
                $leads->map(fn (Lead $lead) => [
                    $lead->codigo ?? '',
                    $lead->nome,
                    Documento::formatarCnpj($lead->cnpj),
                    $lead->cidade ?? '',
                    $lead->uf ?? '',
                    $lead->telefone ?? '',
                    $lead->email ?? '',
                    $lead->responsavel_nome ?? '',
                    $lead->situacao->rotulo(),
                    $lead->agendado_para?->format('d/m/Y H:i') ?? '',
                    $lead->origem ?? '',
                    $lead->vendedores->pluck('nome')->implode(', '),
                ])->values()->all(),
            ],
        ]);
    }
}
