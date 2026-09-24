<?php

namespace App\Mail;

use App\Models\Interessado;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Avisa o comercial de que chegou pedido de contato pelo site.
 *
 * O pedido ja esta gravado na fila da administracao quando este e-mail sai: o
 * aviso existe para o retorno comecar no mesmo dia, e nao para ser o registro.
 * Se o envio falhar, o pedido continua la, e e por isso que a falha nao
 * derruba a resposta ao visitante.
 *
 * O assunto carrega o assunto escolhido no formulario, porque e por ele que o
 * comercial decide quem responde antes mesmo de abrir.
 */
class ContatoRecebido extends Mailable
{
    public function __construct(public Interessado $interessado) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Contato pelo site: '.$this->interessado->assunto,
            replyTo: [$this->interessado->email],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.contato-site');
    }
}
