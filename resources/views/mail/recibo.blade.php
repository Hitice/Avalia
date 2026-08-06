@extends('mail.base')

@section('titulo', 'Pagamento confirmado')

@section('conteudo')
    <p style="margin:0 0 12px 0;">Olá, {{ $nome }}.</p>
    <p style="margin:0 0 12px 0;">
        Recebemos o pagamento da fatura de <strong>{{ $fatura->competenciaRotulo() }}</strong>,
        no valor de <strong>{{ $fatura->totalRotulo() }}</strong>. Obrigado.
    </p>

    @if ($acessoLiberado)
        <p style="margin:0 0 12px 0;">
            As consultas da sua empresa, que estavam suspensas por atraso, já foram liberadas.
        </p>
    @endif

    @include('mail.botao', ['url' => route('empresa.faturas'), 'rotulo' => 'Ver minhas faturas'])
@endsection
