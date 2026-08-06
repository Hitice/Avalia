{{-- O botao de acao dos e-mails: div centralizada porque <a> display block
     nao centraliza igual em todos os clientes. Espera $url e $rotulo. --}}
<div style="text-align:center;margin:18px 0 14px 0;">
    <a href="{{ $url }}"
       style="display:inline-block;background-color:#465fff;color:#ffffff;text-decoration:none;font-size:14px;font-weight:bold;padding:10px 22px;border-radius:8px;">
        {{ $rotulo }}
    </a>
</div>
