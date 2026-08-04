<?php

namespace App\Models;

use App\Contracts\ContaAutenticavel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Quem opera a Avalia: administrador ou vendedor.
 *
 * Nao confundir com Cliente: sao guards distintos justamente para que um
 * cliente nunca caia numa rota de gestao por descuido de roteamento.
 */
class Staff extends Authenticatable implements ContaAutenticavel
{
    use HasFactory, SoftDeletes;

    protected $table = 'staff';

    protected $fillable = [
        'nome', 'email', 'senha', 'papel', 'super', 'ativo',
        'cpf', 'pix_chave', 'banco', 'agencia', 'conta',
    ];

    protected $hidden = ['senha', 'sessao_versao'];

    protected function casts(): array
    {
        return [
            'senha' => 'hashed',
            'super' => 'boolean',
            'ativo' => 'boolean',
            'sessao_versao' => 'integer',
            'ultimo_acesso_em' => 'datetime',
        ];
    }

    /** O Laravel procura `password`; aqui a coluna chama senha. */
    public function getAuthPassword(): string
    {
        return $this->senha;
    }

    public function ehAdmin(): bool
    {
        return $this->papel === 'admin';
    }

    public function ehVendedor(): bool
    {
        return $this->papel === 'vendedor';
    }

    /** Superusuario passa por cima de qualquer policy. */
    public function ehSuper(): bool
    {
        return (bool) $this->super;
    }

    /** Regra unica de quem pode entrar. Vale no login e a cada requisicao. */
    public function podeEntrar(): bool
    {
        return $this->ativo && $this->deleted_at === null;
    }

    public function motivoSuspensao(): ?string
    {
        if ($this->deleted_at !== null) {
            return 'Esta conta foi removida.';
        }

        return $this->ativo ? null : 'Esta conta esta desativada. Fale com a administracao.';
    }

    /**
     * Derruba todas as sessoes abertas desta conta.
     * Usado ao desativar, trocar papel ou redefinir senha.
     */
    public function revogaSessoes(): void
    {
        $this->increment('sessao_versao');
    }
}
