<?php

namespace App\Mail;

use App\Models\Fatura;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Avisa a empresa que as consultas foram suspensas por fatura em atraso.
 *
 * A suspensao ja aconteceu quando este e-mail sai. Suspender sem avisar
 * transforma o proximo erro de consulta do cliente num chamado de suporte; o
 * e-mail diz o motivo e que a liberacao e automatica com o pagamento.
 */
class ConsultasBloqueadas extends Mailable
{
    public function __construct(public Fatura $fatura) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Consultas suspensas por fatura em atraso');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.consultas-bloqueadas', with: [
            'nome' => $this->fatura->cliente->tratamento(),
        ]);
    }
}
