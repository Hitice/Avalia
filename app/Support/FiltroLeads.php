<?php

namespace App\Support;

use App\Enums\SituacaoLead;
use App\Models\Lead;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Filtros da base de leads.
 *
 * Uma busca livre, mais cidade e UF. Quem procura um lead tem um dado na mao (o
 * nome, o CNPJ, o telefone, o estagio) e nao sabe de antemao qual campo a tela
 * espera: sete seletores obrigavam a escolher o campo antes de digitar, e
 * escolher errado devolvia tela vazia sem dizer por que.
 *
 * Cidade e UF ficam como seletores proprios porque nao se procura por elas, se
 * recorta por elas: a pergunta e "quem eu tenho em Uberlandia", e a resposta
 * precisa da lista fechada do que existe na base, para nao errar a grafia.
 *
 * Os recortes que nao dao para digitar continuam existindo, mas entram pelo
 * endereco em vez de por um seletor: os cartoes de numero da tela sao os links.
 * "Sem vendedor" e "sem telefone" nao se escrevem numa busca de texto.
 *
 * O recorte inteiro vive na barra de enderecos, entao a tela vira link, a
 * exportacao leva exatamente o que esta nela, e a acao em lote pode agir sobre o
 * filtro inteiro e nao so sobre o que a tabela desenhou.
 */
final class FiltroLeads
{
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
     * Estagios do funil, mais dois recortes que nao sao estagio: o que segue em
     * prospeccao, e o que passou da data marcada.
     *
     * @return array<string, string>
     */
    public static function situacoes(): array
    {
        return ['' => 'Todas', 'em_aberto' => 'Em aberto', 'atrasado' => 'Agendamento vencido']
            + SituacaoLead::rotulos();
    }

    /**
     * O que o operador escolheu, ja normalizado.
     *
     * Valor fora das opcoes conhecidas volta ao padrao em vez de virar erro: o
     * endereco da tela e feito para ser colado e editado a mao.
     *
     * @return array{busca: string, uf: string, cidade: string, situacao: string, contato: string, documento: string, vendedor: string, removidos: bool}
     */
    public static function escolhido(Request $pedido): array
    {
        $situacao = (string) $pedido->query('situacao', '');
        $contato = (string) $pedido->query('contato', '');
        $documento = (string) $pedido->query('documento', '');
        $situacoes = self::situacoes();

        return [
            'busca' => trim((string) $pedido->query('busca', '')),
            'uf' => mb_strtoupper(trim((string) $pedido->query('uf', ''))),
            'cidade' => trim((string) $pedido->query('cidade', '')),
            'situacao' => array_key_exists($situacao, $situacoes) ? $situacao : '',
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
            self::buscar($leads, $escolha['busca']);
        }

        if ($escolha['uf'] !== '') {
            $leads->where('uf', $escolha['uf']);
        }

        if ($escolha['cidade'] !== '') {
            $leads->where('cidade', $escolha['cidade']);
        }

        match ($escolha['situacao']) {
            '' => null,
            'em_aberto' => $leads->emAberto(),
            'atrasado' => $leads->agendamentoVencido(),
            default => $leads->where('situacao', $escolha['situacao']),
        };

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

    /**
     * A busca de um campo so, contra tudo que o operador pode ter na mao.
     *
     * Inclui o estagio pelo nome que aparece na tela: quem digita "agendado"
     * espera os agendados, e devolver nada ensinaria a nao confiar na busca.
     *
     * @param  Builder<Lead>  $leads
     */
    private static function buscar(Builder $leads, string $termo): void
    {
        $como = '%'.$termo.'%';

        // Os digitos do CNPJ sao comparados sem mascara, porque o operador cola
        // do jeito que recebeu.
        $digitos = Documento::normalizarCnpj($termo);

        $estagios = array_keys(array_filter(
            SituacaoLead::rotulos(),
            fn (string $rotulo) => mb_stripos($rotulo, $termo) !== false,
        ));

        $leads->where(function (Builder $q) use ($como, $digitos, $termo, $estagios) {
            $q->where('nome', 'like', $como)
                ->orWhere('codigo', $termo)
                ->orWhere('cidade', 'like', $como)
                ->orWhere('telefone', 'like', $como)
                ->orWhere('email', 'like', $como)
                ->orWhere('responsavel_nome', 'like', $como)
                ->orWhere('origem', 'like', $como)
                // UF casa exata: "SP" dentro de um LIKE acharia "Serra do Sul".
                ->orWhere('uf', mb_strtoupper($termo));

            if ($digitos !== '') {
                $q->orWhere('cnpj', 'like', '%'.$digitos.'%');
            }

            if ($estagios !== []) {
                $q->orWhereIn('situacao', $estagios);
            }
        });
    }
}
