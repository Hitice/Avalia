<?php

namespace App\Mail;

use App\Models\InteressadoCobranca;
use App\Support\Empresa;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Avisa o comercial de que chegou produtor novo no Avalia Gestor.
 *
 * Leva o necessario para o retorno: nome, e-mail e WhatsApp. O documento vai
 * mascarado, porque ninguem liga para um produtor precisando do CPF inteiro, e
 * caixa de e-mail e o lugar menos controlado por onde dado pessoal circula.
 */
class PreCadastroRecebido extends Mailable
{
    public function __construct(public InteressadoCobranca $interessado) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Novo pré-cadastro no '.Empresa::marcaCobranca());
    }

    public function content(): Content
    {
        return new Content(view: 'mail.pre-cadastro-cobranca');
    }
}
