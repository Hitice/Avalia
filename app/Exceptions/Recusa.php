<?php

namespace App\Exceptions;

/**
 * A regra de negocio disse nao.
 *
 * Tipo proprio, e nao `RuntimeException`: recusa nao e falha. Revender uma
 * plaquinha baixada e um pedido que o sistema entendeu e negou por um motivo
 * que a pessoa consegue ler, e devolver 500 para isso ensina o operador que a
 * tela quebra sozinha. Erro de verdade continua subindo como erro.
 *
 * A mensagem e escrita para quem esta na tela, e nao para o log: ela aparece
 * inteira no aviso da pagina.
 */
class Recusa extends \RuntimeException {}
