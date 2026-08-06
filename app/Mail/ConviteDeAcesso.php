<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * O e-mail de boas-vindas com o link para definir a senha.
 *
 * Nao carrega senha, nem provisoria: so o link assinado, que vale 48 horas e
 * morre no primeiro uso. O texto diz de onde o convite veio e manda ignorar se
 * a pessoa nao o esperava, porque e-mail de acesso inesperado e o primeiro
 * sinal de cadastro indevido.
 */
class ConviteDeAcesso extends Mailable
{
    public function __construct(
        public string $nome,
        public string $link,
        public bool $ehEmpresa = false,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Seu acesso à Avalia');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.convite');
    }
}
