{{-- O botao de dinheiro dos e-mails de cobranca.

     Existe separado porque quatro e-mails falam da mesma fatura, e para onde
     eles levam e uma decisao so: o boleto em PDF quando ha, a pagina de
     pagamento do provedor quando nao, e o portal em ultimo caso. --}}
@include('mail.botao', ['url' => $fatura->linkDePagamento(), 'rotulo' => 'Ver minha fatura'])
