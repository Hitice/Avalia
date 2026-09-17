@extends('mail.base')

@section('titulo', $redefinicao ? 'Redefinição de senha' : 'Seu acesso à Avalia One')

@section('conteudo')
    @if ($redefinicao)
        {{-- Quem pediu troca de senha ja tem conta. Receber "bem-vindo, seu
             acesso foi criado" faz parecer que alguem criou um acesso no nome
             da pessoa, e o primeiro reflexo dela e ligar achando que foi
             invadida. --}}
        <p style="margin:0 0 12px 0;">Olá, {{ $nome }}.</p>
        <p style="margin:0 0 12px 0;">
            Recebemos um pedido para redefinir a senha da sua conta. Para escolher uma nova:
        </p>
    @elseif ($operadorDe)
        <p style="margin:0 0 12px 0;">Olá, {{ $nome }}, seja bem-vindo à Avalia One.</p>
        <p style="margin:0 0 12px 0;">
            Seu acesso às consultas de {{ $operadorDe }} foi criado. Para começar, defina a sua senha:
        </p>
    @elseif ($ehEmpresa)
        <p style="margin:0 0 12px 0;">Olá, {{ $nome }}, seja bem-vindo à Avalia One.</p>
        <p style="margin:0 0 12px 0;">
            O acesso da sua empresa foi criado. Para começar a consultar, defina a sua senha:
        </p>
    @else
        <p style="margin:0 0 12px 0;">Olá, {{ $nome }}, seja bem-vindo ao time de vendas.</p>
        <p style="margin:0 0 12px 0;">
            Seu acesso à Avalia One foi criado. Para entrar, defina a sua senha:
        </p>
    @endif

    @include('mail.botao', ['url' => $link, 'rotulo' => $redefinicao ? 'Escolher nova senha' : 'Definir minha senha'])
@endsection

@section('rodape')
    <p style="margin:0 0 8px 0;">
        O link expira em {{ App\Support\Convite::HORAS_DE_VALIDADE }} horas e vale uma única vez.
        @if ($redefinicao)
            Se não foi você quem pediu, ignore este e-mail: sua senha atual continua valendo.
        @else
            Se você não esperava recebê-lo, ignore.
        @endif
    </p>
@endsection
