<?php

namespace App\Mail;

use App\Models\Fatura;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Confirma para a empresa que o pagamento entrou.
 *
 * Quando o pagamento tambem reativa as consultas, o mesmo e-mail avisa: sao um
 * evento so para o cliente, e dois e-mails simultaneos sobre a mesma fatura
 * parecem erro.
 */
class ReciboDeLiquidacao extends Mailable
{
    public function __construct(
        public Fatura $fatura,
        public bool $acessoLiberado = false,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Pagamento confirmado · '.$this->fatura->competenciaRotulo());
    }

    public function content(): Content
    {
        return new Content(view: 'mail.recibo', with: [
            'nome' => $this->fatura->cliente->tratamento(),
            'acessoLiberado' => $this->acessoLiberado,
        ]);
    }
}
