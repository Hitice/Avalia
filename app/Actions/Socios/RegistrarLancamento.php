<?php

namespace App\Actions\Socios;

use App\Contabil\Lancar;
use App\Contabil\Partidas;
use App\Enums\NaturezaLancamento;
use App\Exceptions\Recusa;
use App\Models\ContaFinanceira;
use App\Models\LancamentoFinanceiro;
use App\Models\Socio;

/**
 * Grava um evento financeiro como partidas que somam zero.
 *
 * Quem chama informa a NATUREZA, e nao as contas. A traducao mora aqui, num
 * lugar so, porque escolher conta na tela e onde nasce o erro que este modulo
 * existe para impedir:
 *
 *   aporte lancado como receita      infla o resultado do mes
 *   reembolso lancado como despesa   cobra a mesma conta duas vezes
 *   transferencia lancada como saida faz o mes parecer pior do que foi
 *
 * A conferencia final e uma soma. Se as pernas nao dao zero, nao grava: e a
 * unica invariante que pega erro de traducao sem ninguem reconferir extrato.
 *
 * Nada se atualiza depois. Corrigir e estornar, que lanca o contrario e amarra
 * os dois, porque saldo que alguem ja conferiu nao pode mudar por edicao.
 */
class RegistrarLancamento
{
    public function __construct(private readonly Lancar $lancar) {}

    /**
     * @param  array{descricao: string, competencia: string, ocorrido_em: \DateTimeInterface|string, valor_cents: int, socio_id?: int|null, conta_id?: int|null, destino_id?: int|null, contraparte?: string|null, documento?: string|null, comprovante?: string|null, origem_tipo?: string|null, origem_id?: int|null}  $dados
     */
    public function __invoke(NaturezaLancamento $natureza, array $dados): LancamentoFinanceiro
    {
        $valor = (int) ($dados['valor_cents'] ?? 0);

        if ($valor <= 0) {
            throw new Recusa('O valor precisa ser maior que zero. Para desfazer um lançamento, use o estorno.');
        }

        $this->conferirQueAReceitaNaoDuplica($natureza, $dados);

        $socio = $this->socio($natureza, $dados['socio_id'] ?? null);
        $pernas = $this->pernas($natureza, $valor, $socio, $dados);

        return ($this->lancar)(Partidas::de($pernas), [
            'natureza' => $natureza->value,
            'descricao' => $dados['descricao'],
            'competencia' => $dados['competencia'],
            'ocorrido_em' => $dados['ocorrido_em'],
            'contraparte' => $dados['contraparte'] ?? null,
            'documento' => $dados['documento'] ?? null,
            'comprovante' => $dados['comprovante'] ?? null,
            'origem_tipo' => $dados['origem_tipo'] ?? null,
            'origem_id' => $dados['origem_id'] ?? null,
        ]);
    }

    /**
     * As duas pernas de cada natureza.
     *
     * Debito positivo, credito negativo. Em conta de ativo e despesa o debito
     * aumenta; em passivo, patrimonio e receita quem aumenta e o credito. E a
     * convencao contabil de sempre, e ela e usada aqui porque fecha sozinha.
     *
     * @return array<int, int> conta_id => valor com sinal
     */
    private function pernas(NaturezaLancamento $natureza, int $valor, ?Socio $socio, array $dados): array
    {
        $caixa = $this->conta(ContaFinanceira::CAIXA, $dados['conta_id'] ?? null);

        return match ($natureza) {
            // Quita a comissao devida e reduz o caixa. A despesa ja foi lancada na
            // venda. Fora do formulario: so a tela de vendas sabe a quem se deve.
            NaturezaLancamento::Pagamento => [
                $this->conta('comissao-a-pagar') => $valor,
                $caixa => -$valor,
            ],

            // Entra dinheiro e cresce o patrimonio do socio. Receita nao entra
            // na conversa: ninguem vendeu nada.
            NaturezaLancamento::Aporte => [
                $caixa => $valor,
                $this->doSocio(ContaFinanceira::APORTE, $socio) => -$valor,
            ],

            // Entra dinheiro e nasce uma divida: ele espera de volta.
            NaturezaLancamento::Emprestimo => [
                $caixa => $valor,
                $this->doSocio(ContaFinanceira::EMPRESTIMO, $socio) => -$valor,
            ],

            // Sai dinheiro da empresa e o resultado piora.
            NaturezaLancamento::Despesa => [
                $this->conta(ContaFinanceira::DESPESA, $dados['categoria_id'] ?? null, 'despesa') => $valor,
                $caixa => -$valor,
            ],

            // A despesa existe igual, mas o caixa nao se move: quem pagou foi o
            // socio, e a empresa passa a dever a ele. Duas coisas num evento so,
            // e e por isso que esta natureza existe separada de Despesa.
            NaturezaLancamento::DespesaDoSocio => [
                $this->conta(ContaFinanceira::DESPESA, $dados['categoria_id'] ?? null, 'despesa') => $valor,
                $this->doSocio(ContaFinanceira::EMPRESTIMO, $socio) => -$valor,
            ],

            // Quita a divida e reduz o caixa. NAO gera despesa: ela ja foi
            // lancada quando o socio pagou.
            NaturezaLancamento::Reembolso => [
                $this->doSocio(ContaFinanceira::EMPRESTIMO, $socio) => $valor,
                $caixa => -$valor,
            ],

            NaturezaLancamento::Receita => [
                $caixa => $valor,
                $this->conta(ContaFinanceira::RECEITA, $dados['categoria_id'] ?? null, 'receita') => -$valor,
            ],

            // Duas contas de ativo. O resultado nao muda, e e isso que a
            // natureza propria garante.
            NaturezaLancamento::Transferencia => [
                $this->destino($dados['destino_id'] ?? null, $caixa) => $valor,
                $caixa => -$valor,
            ],

            // Reduz o que a empresa deve ao socio.
            NaturezaLancamento::Retirada => [
                $this->doSocio(ContaFinanceira::EMPRESTIMO, $socio) => $valor,
                $caixa => -$valor,
            ],

            // Reduz o patrimonio dele: e resultado sendo entregue, e nao divida
            // sendo paga.
            NaturezaLancamento::Distribuicao => [
                $this->doSocio(ContaFinanceira::APORTE, $socio) => $valor,
                $caixa => -$valor,
            ],
        };
    }

