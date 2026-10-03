<?php

namespace App\Crm;

/**
 * O que uma tabela de frente precisa dizer para virar contato. O nucleo so
 * conhece esta interface; quem conhece plano, placa e subconta e a frente.
 */
interface TemContato
{
    /** @return array{nome: string, documento?: ?string, email?: ?string, whatsapp?: ?string, telefone?: ?string, cidade?: ?string, uf?: ?string} */
    public function dadosDeContato(): array;

    /** cliente, negocio, lead, interessado ou produtor. */
    public function papelNoCrm(): string;
}
