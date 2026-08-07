@extends('mail.base')

@section('titulo', 'Seu acesso à Avalia')

@section('conteudo')
    @if ($operadorDe)
        <p style="margin:0 0 12px 0;">Olá, {{ $nome }}, seja bem-vindo à Avalia.</p>
        <p style="margin:0 0 12px 0;">
            Seu acesso às consultas de {{ $operadorDe }} foi criado. Para começar, defina a sua senha:
        </p>
    @elseif ($ehEmpresa)
        <p style="margin:0 0 12px 0;">Olá, {{ $nome }}, seja bem-vindo à Avalia.</p>
        <p style="margin:0 0 12px 0;">
            O acesso da sua empresa foi criado. Para começar a consultar, defina a sua senha:
        </p>
    @else
        <p style="margin:0 0 12px 0;">Olá, {{ $nome }}, seja bem-vindo ao time de vendas.</p>
        <p style="margin:0 0 12px 0;">
            Seu acesso ao Avaliaone foi criado. Para entrar, defina a sua senha:
        </p>
    @endif

    @include('mail.botao', ['url' => $link, 'rotulo' => 'Definir minha senha'])
@endsection

@section('rodape')
    <p style="margin:0 0 8px 0;">
        O link expira em {{ App\Support\Convite::HORAS_DE_VALIDADE }} horas e vale uma única vez.
        Se você não esperava recebê-lo, ignore.
    </p>
@endsection
