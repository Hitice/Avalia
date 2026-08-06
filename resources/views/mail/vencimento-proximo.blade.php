@extends('mail.base')

@section('titulo', 'Sua fatura vence em breve')

@section('conteudo')
    <p style="margin:0 0 12px 0;">Olá, {{ $nome }}.</p>
    <p style="margin:0 0 12px 0;">
        Um lembrete rápido: a fatura de <strong>{{ $fatura->competenciaRotulo() }}</strong>,
        no valor de <strong>{{ $fatura->totalRotulo() }}</strong>, vence em
        <strong>{{ $fatura->vencimento()->format('d/m/Y') }}</strong>.
    </p>
    <p style="margin:0 0 12px 0;">
        Se o pagamento já foi feito, desconsidere este aviso.
    </p>

    @include('mail.botao', ['url' => route('empresa.faturas'), 'rotulo' => 'Ver minha fatura'])
@endsection
