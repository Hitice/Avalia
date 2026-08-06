<?php

namespace App\Mail;

use App\Models\Fatura;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Avisa a empresa que a competencia fechou e a fatura esta no portal.
 *
 * Vai no fechamento, seja ele da rotina da madrugada ou de um clique na tela:
 * fatura que so aparece quando alguem entra no portal e fatura que vence sem
 * ninguem saber que existia.
 */
class FaturaEmitida extends Mailable
{
    public function __construct(public Fatura $fatura) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Sua fatura de '.$this->fatura->competenciaRotulo().' está disponível');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.fatura-emitida', with: [
            'nome' => $this->fatura->cliente->tratamento(),
        ]);
    }
}
