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
    /**
     * `redefinicao` troca o texto de boas-vindas pelo de troca de senha.
     *
     * O mecanismo e o mesmo (link assinado de 48h que morre no uso) e continua
     * havendo um so, mas quem clicou em "esqueci minha senha" nao pode receber
     * "seja bem-vindo, seu acesso foi criado": a pessoa ja tem conta ha meses,
     * e o texto errado faz parecer que alguem criou um acesso no nome dela.
     */
    public function __construct(
        public string $nome,
        public string $link,
        public bool $ehEmpresa = false,
        public ?string $operadorDe = null,
        public bool $redefinicao = false,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->redefinicao
            ? 'Redefinição de senha'
            : 'Seu acesso à Avalia One');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.convite');
    }
}
