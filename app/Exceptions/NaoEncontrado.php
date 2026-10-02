<?php

namespace App\Exceptions;

/**
 * A busca correu bem e nao achou nada. E Recusa, mas a tela trata diferente:
 * em vez de so mostrar o erro, pede mais um dado para tentar de novo.
 */
class NaoEncontrado extends Recusa {}
