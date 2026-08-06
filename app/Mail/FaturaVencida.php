<?php

namespace App\Mail;

use App\Models\Fatura;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * O aviso entre o vencimento e o bloqueio.
 *
 * A regra da uma janela de tolerancia antes de suspender as consultas, e a
 * janela so cumpre o papel se o cliente souber dela: diz que venceu, que nada
 * foi cortado e ate quando regularizar.
 */
class FaturaVencida extends Mailable
{
    public function __construct(public Fatura $fatura) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Sua fatura de '.$this->fatura->competenciaRotulo().' venceu');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.fatura-vencida', with: [
            'nome' => $this->fatura->cliente->tratamento(),
            'limite' => $this->fatura->vencimento()->addDays(Fatura::DIAS_ATE_BLOQUEIO),
        ]);
    }
}
