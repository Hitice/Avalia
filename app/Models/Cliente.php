<?php

namespace App\Models;

use App\Contracts\ContaAutenticavel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * A empresa contratante. Entra na plataforma para consultar e ver as proprias
 * faturas — nunca para gerir a Avalia.
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

    protected $fillable = ['razao_social', 'email', 'senha', 'situacao'];

    protected $hidden = ['senha', 'sessao_versao'];

    protected function casts(): array
    {
        return [
            'senha' => 'hashed',
            'sessao_versao' => 'integer',
            'ultimo_acesso_em' => 'datetime',
        ];
    }

    public function getAuthPassword(): string
    {
        return $this->senha;
    }

    /**
     * Inadimplente e bloqueado continuam entrando: precisam ver a fatura para
     * regularizar. O que eles nao podem e consultar — isso e decidido em
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

    public function revogaSessoes(): void
    {
        $this->increment('sessao_versao');
    }
}
