<?php

namespace App\Models;

use App\Crm\TemContato;
use Illuminate\Database\Eloquent\Model;

/**
 * Pedido de contato vindo da pagina publica.
 *
 * E dado pessoal de quem ainda nao e cliente: nome, telefone e e-mail entram
 * aqui para a conversa comecar do nosso lado, e nao pela URL de um servico de
 * terceiro. Nao guarda nada alem do que o formulario pede.
 */
class Interessado extends Model implements TemContato
{
    protected $table = 'interessados';

    protected $fillable = ['contato_id',
        'nome', 'empresa', 'telefone', 'email', 'funcionarios',
        'assunto', 'mensagem', 'origem', 'atendido_em',
    ];

    protected function casts(): array
    {
        return ['atendido_em' => 'datetime'];
    }

    /** Ainda espera o primeiro contato. */
    public function scopeAguardando($consulta)
    {
        return $consulta->whereNull('atendido_em');
    }

    public function dadosDeContato(): array
    {
        return ['nome' => $this->empresa ?: $this->nome, 'email' => $this->email, 'telefone' => $this->telefone];
    }

    public function papelNoCrm(): string
    {
        return 'interessado';
    }
}
