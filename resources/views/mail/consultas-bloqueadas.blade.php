@extends('mail.base')

@section('titulo', 'Consultas suspensas por fatura em atraso')

@section('conteudo')
    <p style="margin:0 0 12px 0;">Olá, {{ $nome }}.</p>
    <p style="margin:0 0 12px 0;">
        A fatura de <strong>{{ $fatura->competenciaRotulo() }}</strong>, no valor de
        <strong>{{ $fatura->totalRotulo() }}</strong>, venceu em
        {{ $fatura->vencimento()->format('d/m/Y') }} e segue em aberto. Por isso as
        consultas da sua empresa foram suspensas.
    </p>
    <p style="margin:0 0 12px 0;">
        Assim que o pagamento for confirmado, a liberação é automática.
        Se o pagamento já foi feito, ele deve ser confirmado em breve.
    </p>

    @include('mail.botao', ['url' => route('empresa.faturas'), 'rotulo' => 'Ver minha fatura'])
@endsection
