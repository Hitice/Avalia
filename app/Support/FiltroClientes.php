<?php

namespace App\Support;

use App\Models\Cliente;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Filtros da lista de clientes.
 *
 * Irmao do FiltroConsultas e pelo mesmo motivo: as perguntas que a operacao faz
 * sobre a carteira sao sempre as mesmas (de quem e, em que plano esta, esta
 * ativo ou suspenso), e mante-las em um lugar so evita que a tela e a planilha
 * comecem a divergir na contagem.
 *
 * Aqui a busca por documento e permitida, e a diferenca em relacao a consulta e
 * proposital: o CNPJ do contratante e dado cadastral da propria carteira, e nao
 * o documento de um terceiro consultado. Ainda assim so o CNPJ, nunca CPF de
 * responsavel, porque esse e dado de pessoa e nao precisa ir para a barra de
 * enderecos.
 */
final class FiltroClientes
{
    /** @var array<string, string> */
    public const SITUACOES = [
        '' => 'Todas',
        'ativo' => 'Ativa',
        'suspenso' => 'Suspensa',
        'inativo' => 'Inativa',
    ];

    /**
     * O que o operador escolheu, ja normalizado.
     *
     * Valor fora das opcoes conhecidas volta ao padrao em vez de virar erro: o
     * endereco da tela e feito para ser colado e editado a mao.
     *
     * @return array{busca: string, situacao: string, vendedor: string, plano: string, removidas: bool}
     */
    public static function escolhido(Request $pedido): array
    {
        $situacao = (string) $pedido->query('situacao', '');

        return [
            'busca' => trim((string) $pedido->query('busca', '')),
            'situacao' => array_key_exists($situacao, self::SITUACOES) ? $situacao : '',
            'vendedor' => (string) $pedido->query('vendedor', ''),
            'plano' => (string) $pedido->query('plano', ''),
            'removidas' => $pedido->boolean('removidas'),
        ];
    }

    /**
     * @param  Builder<Cliente>  $clientes
     * @return Builder<Cliente>
     */
    public static function aplicar(Builder $clientes, Request $pedido): Builder
    {
        $escolha = self::escolhido($pedido);

        if ($escolha['busca'] !== '') {
            // Razao social OU CNPJ no mesmo campo: quem procura um cliente tem
            // na mao um dos dois, e nunca sabe de antemao qual campo a tela
            // espera. Os digitos do CNPJ sao comparados sem mascara, porque o
            // operador cola do jeito que recebeu.
            $termo = '%'.$escolha['busca'].'%';
            $digitos = preg_replace('/\D/', '', $escolha['busca']) ?: '';

            $clientes->where(function (Builder $q) use ($termo, $digitos) {
                $q->where('razao_social', 'like', $termo);

                if ($digitos !== '') {
                    $q->orWhere('cnpj', 'like', '%'.$digitos.'%');
                }
            });
        }

        if ($escolha['situacao'] !== '') {
            $clientes->where('situacao', $escolha['situacao']);
        }

        if ($escolha['vendedor'] !== '') {
            $clientes->where('vendedor_id', (int) $escolha['vendedor']);
        }

        if ($escolha['plano'] !== '') {
            $clientes->where('plano_id', (int) $escolha['plano']);
        }

        return $clientes;
    }
}
