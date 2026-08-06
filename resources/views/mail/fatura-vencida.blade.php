@extends('mail.base')

@section('titulo', 'Sua fatura venceu')

@section('conteudo')
    <p style="margin:0 0 12px 0;">Olá, {{ $nome }}.</p>
    <p style="margin:0 0 12px 0;">
        A fatura de <strong>{{ $fatura->competenciaRotulo() }}</strong>, no valor de
        <strong>{{ $fatura->totalRotulo() }}</strong>, venceu em
        {{ $fatura->vencimento()->format('d/m/Y') }} e segue em aberto.
    </p>
    <p style="margin:0 0 12px 0;">
        As consultas da sua empresa continuam funcionando normalmente. Para que
        siga assim, regularize o pagamento até
        <strong>{{ $limite->format('d/m/Y') }}</strong>.
        Se o pagamento já foi feito, desconsidere este aviso.
    </p>

    @include('mail.botao', ['url' => route('empresa.faturas'), 'rotulo' => 'Ver minha fatura'])
@endsection
