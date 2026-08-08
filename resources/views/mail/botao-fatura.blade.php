{{-- O botao de dinheiro dos e-mails de cobranca.

     Existe separado porque quatro e-mails falam da mesma fatura, e para onde
     eles levam e uma decisao so. Quando a cobranca esta emitida, o botao abre
     a pagina de pagamento do provedor, com boleto, Pix e cartao e sem pedir
     login. Quando nao esta, cai no portal, que explica a situacao. --}}
@include('mail.botao', [
    'url' => $fatura->linkDePagamento(),
    'rotulo' => $fatura->cobrancaEmitida() ? 'Pagar minha fatura' : 'Ver minha fatura',
])
