<?php

namespace App\Actions\Consumo;

use App\Models\Consulta;
use App\Support\Auditar;

/**
 * Apaga a resposta do bureau vencida, preservando o que explica a cobranca.
 *
 * A retencao e de 180 dias (PDD.md, secao 5). Passado o prazo, some o que e
 * dado pessoal de terceiro: o retorno do fornecedor e o documento consultado.
 *
 * O resto da linha fica para sempre. Preco, custo, competencia e servico sao o
 * que explica uma fatura antiga, e apagar a consulta inteira deixaria uma
 * cobranca sem composicao, o que e problema fiscal, nao ganho de privacidade.
 *
 * O expurgo e marcado em expurgada_em, e nao deduzido da ausencia de resposta:
 * consulta que falhou tambem nasce sem dado util, e as duas situacoes precisam
 * ser distinguiveis numa auditoria.
 */
class ExpurgarRespostas
{
    public function __invoke(?\DateTimeInterface $agora = null): int
    {
        $limite = ($agora ?? now())->format('Y-m-d');

        $vencidas = Consulta::query()
            ->whereNotNull('expurgar_em')
            ->whereNull('expurgada_em')
            ->whereDate('expurgar_em', '<=', $limite)
            ->get();

        foreach ($vencidas as $consulta) {
            $consulta->update([
                'resposta' => null,
                'documento' => null,
                'expurgada_em' => now(),
            ]);
        }

        if ($vencidas->isNotEmpty()) {
            Auditar::registrar('consulta.expurgada', null, [
                'quantidade' => $vencidas->count(),
                'ate' => $limite,
            ]);
        }

        return $vencidas->count();
    }
}
