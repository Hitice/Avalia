<?php

namespace App\Services\Conectores;

use App\Contracts\ConectorBureau;
use App\Models\Conexao;
use App\Models\Servico;

/**
 * Qual bureau atende cada servico.
 *
 * Ate aqui o sistema escolhia UM conector para tudo, e isso so funcionava
 * enquanto havia um fornecedor. Nao ha: o catalogo mistura bases, e "Base III"
 * e a Boa Vista enquanto outras linhas vem de outro lugar. Com escolha global,
 * ligar a Boa Vista mandaria para ela ate o que ela nao vende.
 *
 * Entao a escolha e do SERVICO. Cada linha do catalogo diz de quem ela vem, e
 * a consulta pergunta a esta classe qual conector usar. Trocar de fornecedor
 * em um servico passa a ser cadastro, e nao deploy.
 *
 * A cascata, nesta ordem:
 *
 * 1. O fornecedor do proprio servico, se a conexao dele estiver ativa.
 * 2. A escolha global (config ou primeira conexao ativa), que e o que existia
 *    antes e continua valendo para servico sem fornecedor declarado.
 * 3. O simulado, que responde sem cobrar ninguem.
 *
 * O passo 1 exige conexao ATIVA de proposito: servico apontando para um bureau
 * desligado deve cair no comportamento geral, e nao falhar. Desligar uma
 * conexao e uma acao de emergencia, e emergencia nao pode derrubar o catalogo
 * inteiro junto.
 */
class EscolherConector
{
    /** @var array<string, class-string<ConectorBureau>> */
    public const CONECTORES = [
        'simulado' => ConectorSimulado::class,
        'serasa' => ConectorSerasa::class,
        'boa-vista' => ConectorBoaVista::class,
    ];

    /** Ordem de preferencia quando ninguem escolheu nada. */
    private const PREFERENCIA = ['serasa', 'quod', 'boa-vista', 'spc'];

    public function para(?Servico $servico): ConectorBureau
    {
        // A primeira base declarada, para quem so precisa de um conector.
        $primeira = trim(explode(',', (string) $servico?->fornecedor)[0] ?? '');

        return $this->conector($primeira);
    }

    /**
     * O conector de um fornecedor pelo nome, com a mesma cascata.
     *
     * Fornecedor desconhecido ou com a conexao desligada cai na escolha geral:
     * desligar uma conexao e acao de emergencia, e emergencia nao pode derrubar
     * o catalogo inteiro junto.
     */
    public function conector(string $fornecedor): ConectorBureau
    {
        // O simulado nao tem conexao para estar ativa: ele E a ausencia de
        // fornecedor. Exigir conexao dele o tornava inalcancavel justamente
        // quando alguem o escolhe de proposito para testar o fluxo.
        if ($fornecedor === 'simulado') {
            return app(ConectorSimulado::class);
        }

        if ($fornecedor !== '' && isset(self::CONECTORES[$fornecedor]) && Conexao::ativaDe($fornecedor)) {
            return app(self::CONECTORES[$fornecedor]);
        }

        return app(self::CONECTORES[self::global()] ?? self::CONECTORES['simulado']);
    }

    /**
     * A escolha que vale para servico sem fornecedor declarado.
     *
     * Config manda, e e o que os testes e a homologacao usam. Sem config, vale
     * a primeira conexao de bureau ativa na tela de Conexoes. A escolha e por
     * configuracao e nao por ambiente: amarrar ao ambiente faria producao
     * decidir sozinha usar dado falso no dia em que a credencial faltasse, e
     * ninguem perceberia.
     */
    public static function global(): string
    {
        $escolhido = (string) config('services.bureau.conector', '');

        if ($escolhido !== '') {
            return $escolhido;
        }

        foreach (self::PREFERENCIA as $fornecedor) {
            if (isset(self::CONECTORES[$fornecedor]) && Conexao::ativaDe($fornecedor)) {
                return $fornecedor;
            }
        }

        return 'simulado';
    }
}
