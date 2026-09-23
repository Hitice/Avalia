@extends('mail.base')

@section('titulo', 'Novo pré-cadastro no '.\App\Support\Empresa::marcaCobranca())

@section('conteudo')
    <p style="margin:0 0 12px 0;">Chegou um pedido novo pela página do {{ \App\Support\Empresa::marcaCobranca() }}.</p>

    <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;font-size:14px;color:#344054;">
        <tr>
            <td style="padding:4px 0;color:#667085;width:130px;">Nome</td>
            <td style="padding:4px 0;">{{ $interessado->nome }}</td>
        </tr>
        <tr>
            <td style="padding:4px 0;color:#667085;">Instagram</td>
            <td style="padding:4px 0;">{{ $interessado->instagram }}</td>
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
            <td style="padding:4px 0;color:#667085;">Vende</td>
            <td style="padding:4px 0;">{{ $interessado->vende }}</td>
        </tr>
        <tr>
            <td style="padding:4px 0;color:#667085;">Papel</td>
            <td style="padding:4px 0;">{{ $interessado->papel }}</td>
        </tr>
        <tr>
            <td style="padding:4px 0;color:#667085;">Quer começar</td>
            <td style="padding:4px 0;">{{ $interessado->prazo ?: 'não informou' }}</td>
        </tr>
        <tr>
            <td style="padding:4px 0;color:#667085;">Faturou no ano</td>
            <td style="padding:4px 0;">{{ $interessado->faturamento_ano ?: 'não informou' }}</td>
        </tr>
    </table>
@endsection

@section('rodape')
    <p style="margin:0 0 8px 0;">
        Quem pediu contato ainda não tem conta: o documento só é pedido no cadastro,
        depois que a conversa acontece.
    </p>
@endsection
