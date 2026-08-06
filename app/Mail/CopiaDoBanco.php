<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * A copia diaria do banco, anexada e comprimida.
 *
 * E o destino externo do backup: a copia em disco protege de engano humano,
 * mas mora no mesmo disco que ela protege. A caixa de e-mail do dominio e um
 * cofre fora da maquina que ja existe e ja esta pago; enquanto o banco couber
 * num anexo, e o destino mais simples possivel.
 *
 * O anexo tem dado de cliente e hash de senha: a caixa de destino e nossa e
 * de mais ninguem, e este e-mail nunca ganha copia nem encaminhamento
 * automatico.
 */
class CopiaDoBanco extends Mailable
{
    public function __construct(
        public string $nomeDoArquivo,
        public string $bytesComprimidos,
        public int $registros,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Cópia do banco · '.now()->format('d/m/Y'));
    }

    public function content(): Content
    {
        return new Content(htmlString: sprintf(
            '<p>Cópia diária do banco da Avalia: %d registros, gerada em %s.</p>'
            .'<p>Restauração: descompactar, e no servidor rodar <code>php artisan avalia:importar ARQUIVO</code> '
            .'com as tabelas já criadas por <code>php artisan migrate</code>.</p>',
            $this->registros,
            now()->format('d/m/Y H:i'),
        ));
    }

    /** @return list<Attachment> */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->bytesComprimidos, $this->nomeDoArquivo.'.gz')
                ->withMime('application/gzip'),
        ];
    }
}
