<?php

namespace App\Models;

use App\Contracts\ContaAutenticavel;
use App\Support\Documento;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * A empresa contratante. Entra na plataforma para consultar e ver as proprias
 * faturas, nunca para gerir a Avalia.
 *
 * O modulo Cadastro acrescenta a ficha completa (proposta comercial) por
 * migration propria. Aqui fica so o que o Acesso precisa.
 */
class Cliente extends Authenticatable implements ContaAutenticavel
{
    use HasFactory, SoftDeletes;

    protected $table = 'clientes';

    /** Situacoes que permitem consultar. As demais suspendem o uso. */
    public const SITUACOES_ATIVAS = ['ativo'];

    protected $fillable = ['razao_social', 'cnpj', 'email', 'senha', 'situacao', 'plano_id', 'vendedor_id'];

    protected $hidden = ['senha', 'sessao_versao'];

    protected function casts(): array
    {
        return [
            'senha' => 'hashed',
            'sessao_versao' => 'integer',
            'ultimo_acesso_em' => 'datetime',
        ];
    }

    public function plano(): BelongsTo
    {
        return $this->belongsTo(Plano::class);
    }

    /** Vendedor dono da carteira. Vendedor nao mexe em cliente de outro. */
    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'vendedor_id');
    }

    public function consultas(): HasMany
    {
        return $this->hasMany(Consulta::class);
    }

    public function faturas(): HasMany
    {
        return $this->hasMany(Fatura::class);
    }

    public function cnpjRotulo(): string
    {
        return Documento::formatarCnpj($this->cnpj);
    }

    public function getAuthPassword(): string
    {
        return $this->senha;
    }

    /**
     * Inadimplente e bloqueado continuam entrando: precisam ver a fatura para
     * regularizar. O que eles nao podem e consultar, e isso e decidido em
     * podeConsultar(), nao aqui.
     */
    public function podeEntrar(): bool
    {
        return $this->situacao !== 'inativo' && $this->deleted_at === null;
    }

    public function podeConsultar(): bool
    {
        return in_array($this->situacao, self::SITUACOES_ATIVAS, true);
    }

    /** Motivo legivel da suspensao, para a tela explicar em vez de so negar. */
    public function motivoSuspensao(): ?string
    {
        return match ($this->situacao) {
            'inadimplente' => 'Consultas suspensas por fatura em aberto. Regularize para voltar a consultar.',
            'bloqueado' => 'Acesso as consultas bloqueado pela administracao. Fale com seu vendedor.',
            'inativo' => 'Contrato encerrado.',
            default => null,
        };
    }

    /**
     * Derruba todas as sessoes abertas desta conta.
     *
     * Apaga tambem o token de lembranca: sem isso, quem tem o cookie de
     * "manter conectado" voltaria a entrar depois de ter o acesso revogado, e
     * a revogacao seria so aparente.
     */
    public function revogaSessoes(): void
    {
        $this->increment('sessao_versao');
        $this->forceFill(['remember_token' => null])->saveQuietly();
    }
}
