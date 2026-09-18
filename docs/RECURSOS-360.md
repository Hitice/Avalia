# Avalia 360 — o que existe, o que falta, o que decidir

Escreva a sua resposta depois da seta de cada linha. Pode ser `sim`, `não`,
`depois`, ou o que quiser mudar. Quando terminar, me avise que eu leio tudo e
reorganizo o plano a partir das suas respostas.

Última revisão: 18/09/2026.

---

## 1. Funciona hoje, testado e no ar

Nada aqui precisa de decisão, mas diga se algum deve sair ou mudar.

- **Página pública do 360** (`/cobranca`) — promessa, como funciona, perfis, dúvidas. →
- **Formulário de contato** — nome, WhatsApp, e-mail, Instagram, o que vende, papel, prazo e faturamento. Grava no banco e avisa o comercial por e-mail. →
- **Conta do produtor** — cadastro em quatro campos, login, "manter conectado", recuperação de senha. →
- **Painel do produtor** — já recebido, a receber, em atraso e as últimas vendas. →
- **Checkout público** (`/pay/{slug}`) — dados, endereço, melhor dia, aceite do contrato. →
- **Motor de análise** — documento válido, idade mínima, uma compra em aberto por CPF, lista de bloqueio, restrição reduz o teto de parcelas, piso por parcela. Grava a versão da regra em cada decisão. →
- **Cobrança da entrada** com split para a conta do produtor. →
- **Carnê automático** — nasce quando a entrada é confirmada, com a sobra da divisão na primeira parcela e vencimento no melhor dia. →
- **Webhook do provedor** — baixa de pagamento, marcação de vencido, à prova de reentrega. →
- **Razão contábil** — quatro lançamentos por parcela paga (bruto, taxa do provedor, taxa da plataforma, repasse) que somam zero. →
- **Subconta no provedor** — código pronto, nunca testado contra o Asaas de verdade. →

---

## 2. A página promete e o sistema NÃO faz

Isto me preocupa. Cada linha abaixo está escrita na `/cobranca` hoje.

- **Régua de lembrete antes do vencimento** — existe para as faturas da Avalia One, não para as parcelas do 360. Construo, ou tiro da página? →
- **Cobrança de quem atrasa** — a parcela é marcada como vencida, e nada mais acontece. Nenhum e-mail, nenhum WhatsApp. →
- **Negativação sob seu comando** — não existe botão nem integração. →
- **"Link gerado e enviado ao cliente"** — o sistema gera o link; quem envia é o produtor. Implemento o envio, ou mudo a frase? →

---

## 3. Falta para alguém conseguir usar

Sem estes, nenhum produtor opera sozinho.

- **Tela de cadastro de produto e oferta** — hoje só por código. →
- **Aprovação do produtor pelo admin**, com abertura da subconta e coleta de documento e endereço. →
- **Validação no Asaas de sandbox** — provar que a subconta, o cliente e a cobrança com split funcionam de verdade. →
- **Contrato em PDF** — o aceite fica registrado com data, mas não existe documento para guardar ou mandar. →

---

## 4. Existe na TMB e não aqui

- **Antecipação de recebíveis** (eles fazem de 20% a 50%) — exige capital seu para bancar. →
- **Recuperação de carrinho abandonado** por WhatsApp, e-mail e automação. →
- **Programa de indicação** com link próprio e comissão sobre quem for indicado. →
- **Portal do cliente final** — segunda via, acompanhamento das parcelas. →
- **Renegociação** — cancelar as parcelas em aberto e refazer o parcelamento. →
- **Antifraude** além da análise de crédito. →
- **Cartão próprio** (o TMBcard deles). →
- **Rastreamento de origem da venda** (UTM), para saber qual anúncio trouxe o comprador. →
- **Especialista dedicado no onboarding** e suporte separado para produtor e para cliente final. →
- **Depoimentos com nomes conhecidos** na página. →

---

## 5. O que eu proporia, e não está em lugar nenhum

- **Risco projetado antes de criar a oferta** — usar a base da Avalia One para dizer ao produtor que, para o público dele, 12x tem inadimplência estimada X e 6x tem Y. Ninguém oferece isso, e a casa já tem os dados. →
- **API pública para o produtor** — consultar pedidos e criar ofertas pelo sistema dele. →
- **Webhook de saída** — avisar o sistema do produtor quando a parcela é paga ou vence. →
- **Exportação para planilha** de vendas e recebíveis. →
- **Segunda via pelo próprio cliente**, sem passar pelo produtor. →

---

## 6. Regras de dinheiro para confirmar

Estão em `config/cobranca.php` e mudam sem mexer em código.

- **Taxa da plataforma: 5%** sobre cada pagamento. →
- **Teto de 12 parcelas.** →
- **Piso de R$ 100 por parcela.** →
- **Entrada mínima de 10%**, vencendo em 7 dias. →
- **Arrependimento: 7 dias** com devolução da entrada. →
- **Restrição no birô reduz o teto para 6x** em vez de reprovar. →
- **Consulta a birô desligada** por enquanto (custa por consulta). →

### Para o público negativado, que você mencionou

Se o alvo é quem tem desconto indevido e nome sujo, eu mudaria assim.
Confirme ou corrija cada número.

- **Entrada mínima de 20% a 30%** em vez de 10%. →
- **Teto de 6x**, e 4x acima de certo valor. →
- **Piso de R$ 50** por parcela, para caber no orçamento. →
- **Ignorar restrição na análise**, já que ela é a regra e não a exceção. →
- **Negativação como cobrança perde efeito** nesse público. Qual seria o mecanismo? →

---

## 7. Perguntas que preciso que você responda

- **Qual o ticket médio do serviço** que você vende para esse público? →
- **Quanto tempo** entre a contratação e o cliente receber a recuperação? →
- **Quem atende** o produtor e o cliente final no dia a dia? →
- **Existe capital** para antecipação, ou fica fora do escopo? →
- **O 360 é para vender ao mercado** ou para uso próprio primeiro? →
