@extends('mail.base')

@section('titulo', 'Sua fatura está disponível')

@section('conteudo')
    <p style="margin:0 0 12px 0;">Olá, {{ $nome }}.</p>
    <p style="margin:0 0 12px 0;">
        A fatura da sua empresa referente a <strong>{{ $fatura->competenciaRotulo() }}</strong>
        já está disponível no portal.
    </p>

    {{-- A tabelinha diz o essencial: quanto e ate quando. A composicao
         completa fica no portal, onde a pessoa ja esta autenticada. --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
           style="margin:6px 0 6px 0;background-color:#f9fafb;border-radius:10px;">
        <tr>
            <td style="padding:14px 18px;color:#667085;font-size:13px;">Valor</td>
            <td align="right" style="padding:14px 18px;color:#101828;font-size:15px;font-weight:bold;">{{ $fatura->totalRotulo() }}</td>
        </tr>
        <tr>
            <td style="padding:0 18px 14px 18px;color:#667085;font-size:13px;">Vencimento</td>
            <td align="right" style="padding:0 18px 14px 18px;color:#101828;font-size:14px;">{{ $fatura->vencimento()->format('d/m/Y') }}</td>
        </tr>
    </table>

    @include('mail.botao', ['url' => route('empresa.faturas'), 'rotulo' => 'Ver minha fatura'])
@endsection
