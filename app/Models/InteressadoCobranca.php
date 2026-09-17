<?php

namespace App\Models;

use App\Support\Documento;
use Illuminate\Database\Eloquent\Model;

/**
 * Pre-cadastro de produtor no Avalia One.
 *
 * Documento e WhatsApp entram cifrados pelo cast: quem le o banco direto ve
 * texto cifrado, e so a aplicacao, com a chave, devolve o valor. Isso custa a
 * busca por documento, que nao existe nesta tela, e paga com backup que nao
 * entrega dado pessoal.
 */
class InteressadoCobranca extends Model
{
    protected $table = 'interessados_cobranca';

    protected $fillable = [
        'nome', 'documento', 'email', 'whatsapp', 'instagram',
        'vende', 'papel', 'prazo', 'faturamento_ano',
        'ticket_medio_cents', 'volume_mensal', 'origem', 'atendido_em',
    ];

    protected function casts(): array
    {
        return [
            'documento' => 'encrypted',
            'whatsapp' => 'encrypted',
            'ticket_medio_cents' => 'integer',
            'atendido_em' => 'datetime',
        ];
    }

    /** Ainda espera o primeiro contato. */
    public function scopeAguardando($consulta)
    {
        return $consulta->whereNull('atendido_em');
    }

    /** O documento como se escreve, CPF ou CNPJ conforme o tamanho. */
    public function documentoRotulo(): string
    {
        return Documento::formatar($this->documento);
    }
}
