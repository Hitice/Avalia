<?php

namespace App\Contracts;

/**
 * O que o modulo Acesso exige de qualquer conta que entre na plataforma,
 * seja ela staff ou empresa.
 *
 * Existe porque o LoginController trata as duas naturezas pelo mesmo caminho.
 * Sem o contrato, um metodo presente so em um dos models passa despercebido
 * ate alguem com a conta suspensa tentar entrar, que foi exatamente o que
 * aconteceu na primeira rodada de testes.
 */
interface ContaAutenticavel
{
    /** Regra unica de quem pode abrir sessao. Vale no login e a cada requisicao. */
    public function podeEntrar(): bool;

    /** Explicacao legivel quando nao pode entrar, ou null quando pode. */
    public function motivoSuspensao(): ?string;

    /** Derruba na hora todas as sessoes abertas desta conta. */
    public function revogaSessoes(): void;
}
