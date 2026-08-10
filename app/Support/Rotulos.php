<?php

namespace App\Support;

use App\Models\Fatura;

/**
 * Como cada situacao se chama na tela.
 *
 * As telas vinham imprimindo o valor gravado no banco: "liquidado", "vencido",
 * "inadimplente", em minusculas. Sao nomes escolhidos para o codigo, nao para
 * quem le. O cliente que abre a fatura precisa entender "Paga" ou "Em aberto";
 * "liquidado" e vocabulario de quem escreveu a coluna.
 *
 * O mapa vive aqui e nao em cada Blade porque a mesma situacao aparece na tela
 * do cliente, na do vendedor e na da administracao. Tres listas soltas viram
 * tres nomes diferentes para o mesmo estado no primeiro ajuste.
 *
 * A cor acompanha o nome pelo mesmo motivo: fatura vencida vermelha em uma tela
 * e cinza em outra ensina o operador a nao confiar na cor.
 */
final class Rotulos
{
    /** @var array<string, array{rotulo: string, etiqueta: string}> */
    private const EMPRESA = [
        'ativo' => ['rotulo' => 'Ativa', 'etiqueta' => 'etiqueta-sucesso'],
        'inadimplente' => ['rotulo' => 'Suspensa por débito', 'etiqueta' => 'etiqueta-alerta'],
        'bloqueado' => ['rotulo' => 'Bloqueada', 'etiqueta' => 'etiqueta-erro'],
        'inativo' => ['rotulo' => 'Encerrada', 'etiqueta' => 'etiqueta-neutra'],
    ];

    /** @var array<string, array{rotulo: string, etiqueta: string}> */
    private const FATURA = [
        Fatura::PAGAMENTO_PENDENTE => ['rotulo' => 'Em aberto', 'etiqueta' => 'etiqueta-neutra'],
        Fatura::PAGAMENTO_VENCIDO => ['rotulo' => 'Vencida', 'etiqueta' => 'etiqueta-alerta'],
        Fatura::PAGAMENTO_LIQUIDADO => ['rotulo' => 'Paga', 'etiqueta' => 'etiqueta-sucesso'],
        Fatura::PAGAMENTO_CANCELADO => ['rotulo' => 'Cancelada', 'etiqueta' => 'etiqueta-erro'],
        Fatura::PAGAMENTO_ESTORNADO => ['rotulo' => 'Estornada', 'etiqueta' => 'etiqueta-erro'],
    ];

    public static function empresa(?string $situacao): string
    {
        return self::EMPRESA[$situacao]['rotulo'] ?? 'Não definida';
    }

    public static function empresaEtiqueta(?string $situacao): string
    {
        return self::EMPRESA[$situacao]['etiqueta'] ?? 'etiqueta-neutra';
    }

    public static function fatura(?string $situacao): string
    {
        return self::FATURA[$situacao]['rotulo'] ?? 'Não definida';
    }

    public static function faturaEtiqueta(?string $situacao): string
    {
        return self::FATURA[$situacao]['etiqueta'] ?? 'etiqueta-neutra';
    }

    /** Opcoes do campo de situacao da empresa, na ordem do ciclo de vida. */
    public static function situacoesDaEmpresa(): array
    {
        return array_map(fn (array $item) => $item['rotulo'], self::EMPRESA);
    }

    /**
     * Espera do cliente, em segundos.
     *
     * O banco guarda milissegundos porque e a unidade que o fornecedor devolve.
     * Ninguem decide nada com "847 ms": a pergunta de quem opera e se o cliente
     * espera muito, e isso se mede em segundos.
     */
    public static function espera(?int $milissegundos): string
    {
        if ($milissegundos === null) {
            return 'Sem registro';
        }

        return number_format($milissegundos / 1000, 1, ',', '.').' s';
    }

    /*
    |--------------------------------------------------------------------------
    | Trilha de auditoria
    |--------------------------------------------------------------------------
    |
    | A trilha grava chaves de codigo (fatura.liquidada, total_cents) e quem le
    | e o administrador. Toda acao registrada TEM que ter nome aqui: um teste
    | varre o codigo e derruba a suite quando alguem registra acao nova sem
    | rotulo, porque "Acao registrada" nao explica nada a ninguem.
    |
    */

