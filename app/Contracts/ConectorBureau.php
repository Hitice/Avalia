<?php

namespace App\Contracts;

use App\Models\Servico;
use App\Support\RespostaConsulta;

/**
 * O contrato de integracao com um fornecedor de consultas.
 *
 * Existe para que o resto do sistema nao saiba com quem esta falando. Trocar de
 * fornecedor, ou ter um por familia de servico, passa a ser trocar a
 * implementacao amarrada no container, e nada mais.
 *
 * Quem implementa promete tres coisas:
 *
 *   nunca lancar excecao por falha do fornecedor. Timeout, recusa e resposta
 *   malformada sao respostas possiveis do mundo real, e viram
 *   RespostaConsulta::falha para que quem chamou decida o que fazer;
 *   nunca devolver dado de outro documento que nao o perguntado;
 *   sempre dizer quanto tempo levou, porque e o unico numero que denuncia um
 *   fornecedor degradando antes de o cliente reclamar.
 */
interface ConectorBureau
{
    /**
     * @param  string  $documento  CPF ou CNPJ, so digitos
     * @param  string  $finalidade  motivo declarado, guardado junto da consulta
     */
    public function consultar(Servico $servico, string $documento, string $finalidade): RespostaConsulta;

    /** Nome curto do fornecedor, para trilha e diagnostico. */
    public function nome(): string;
}
