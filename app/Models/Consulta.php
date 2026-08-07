<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma consulta feita por uma empresa, com o preco congelado.
 *
 * `preco_cents` e `custo_cents` sao copias do catalogo no instante da consulta,
 * e nao referencias. Reajuste posterior nao mexe nesta linha, que e o que
 * permite reprecificar o catalogo sem reescrever o passado.
 */
class Consulta extends Model
{
    use HasFactory;

    protected $table = 'consultas';

    /** Situacoes possiveis de uma tentativa. */
    public const SUCESSO = 'sucesso';

    public const FALHA = 'falha';

    /** Retencao da resposta do bureau, em dias (PDD.md, secao 5). */
    public const DIAS_DE_RETENCAO = 180;

    /**
     * Janela em que a mesma consulta nao e refeita nem cobrada de novo.
     *
     * Curta de proposito: repetir no dia seguinte e uso legitimo, porque a
     * informacao do bureau muda. O que ela impede e o clique repetido, o
     * reenvio de formulario e a nova tentativa depois de queda de conexao.
     */
    public const SEGUNDOS_SEM_REPETIR = 120;

    /** Teto diario de consultas por empresa, enquanto nao houver um por contrato. */
    public const LIMITE_DIARIO = 500;

    /**
     * A finalidade declarada de toda consulta do portal.
     *
     * Definida pelo negocio: pesquisa de score de credito, amarrada ao aceite
     * dos termos. Digitar a finalidade a cada consulta virou formalidade
     * vazia; a declaracao vinculante e a do aceite, e cada consulta a
     * referencia.
     */
    public const FINALIDADE_PADRAO = 'Pesquisa de score de crédito, conforme os termos aceitos';

    protected $fillable = [
        'cliente_id', 'servico_id', 'operador_id', 'competencia', 'preco_cents', 'custo_cents',
        'documento', 'finalidade', 'solicitante', 'situacao',
        'referencia_externa', 'duracao_ms', 'resposta', 'expurgada_em', 'expurgar_em',
    ];

    protected function casts(): array
    {
        return [
            'cliente_id' => 'integer',
            'servico_id' => 'integer',
            'preco_cents' => 'integer',
            'custo_cents' => 'integer',
            'duracao_ms' => 'integer',
            'resposta' => 'array',
            'expurgada_em' => 'datetime',
            'expurgar_em' => 'date',
        ];
    }

    /** Competencia de uma data, no formato que o fechamento agrupa. */
    public static function competenciaDe(?\DateTimeInterface $momento = null): string
    {
        return ($momento ?? now())->format('Y-m');
    }

    public function deuCerto(): bool
    {
        return $this->situacao === self::SUCESSO;
    }

    /** Ja teve a resposta do bureau apagada pela retencao. */
    public function expurgada(): bool
    {
        return $this->expurgada_em !== null;
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function servico(): BelongsTo
    {
        return $this->belongsTo(Servico::class);
    }
}
