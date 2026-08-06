<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Seu acesso à Avalia</title>
</head>
{{-- Estilo inline e estrutura simples de proposito: cliente de e-mail nao
     carrega CSS externo e cada um renderiza a seu modo. O que importa chegar
     inteiro e o link. --}}
<body style="margin:0;padding:0;background-color:#f2f4f7;font-family:Arial,Helvetica,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f2f4f7;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                       style="max-width:520px;background-color:#ffffff;border-radius:16px;border:1px solid #e4e7ec;">
                    <tr>
                        <td style="padding:32px 36px 8px 36px;">
                            <span style="font-size:22px;font-weight:bold;color:#1d2939;">Avalia<span style="color:#98a2b3;">one</span></span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 36px 0 36px;color:#344054;font-size:15px;line-height:1.6;">
                            <p style="margin:0 0 12px 0;">Olá, {{ $nome }}.</p>
                            <p style="margin:0 0 12px 0;">
                                {{ $ehEmpresa
                                    ? 'O acesso da sua empresa à Avalia foi criado. Para começar a consultar, defina a sua senha:'
                                    : 'Seu acesso à Avalia foi criado. Para entrar, defina a sua senha:' }}
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:16px 36px 8px 36px;">
                            <a href="{{ $link }}"
                               style="display:inline-block;background-color:#465fff;color:#ffffff;text-decoration:none;font-size:15px;font-weight:bold;padding:13px 32px;border-radius:10px;">
                                Definir minha senha
                            </a>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 36px 28px 36px;color:#667085;font-size:13px;line-height:1.6;">
                            <p style="margin:0 0 8px 0;">
                                O link vale por {{ App\Support\Convite::HORAS_DE_VALIDADE }} horas e deixa de funcionar
                                depois que a senha for definida.
                            </p>
                            <p style="margin:0;">
                                Se você não esperava este e-mail, ignore. Nenhum acesso é criado sem a definição da senha.
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
