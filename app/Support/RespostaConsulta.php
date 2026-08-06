<?php

namespace App\Support;

/**
 * O que voltou de uma consulta ao fornecedor.
 *
 * Sucesso e falha sao os dois resultados normais, e nao um deles mais uma
 * excecao: fornecedor fora do ar e rotina, nao acidente. Quem chama trata os
 * dois pelo mesmo caminho e decide o que cobrar.
 */
final class RespostaConsulta
{
    /** @param array<string, mixed> $dados */
    private function __construct(
        public readonly bool $sucesso,
        public readonly array $dados,
        public readonly ?string $erro,
        public readonly ?string $referenciaExterna,
        public readonly int $duracaoMs,
    ) {}

    /** @param array<string, mixed> $dados */
    public static function sucesso(array $dados, ?string $referenciaExterna = null, int $duracaoMs = 0): self
    {
        return new self(true, $dados, null, $referenciaExterna, max(0, $duracaoMs));
    }

    /**
     * Falha carrega o motivo em linguagem de quem vai ler na tela.
     *
     * Nao carrega dados: resposta parcial de fornecedor e a origem classica de
     * cobrar por uma consulta que nao respondeu nada.
     */
    public static function falha(string $erro, ?string $referenciaExterna = null, int $duracaoMs = 0): self
    {
        return new self(false, [], $erro, $referenciaExterna, max(0, $duracaoMs));
    }
}
