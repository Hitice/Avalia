<?php

namespace App\Exceptions;

use App\Models\VersaoCatalogo;
use RuntimeException;

/**
 * Tentativa de alterar preco de uma versao que ja saiu do rascunho.
 *
 * Nao e validacao de formulario: e a ultima linha de defesa contra reescrever
 * o passado. Se esta excecao chega a producao, algum fluxo esta tentando mudar
 * numero de contrato assinado — o certo e criar uma versao nova.
 */
class CatalogoCongelado extends RuntimeException
{
    public static function para(VersaoCatalogo $versao): self
    {
        return new self(
            "A versao de catalogo '{$versao->rotulo}' esta {$versao->situacao} e nao aceita "
            .'alteracao de preco. Duplique-a para criar um rascunho.'
        );
    }
}
