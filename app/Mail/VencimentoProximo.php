<?php

namespace App\Mail;

use App\Models\Fatura;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Lembrete de vencimento, dias antes do dia 10.
 *
 * O tom e de lembrete, nao de cobranca: a fatura ainda nao venceu e o cliente
 * nao fez nada de errado. O objetivo e evitar o atraso de quem so esqueceu,
 * que e a maioria.
 */
class VencimentoProximo extends Mailable
{
    public function __construct(public Fatura $fatura) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Sua fatura vence em '.$this->fatura->vencimento()->format('d/m'));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.vencimento-proximo', with: [
            'nome' => $this->fatura->cliente->tratamento(),
        ]);
    }
}
