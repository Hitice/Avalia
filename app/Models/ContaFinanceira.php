<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma conta do plano de contas.
 *
 * `grupo` decide de que lado a conta cresce, e e o que permite somar saldo sem
 * uma tabela de regras por conta:
 *
 *   ativo e despesa   crescem no DEBITO  (valor positivo)
 *   passivo, patrimonio e receita crescem no CREDITO (valor negativo)
 */
class ContaFinanceira extends Model
{
    protected $table = 'contas_financeiras';

    protected $fillable = ['codigo', 'nome', 'grupo', 'socio_id', 'ativa'];

    protected function casts(): array
    {
        return ['ativa' => 'boolean'];
    }

    public const CAIXA = 'caixa';

    public const RECEITA = 'receita';

    public const DESPESA = 'despesa';

    /** Prefixos das contas por socio, completadas com o id dele. */
    public const APORTE = 'aporte';

    public const EMPRESTIMO = 'emprestimo';

    public function socio(): BelongsTo
    {
        return $this->belongsTo(Socio::class);
    }

    /** O saldo que se mostra, ja com o sinal que a pessoa espera ler. */
    public function saldoCents(): int
    {
        $soma = (int) $this->partidas()->sum('valor_cents');

        return in_array($this->grupo, ['ativo', 'despesa'], true) ? $soma : -$soma;
    }

    public function partidas()
    {
        return $this->hasMany(PartidaFinanceira::class, 'conta_id');
    }
}
