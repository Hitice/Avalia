<?php

namespace App\Actions\Etiquetas;

use App\Enums\SituacaoEtiqueta;
use App\Exceptions\Recusa;
use App\Models\Etiqueta;
use App\Support\Auditar;

/**
 * Apaga um codigo que nunca chegou a existir para ninguem.
 *
 * A casa nao apaga o que tem historia, e esta acao nao e excecao a isso: ela
 * so aceita o codigo que nasceu, ficou em branco e nunca apontou para lugar
 * nenhum nem foi lido por ninguem. E o caso real de gerar cem por engano, ou
 * de gerar de teste: nao ha placa no balcao de ninguem, nao ha cliente
 * esperando e nao ha nada para explicar depois.
 *
 * Codigo que ja apontou para algum lugar nao volta atras por aqui, mesmo que
 * pareca esquecido: alguem pode ter a placa dele na gaveta, e apagar o
 * registro devolveria o codigo ao sorteio. Para esse existe `BaixarEtiqueta`,
 * que tira de circulacao e mantem o codigo reservado para sempre.
 */
class ExcluirEtiqueta
{
    public function __invoke(Etiqueta $etiqueta): void
    {
        if ($etiqueta->situacao !== SituacaoEtiqueta::EmBranco) {
            throw new Recusa('Este código já foi para a rua. Use "Encerrar": assim ele sai do ar e o número continua reservado, em vez de voltar para o sorteio.');
        }

        if ($etiqueta->destinos()->exists() || $etiqueta->total_acessos > 0) {
            throw new Recusa('Este código já apontou para algum lugar, ou já foi lido. Use "Encerrar" em vez de apagar.');
        }

        // O registro na auditoria vem ANTES: depois de apagar nao ha entidade
        // para o rastro apontar, e o que sobraria seria uma linha orfa.
        Auditar::registrar('etiquetas.excluida', $etiqueta, ['codigo' => $etiqueta->codigo]);

        $etiqueta->delete();
    }
}
