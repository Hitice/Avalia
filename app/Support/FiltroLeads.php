<?php

namespace App\Support;

use App\Models\Lead;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Filtros da base de leads.
 *
 * Irmao do FiltroClientes, e aqui o filtro faz mais do que procurar: e ele que
 * monta o recorte que vai ser compartilhado com um vendedor. "Os leads de
 * Uberlandia com telefone" e uma decisao comercial, e precisa caber no endereco
 * da tela para virar link e para a acao em lote agir sobre exatamente o que
 * esta na tela.
 *
 * A busca aceita nome, CNPJ ou codigo da base de origem no mesmo campo: quem
 * procura um lead tem um dos tres na mao e nao sabe qual a tela espera.
 */
final class FiltroLeads
{
    /** @var array<string, string> */
    public const SITUACOES = [
        '' => 'Todas',
        'ativo' => 'Ativo',
        'inativo' => 'Inativo',
    ];

    /** @var array<string, string> */
    public const CONTATOS = [
        '' => 'Qualquer',
        'telefone' => 'Com telefone',
        'email' => 'Com e-mail',
        'algum' => 'Com telefone ou e-mail',
        'nenhum' => 'Sem contato',
    ];

    /** @var array<string, string> */
    public const DOCUMENTOS = [
        '' => 'Qualquer',
        'com' => 'Com CNPJ',
        'sem' => 'Sem CNPJ',
    ];

    /**
     * O que o operador escolheu, ja normalizado.
     *
     * Valor fora das opcoes conhecidas volta ao padrao em vez de virar erro: o
     * endereco da tela e feito para ser colado e editado a mao.
     *
     * @return array{busca: string, uf: string, cidade: string, origem: string, situacao: string, contato: string, documento: string, vendedor: string, removidos: bool}
     */
    public static function escolhido(Request $pedido): array
    {
        $situacao = (string) $pedido->query('situacao', '');
        $contato = (string) $pedido->query('contato', '');
        $documento = (string) $pedido->query('documento', '');

        return [
            'busca' => trim((string) $pedido->query('busca', '')),
            'uf' => mb_strtoupper(trim((string) $pedido->query('uf', ''))),
            'cidade' => trim((string) $pedido->query('cidade', '')),
            'origem' => trim((string) $pedido->query('origem', '')),
            'situacao' => array_key_exists($situacao, self::SITUACOES) ? $situacao : '',
            'contato' => array_key_exists($contato, self::CONTATOS) ? $contato : '',
            'documento' => array_key_exists($documento, self::DOCUMENTOS) ? $documento : '',
            // Aceita id de vendedor e tambem "sem", que e a pergunta que a
            // administracao faz antes de distribuir: o que ainda esta parado.
            'vendedor' => (string) $pedido->query('vendedor', ''),
            'removidos' => $pedido->boolean('removidos'),
        ];
    }

    /**
     * @param  Builder<Lead>  $leads
     * @return Builder<Lead>
     */
    public static function aplicar(Builder $leads, Request $pedido): Builder
    {
        $escolha = self::escolhido($pedido);

        if ($escolha['busca'] !== '') {
            $termo = '%'.$escolha['busca'].'%';
            // Os digitos do CNPJ sao comparados sem mascara, porque o operador
            // cola do jeito que recebeu.
            $digitos = Documento::normalizarCnpj($escolha['busca']);

            $leads->where(function (Builder $q) use ($termo, $digitos, $escolha) {
                $q->where('nome', 'like', $termo)
                    ->orWhere('codigo', $escolha['busca'])
                    ->orWhere('cidade', 'like', $termo)
                    ->orWhere('telefone', 'like', $termo)
                    ->orWhere('email', 'like', $termo);

                if ($digitos !== '') {
                    $q->orWhere('cnpj', 'like', '%'.$digitos.'%');
                }
            });
        }

        if ($escolha['uf'] !== '') {
            $leads->where('uf', $escolha['uf']);
        }

        if ($escolha['cidade'] !== '') {
            $leads->where('cidade', $escolha['cidade']);
        }

        if ($escolha['origem'] !== '') {
            $leads->where('origem', $escolha['origem']);
        }

        if ($escolha['situacao'] !== '') {
            $leads->where('ativo', $escolha['situacao'] === 'ativo');
        }

        match ($escolha['contato']) {
            'telefone' => $leads->whereNotNull('telefone'),
            'email' => $leads->whereNotNull('email'),
            'algum' => $leads->where(fn (Builder $q) => $q->whereNotNull('telefone')->orWhereNotNull('email')),
            'nenhum' => $leads->whereNull('telefone')->whereNull('email'),
            default => null,
        };

        match ($escolha['documento']) {
            'com' => $leads->whereNotNull('cnpj'),
            'sem' => $leads->whereNull('cnpj'),
            default => null,
        };

        if ($escolha['vendedor'] === 'sem') {
            $leads->whereDoesntHave('vendedores');
        } elseif ($escolha['vendedor'] !== '') {
            $leads->whereHas('vendedores', fn (Builder $q) => $q->where('staff.id', (int) $escolha['vendedor']));
        }

        return $leads;
    }
}
