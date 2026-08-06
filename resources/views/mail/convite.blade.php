<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Seu acesso à Avalia</title>
</head>
{{-- Estilo inline e estrutura simples de proposito: cliente de e-mail nao
     carrega CSS externo e cada um renderiza a seu modo. O gauge vai como PNG
     hospedado porque o Gmail nao renderiza SVG. --}}
<body style="margin:0;padding:0;background-color:#f2f4f7;font-family:Arial,Helvetica,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f2f4f7;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                       style="max-width:520px;background-color:#ffffff;border-radius:16px;border:1px solid #e4e7ec;">
                    <tr>
                        <td style="padding:30px 36px 6px 36px;">
                            <img src="https://avaliaone.com.br/images/email-gauge.png" width="28" height="28" alt=""
                                 style="vertical-align:middle;margin-right:8px;">
                            <span style="font-size:22px;font-weight:bold;color:#3641f5;vertical-align:middle;">Avalia<span style="color:#98a2b3;">one</span></span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:14px 36px 0 36px;color:#344054;font-size:15px;line-height:1.6;">
                            @if ($ehEmpresa)
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
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:12px 36px 6px 36px;">
                            <a href="{{ $link }}"
                               style="display:inline-block;background-color:#465fff;color:#ffffff;text-decoration:none;font-size:14px;font-weight:bold;padding:10px 22px;border-radius:8px;">
                                Definir minha senha
                            </a>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:14px 36px 26px 36px;color:#667085;font-size:13px;line-height:1.6;">
                            <p style="margin:0 0 8px 0;">
                                O link expira em {{ App\Support\Convite::HORAS_DE_VALIDADE }} horas e vale uma única vez.
                            </p>
                            <p style="margin:0;">
                                Este é um envio automático, não responda a este e-mail.
                                Se você não esperava recebê-lo, ignore.
                            </p>
                        </td>
                    </tr>
                </table>
                <p style="margin:16px 0 0 0;color:#98a2b3;font-size:12px;">
                    © {{ now()->year }} Avalia · avaliaone.com.br
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
