<?php

namespace App\Models;

use App\Contracts\ContaAutenticavel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'comissao_pct', 'pode_financeiro', 'cpf', 'pix_chave', 'banco', 'agencia', 'conta',
    ];

    protected $hidden = ['senha', 'sessao_versao'];

    protected function casts(): array
    {
        return [
            'senha' => 'hashed',
            'super' => 'boolean',
            'ativo' => 'boolean',
            'pode_financeiro' => 'boolean',
            'comissao_pct' => 'integer',
            'sessao_versao' => 'integer',
            'ultimo_acesso_em' => 'datetime',
        ];
    }

    /** O Laravel procura `password`; aqui a coluna chama senha. */
    public function getAuthPassword(): string
    {
        return $this->senha;
    }

    /** Carteira do vendedor. */
    public function clientes(): HasMany
    {
        return $this->hasMany(Cliente::class, 'vendedor_id');
    }

    /**
     * Os leads que a administracao compartilhou com esta pessoa.
     *
     * Nao e carteira: lead nao tem contrato, e o mesmo lead pode estar com mais
     * de um vendedor. Quem distribui e sempre a administracao.
     */
    public function leads(): BelongsToMany
    {
        return $this->belongsToMany(Lead::class, 'lead_staff', 'staff_id', 'lead_id')
            ->withPivot(['compartilhado_em', 'compartilhado_por']);
    }

    /**
     * Pode confirmar pagamento e fechar competencia.
     *
     * Superusuario passa por cima, como em qualquer outra permissao. Vendedor
     * nunca pode, mesmo com a marca ligada por engano no cadastro: a permissao
     * so faz sentido dentro da administracao.
     */
    public function podeFinanceiro(): bool
    {
        return $this->ehSuper() || ($this->ehAdmin() && (bool) $this->pode_financeiro);
    }

    public function ehAdmin(): bool
    {
        return $this->papel === 'admin';
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
     *
     * Apaga tambem o token de lembranca: sem isso, quem tem o cookie de
     * "manter conectado" voltaria a entrar depois de ter o acesso revogado, e
     * a revogacao seria so aparente.
     */
    /**
     * Ciencia dos termos do vendedor. So o papel vendedor tem gate: quem
     * administra e quem publica os proprios documentos.
     */
    public function aceitouObrigatorios(): bool
    {
        if ($this->papel !== 'vendedor') {
            return true;
        }

        $obrigatorios = DocumentoLegal::query()->para('vendedor')->where('exige_aceite', true)->pluck('id');

        return $obrigatorios->isEmpty()
            || AceiteDocumento::where('staff_id', $this->id)
                ->whereIn('documento_id', $obrigatorios)
                ->count() === $obrigatorios->count();
    }

    public function revogaSessoes(): void
    {
        $this->increment('sessao_versao');
        $this->forceFill(['remember_token' => null])->saveQuietly();
    }
}
