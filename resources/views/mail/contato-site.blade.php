@extends('mail.base')

@section('titulo', 'Novo contato pelo site')

@section('conteudo')
    <p style="margin:0 0 12px 0;">Chegou um pedido de contato pela página da {{ \App\Support\Empresa::marca() }}.</p>

    <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;font-size:14px;color:#344054;">
        <tr>
            <td style="padding:4px 0;color:#667085;width:130px;">Nome</td>
            <td style="padding:4px 0;">{{ $interessado->nome }}</td>
        </tr>
        <tr>
            <td style="padding:4px 0;color:#667085;">Empresa</td>
            <td style="padding:4px 0;">{{ $interessado->empresa }}</td>
        </tr>
        <tr>
            <td style="padding:4px 0;color:#667085;">E-mail</td>
            <td style="padding:4px 0;">{{ $interessado->email }}</td>
        </tr>
        <tr>
            <td style="padding:4px 0;color:#667085;">WhatsApp</td>
            <td style="padding:4px 0;">{{ $interessado->telefone }}</td>
        </tr>
        <tr>
            <td style="padding:4px 0;color:#667085;">Assunto</td>
            <td style="padding:4px 0;">{{ $interessado->assunto }}</td>
        </tr>
    </table>

    <p style="margin:16px 0 4px 0;color:#667085;font-size:14px;">Mensagem</p>
    <p style="margin:0;font-size:14px;color:#344054;white-space:pre-line;">{{ $interessado->mensagem }}</p>
@endsection