    /** @var array<string, string> */
    private const ACOES = [
        'fatura.fechada' => 'Fatura emitida',
        'fatura.liquidada' => 'Pagamento confirmado',
        'fatura.estornada' => 'Pagamento estornado',
        'fatura.segunda_via' => 'Segunda via da fatura pedida',
        'preco.alvo' => 'Preço subiu para a margem alvo',
        'clientes.exportados' => 'Carteira de clientes exportada',
        'faturas.exportadas' => 'Faturas exportadas em planilha',
        'cobranca.criada' => 'Cobrança emitida',
        'cliente.inadimplente' => 'Empresa suspensa por débito',
        'empresa.removida' => 'Empresa removida',
        'empresa.excluida' => 'Empresa excluída em definitivo',
        'empresa.situacao' => 'Situação da empresa alterada',
        'equipe.excluida' => 'Cadastro da equipe excluído em definitivo',
        'empresa.restaurada' => 'Empresa restaurada',
        'adesao.atualizada' => 'Adesão atualizada',
        'equipe.criada' => 'Pessoa cadastrada na equipe',
        'equipe.alterada' => 'Cadastro da equipe alterado',
        'equipe.removida' => 'Pessoa removida da equipe',
        'equipe.restaurada' => 'Pessoa restaurada na equipe',
        'equipe.convite_enviado' => 'Redefinição de senha enviada',
        'acesso.senha_definida' => 'Senha definida pelo dono da conta',
        'documento.publicado' => 'Documento publicado',
        'documento.retirado' => 'Documento retirado de circulação',
        'campanha.alterada' => 'Campanha alterada',
        'acesso.senha_trocada' => 'Senha trocada pelo dono da conta',
        'documento.aceito' => 'Documento aceito',
        'consulta.sucesso' => 'Consulta concluída',
        'consulta.falha' => 'Consulta não concluída',
        'consulta.expurgada' => 'Resposta apagada no prazo de retenção',
        'campanha.criada' => 'Campanha criada',
        'campanha.encerrada' => 'Campanha encerrada',
        'campanha.reaberta' => 'Campanha reaberta',
        'interessado.atendido' => 'Pedido de contato atendido',
        'operador.criado' => 'Operador criado para a empresa',
        'operador.desativado' => 'Acesso de operador desativado',
        'operador.reativado' => 'Acesso de operador reativado',
        'conexao.atualizada' => 'Conexão atualizada',
        'conexao.ativada' => 'Conexão ativada',
        'conexao.desativada' => 'Conexão desativada',
        'conexao.testada' => 'Conexão testada',
    ];

    /** @var array<class-string, string> */
    private const ENTIDADES = [
        \App\Models\Fatura::class => 'Fatura',
        \App\Models\Cliente::class => 'Empresa',
        \App\Models\Staff::class => 'Equipe',
        \App\Models\DocumentoLegal::class => 'Documento',
        \App\Models\AceiteDocumento::class => 'Aceite',
        \App\Models\Consulta::class => 'Consulta',
        \App\Models\Campanha::class => 'Campanha',
        \App\Models\CobrancaAsaas::class => 'Cobrança',
        \App\Models\Adesao::class => 'Adesão',
        \App\Models\Interessado::class => 'Pedido de contato',
        \App\Models\Conexao::class => 'Conexão',
        \App\Models\Operador::class => 'Operador',
    ];

    /** @var array<string, string> */
    private const DETALHES = [
        'competencia' => 'Período',
        'total_cents' => 'Valor da fatura',
        'valor_cents' => 'Valor',
        'franquia_cents' => 'Valor incluído na franquia',
        'faixa_cents' => 'Faixa de consumo mínimo',
        'faturas' => 'Faturas',
        'de_cents' => 'De',
        'margem_alvo_bps' => 'Margem alvo da faixa',
        'para_cents' => 'Para',
        'comissao_liberada_cents' => 'Comissão liberada',
        'comissao_recolhida_cents' => 'Comissão recolhida',
        'fatura_id' => 'Fatura',
        'cliente_id' => 'Empresa',
        'razao_social' => 'Empresa',
        'versao' => 'Versão',
        'hash_conteudo' => 'Comprovante do conteúdo',
        'origem' => 'Origem',
        'motivo' => 'Motivo',
        'servico' => 'Serviço',
        'servicos' => 'Serviços',
        'clientes' => 'Clientes',
        'finalidade' => 'Finalidade',
        'fornecedor' => 'Fornecedor',
        'campos' => 'Campos alterados',
        'email' => 'E-mail',
        'guarda' => 'Tipo de conta',
        'tipo' => 'Tipo',
        'quantidade' => 'Quantidade',
        'parcelas' => 'Parcelas',
        'ate' => 'Até',
        'de' => 'De',
        'para' => 'Para',
        'ok' => 'Resultado',
    ];

    public static function acao(?string $acao): string
    {
        // O reserva humaniza a chave em vez de esconder o assunto: melhor a
        // tela dizer "consulta expurgada" cru do que "Acao registrada".
        return self::ACOES[$acao] ?? ucfirst(str_replace(['.', '_'], ' ', (string) $acao));
    }

    public static function acoesDaTrilha(): array
    {
        return self::ACOES;
    }

    public static function entidadeAuditada(?string $classe): string
    {
        return self::ENTIDADES[$classe] ?? 'Registro';
    }

    public static function detalheAuditado(string $chave): string
    {
        return self::DETALHES[$chave] ?? ucfirst(str_replace('_', ' ', $chave));
    }

    /** O valor de um detalhe da trilha, legivel: dinheiro, sim/nao, listas. */
    public static function valorAuditado(string $chave, mixed $valor): string
    {
        if ($chave === 'hash_conteudo') {
            return 'registrado';
        }

        if ($chave === 'guarda') {
            return $valor === 'staff' ? 'Equipe' : 'Empresa';
        }

        if (str_ends_with($chave, '_cents')) {
            return Dinheiro::brl((int) $valor);
        }

        if (is_bool($valor)) {
            return $valor ? 'Sim' : 'Não';
        }

        if (is_array($valor)) {
            return implode(', ', array_map(strval(...), $valor));
        }

        return (string) $valor;
    }
}
