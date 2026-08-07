<?php

namespace App\Models;

use App\Contracts\ContaAutenticavel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A pessoa que consulta em nome da empresa cliente.
 *
 * Nao e um guard proprio: quem abre a sessao da area da empresa continua sendo
 * o Cliente, e o operador viaja na sessao como a identidade de quem esta
 * clicando. Assim nenhuma tela da area muda, e a consulta e o aceite ganham
 * dono com nome.
 */
class Operador extends Model implements ContaAutenticavel
{
    use HasFactory, SoftDeletes;

    protected $table = 'operadores';

    protected $fillable = ['cliente_id', 'nome', 'email', 'senha', 'ativo'];

    protected $hidden = ['senha', 'sessao_versao'];

    protected function casts(): array
    {
        return [
            'senha' => 'hashed',
            'ativo' => 'boolean',
            'sessao_versao' => 'integer',
            'ultimo_acesso_em' => 'datetime',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /** O operador da sessao atual, ou null quando quem entrou e a conta master. */
    public static function daSessao(): ?self
    {
        $id = session('operador_id');

        return $id ? static::find($id) : null;
    }

    /** Operador so entra se ele e a empresa dele puderem. */
    public function podeEntrar(): bool
    {
        return $this->ativo && $this->deleted_at === null && ($this->cliente?->podeEntrar() ?? false);
    }

    public function motivoSuspensao(): ?string
    {
        if (! $this->ativo || $this->deleted_at !== null) {
            return 'Este acesso foi desativado. Fale com o responsável pela sua empresa.';
        }

        return $this->cliente?->motivoSuspensao();
    }

    public function revogaSessoes(): void
    {
        $this->increment('sessao_versao');
    }

    /** Ciencia dos termos obrigatorios, declarada por ESTE operador. */
    public function aceitouObrigatorios(): bool
    {
        $obrigatorios = DocumentoLegal::query()->where('ativo', true)->where('exige_aceite', true)->pluck('id');

        return $obrigatorios->isEmpty()
            || AceiteDocumento::where('operador_id', $this->id)
                ->whereIn('documento_id', $obrigatorios)
                ->count() === $obrigatorios->count();
    }
}
