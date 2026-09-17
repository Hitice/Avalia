@extends('mail.base')

@section('titulo', 'Novo pré-cadastro no Avalia 360')

@section('conteudo')
    <p style="margin:0 0 12px 0;">Chegou um pedido novo pela página do Avalia 360.</p>

    <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;font-size:14px;color:#344054;">
        <tr>
            <td style="padding:4px 0;color:#667085;width:130px;">Nome</td>
            <td style="padding:4px 0;">{{ $interessado->nome }}</td>
        </tr>
        <tr>
            <td style="padding:4px 0;color:#667085;">Documento</td>
            <td style="padding:4px 0;">{{ App\Support\Documento::mascarar($interessado->documento) }}</td>
        </tr>
        <tr>
            <td style="padding:4px 0;color:#667085;">E-mail</td>
            <td style="padding:4px 0;">{{ $interessado->email }}</td>
        </tr>
        <tr>
            <td style="padding:4px 0;color:#667085;">WhatsApp</td>
            <td style="padding:4px 0;">{{ $interessado->whatsapp }}</td>
        </tr>
        <tr>
            <td style="padding:4px 0;color:#667085;">Ticket médio</td>
            <td style="padding:4px 0;">{{ App\Support\Dinheiro::brl($interessado->ticket_medio_cents) }}</td>
        </tr>
        <tr>
            <td style="padding:4px 0;color:#667085;">Volume mensal</td>
            <td style="padding:4px 0;">{{ $interessado->volume_mensal }}</td>
        </tr>
    </table>
@endsection

@section('rodape')
    <p style="margin:0 0 8px 0;">
        O documento vai mascarado de propósito. O número completo fica no cadastro,
        acessível a quem opera a plataforma.
    </p>
@endsection