    /**
     * Receita de fatura, parcela ou plaquinha ja entra sozinha.
     *
     * Desde que a liquidacao passou a reconhecer receita, o caminho da maquina
     * nao duplica: a origem e unica no banco. O que ainda duplicaria e alguem
     * lancar a mao a receita da mesma fatura que ja entrou.
     *
     * A protecao e regra e nao aviso: receita digitada existe para o que NAO
     * tem origem no sistema, como um projeto de software fechado por fora.
     * Quem quiser corrigir uma receita automatica estorna a que existe, que e o
     * caminho que preserva o rastro.
     */
    private function conferirQueAReceitaNaoDuplica(NaturezaLancamento $natureza, array $dados): void
    {
        if ($natureza !== NaturezaLancamento::Receita) {
            return;
        }

        if (($dados['origem_tipo'] ?? null) !== null) {
            return;
        }

        $competencia = (string) ($dados['competencia'] ?? '');

        $automatica = LancamentoFinanceiro::query()
            ->where('natureza', NaturezaLancamento::Receita->value)
            ->whereNotNull('origem_tipo')
            ->where('competencia', $competencia)
            ->exists();

        if ($automatica) {
            throw new Recusa(
                'Esta competência já tem receita reconhecida automaticamente pelas liquidações. '
                .'Lançar receita à mão aqui contaria o mesmo dinheiro duas vezes. '
                .'Para corrigir uma receita automática, estorne a que existe.'
            );
        }
    }

    /** @param array<int, int> $pernas */
    /**
     * A conta que recebe a transferencia.
     *
     * Sem destino, as duas pernas cairiam na mesma conta e o lancamento
     * desapareceria numa soma zero que nao move nada. A recusa diz o que falta,
     * em vez de deixar a conferencia generica de partidas reclamar de outra
     * coisa.
     */
    private function destino(?int $destinoId, int $origem): int
    {
        if ($destinoId === null) {
            throw new Recusa('Escolha a conta de destino da transferência.');
        }

        $destino = $this->conta(ContaFinanceira::CAIXA, $destinoId);

        if ($destino === $origem) {
            throw new Recusa('A conta de destino precisa ser diferente da de origem.');
        }

        return $destino;
    }

    private function socio(NaturezaLancamento $natureza, ?int $socioId): ?Socio
    {
        if (! $natureza->exigeSocio()) {
            return null;
        }

        $socio = $socioId === null ? null : Socio::find($socioId);

        if ($socio === null) {
            throw new Recusa('Este lançamento precisa dizer de qual sócio se trata.');
        }

        return $socio;
    }

    /** A conta da empresa, pelo codigo, ou a que veio escolhida. */
    /** $grupo, quando dado, e o que a conta escolhida precisa ser: categoria de receita numa despesa trocaria o sinal do mes. */
    private function conta(string $codigo, ?int $escolhida = null, ?string $grupo = null): int
    {
        if ($escolhida !== null) {
            $conta = ContaFinanceira::find($escolhida) ?? throw new Recusa('Conta não encontrada.');

            if ($grupo !== null && $conta->grupo !== $grupo) {
                throw new Recusa('A categoria escolhida não é de '.$grupo.'.');
            }

            return (int) $conta->id;
        }

        $conta = ContaFinanceira::firstWhere('codigo', $codigo);

        return $conta?->id ?? throw new Recusa("A conta {$codigo} não está cadastrada.");
    }

    /**
     * A conta pessoal do socio, criada na primeira vez que ele aparece.
     *
     * Criar sob demanda evita um cadastro de contas por socio que ninguem
     * lembraria de fazer, e o codigo carrega o id dele, entao duas pessoas
     * nunca compartilham a mesma conta.
     */
    private function doSocio(string $prefixo, ?Socio $socio): int
    {
        if ($socio === null) {
            throw new Recusa('Este lançamento precisa dizer de qual sócio se trata.');
        }

        $grupo = $prefixo === ContaFinanceira::APORTE ? 'patrimonio' : 'passivo';
        $rotulo = $prefixo === ContaFinanceira::APORTE ? 'Aportes de' : 'A devolver a';

        return ContaFinanceira::firstOrCreate(
            ['codigo' => $prefixo.':'.$socio->id],
            ['nome' => $rotulo.' '.$socio->nome, 'grupo' => $grupo, 'socio_id' => $socio->id],
        )->id;
    }
}
