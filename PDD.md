# Documento de Produto: Avalia

Revisão de 02/10/2026. Descreve o que existe, o que está decidido e o que falta
decidir. Onde uma regra ainda não foi implementada, está dito; onde depende de
decisão comercial, está na seção 16.

**Aqui fica a regra de negócio, e só ela.** Rodar o projeto é `README.md`;
publicar é `DEPLOY.md`; custo do fornecedor é `PRECOS-FORNECEDOR.md`; como se
escreve código e tela são as skills `padroes` e `enxugar`. Número medido
(rotas, testes, linhas) não entra aqui: nasce errado no dia seguinte, e quem
quer o número tem `php vendor/bin/pest` e `php artisan avalia:inventario`.

---

## 1. A casa e os produtos

**Avalia** é uma software house. Razão social AVALIA ONE NEGOCIOS CORPORATIVOS
LTDA, nome fantasia registrado AVALIA 360; nenhum dos dois é a marca exibida. O
texto institucional sai de `App\Support\Empresa` e de `config/empresa.php`,
nunca escrito na tela. Dois sócios meio a meio, Pedro e Ruan, tocam a operação.

| Frente | Marca | O que vende | Receita |
|---|---|---|---|
| Pesquisa de score | **Avalia One** | Plano mensal com franquia de consultas | Mensalidade + consumo |
| Venda parcelada | **Avalia Gestor** | Carnê e cobrança para quem vende a prazo | Taxa sobre cada pagamento |
| Vendas de rua | **Avalia Sales** | Plaquinha de QR e NFC, encurtador, base de negócios | Venda unitária + renovação |
| Back office | **Avalia ERP** e **Avalia CRM** | Caixa, equipe, documentos, auditoria; contatos e leads | (não vende) |
| Serviços de software | **Avalia** | RPA, integrações, URA, desenvolvimento | Projeto, sob contrato |

As três primeiras são produtos de prateleira na mesma aplicação, cada um com
lateral, marca e home próprias. A quarta é trabalho por projeto, apresentado no
site e fechado fora do sistema; não há módulo de projeto, proposta ou horas, e
isso é decisão.

**Cada produto tem regra de dinheiro própria.** Não existe "a comissão da
Avalia": existe a comissão de consultas, a taxa do Gestor e a comissão das
plaquinhas, com bases e percentuais diferentes. A seção 9 trata as três lado a
lado. Confundi-las é o erro mais caro que este documento existe para evitar.

### A marca

O desenho é do dono (Corel, 02/10/2026): o arco de medidor em quatro faixas de
magenta e o ponteiro na faixa alta. O traçado mora em `App\Support\Marca` e sai
dali para o logotipo das telas, o favicon e o miolo do QR; `MarcaTest` cobra que
as três cópias sejam o mesmo desenho. No tema escuro o arco e o nome vão para o
azul da casa; o nome escrito usa o degradê rosa-azul da pontuação
(`texto-bureau`), o mesmo do "movimento." da home. Fonte do arquivo:
`public/marca/avaliaone.svg`.

### O que a comunicação pode prometer

A casa vende informação para decisão, não acesso a dado nem serviço financeiro.
Vocabulário permitido: "pesquisa de score", "pontuação e histórico público", "a
decisão é sempre sua", sempre amarrado a uma transação do próprio contratante.

Banidos da página pública e da vitrine, com teste que derruba a suíte: "análise
de crédito" e "consulta de crédito", "empréstimo" e qualquer garantia de
aprovação. O rodapé declara que a Avalia não concede empréstimo, não garante
aprovação e não decide em nome do contratante.

Base legal: Lei 12.414/2011 (consulente pela finalidade permitida,
responsabilidade solidária da cadeia, art. 16); LGPD art. 7º, X (proteção ao
crédito dispensa consentimento só com essa finalidade).

Marca de fornecedor não aparece em lugar nenhum: nem no catálogo, nem no
portal, nem em documento gerado.

---

## 2. Contas, papéis e o que cada um enxerga

Três tabelas de conta, com guards separados, e cinco papéis.

| Papel | Tabela / guard | Responsabilidade |
|---|---|---|
| Administrador | `staff` | Catálogo, equipe, financeiro, documentos, campanhas, conexões, Sales inteiro |
| Vendedor | `staff` | Carteira própria no One, prospecção distribuída, demonstração; placas e vendas no Sales |
| Empresa (conta master) | `clientes` / `empresa` | Contrata plano, consulta, aceita documentos, paga fatura |
| Operador | `operadores` (sessão da empresa) | Consulta em nome da empresa, com identidade própria |
| Produtor | `produtores` / `produtor` | Vende parcelado no Gestor, recebe repasse |

Superusuário é marca em `staff.super`, não papel: ignora policies e existe em um
exemplar. **Sócio não é papel de sistema**: participação societária é tabela
própria (`socios`), e amarrá-la a `staff` faria remover o acesso de alguém
apagar a quota dele.

### Quatro chaves, quatro perguntas

| Chave | Pergunta | Nasce |
|---|---|---|
| `papel` admin | Pode operar o produto | no cadastro |
| `pode_financeiro` | Pode confirmar pagamento e fechar competência | negada, mesmo para admin |
| `pode_socios` | Pode ver e lançar no caixa dos donos | negada, mesmo para admin |
| `acessa_one` / `acessa_sales` | Em qual produto a pessoa entra | ligadas; a administração desliga por pessoa em Equipe |

Administração entra nos dois produtos. Vendedor entra onde foi ligado, porque a
equipe de rua não é a mesma do CRM. O middleware `produto` decide pela rota,
como a lateral; quem só vende na rua entra direto no Sales depois do login, e a
porta do produto fechado some do menu.

**Porta fechada não joga a pessoa fora da tela.** Quem chega por um link da
própria casa volta para onde estava, com o aviso. A tela inteira (cadeado,
dentro do painel) só aparece para quem digitou o endereço.

### A separação é física, e não condicional

Vendedor tem telas próprias, e não as de administração com campos escondidos.
Custo do fornecedor, lucro e margem não chegam à view: o controller escolhe os
campos. Há teste afirmando que as palavras custo, lucro e margem não aparecem na
carteira nem no portal do cliente. A carteira exibida é sempre a de quem está
autenticado; não existe parâmetro de rota que escolha o vendedor. Trocar o papel
de alguém revoga as sessões abertas.

| Informação | Administrador | Vendedor | Empresa | Produtor |
|---|---|---|---|---|
| Preço de venda | tudo | da carteira dele | do que contratou | da oferta dele |
| Custo do fornecedor, margem e lucro | sim | **nunca** | **nunca** | **nunca** |
| Comissão | de todos | a própria | nunca | — |
| Fatura | todas | da carteira | as próprias | — |
| Consulta e resultado | metadados | metadados da carteira | as próprias, íntegras | — |
| Extrato de repasse | todos | — | — | o próprio |
| Trilha de auditoria | sim | não | não | não |
| Caixa dos sócios | só com `pode_socios` | não | não | não |

**Conflito assumido**: a comissão de consultas é percentual do lucro, e o
vendedor vê a própria comissão, então deduz o lucro com uma conta. A decisão é
manter a comissão visível, porque sem ela a simulação de proposta não serve para
decidir desconto. O que fica fora é o número direto. Alternativas (comissionar
sobre faturamento, ou publicar a alíquota) mudam contrato.

**Visibilidade das plaquinhas é a exceção deliberada**: no Sales a equipe inteira
enxerga a tiragem inteira. A casa é pequena e placa parada porque o vendedor dela
está em campo custa mais que o risco; o controle é a auditoria. Cliente e
produtor continuam limitados ao que é deles. Ver tudo não move comissão:
`vendedor_id` só é escrito na primeira venda.

---

## 3. Vocabulário

Código em português sem acento; tela em português correto.

| Termo | O que é |
|---|---|
| Catálogo | A tabela de preços do One. Uma só, editável, sem versionamento. |
| Serviço | Uma consulta vendável, com código imutável e nome comercial da Avalia. |
| Faixa | Degrau de consumo mínimo. Define a coluna de preços da empresa. |
| Plano | O que a empresa contrata: faixa, mensalidade e franquias. |
| Franquia | Consultas de um serviço já inclusas na mensalidade. Conta em unidades. |
| Consumo mínimo | Piso de **cobrança**, não de consumo. |
| Competência | Mês de referência, AAAA-MM. |
| Fatura | A competência fechada, com a cascata congelada. |
| Piso de preço | Menor preço que paga fornecedor e imposto sem prejuízo. Calculado. |
| Margem | O que sobra depois de imposto, fornecedor e comissão. |
| Adesão | Taxa de entrada, parcelável, rateada meio a meio com o vendedor. |
| Carteira | As empresas de um vendedor. |
| Retenção | Prazo até a resposta do bureau ser apagada. 180 dias. |
| Oferta, Pedido, Parcela | Condição de venda, uma venda parcelada e cada parcela, no Gestor. |
| Repasse | O que a plataforma devolve ao produtor ou ao vendedor. |
| Plaquinha | Placa física de QR e NFC com destino editável. |
| Tiragem | Uma campanha de plaquinhas geradas juntas. |
| Negócio | Um estabelecimento na base de marketing do Sales. |
| Razão | `lancamentos_financeiros` + `partidas_financeiras`: onde o dinheiro é registrado. |
| Aporte | Dinheiro que um sócio põe na empresa. Não é receita. |
| Pró-labore | A parte do sócio que sai toda sexta. |

Regras de nomenclatura:

- Valor interno nunca aparece na tela: `liquidado`, `pendente`, `sucesso` são
  chaves de banco, e a tradução vive em `App\Support\Rotulos`. Ação de
  auditoria nova sem rótulo derruba a suíte.
- A rota não acompanha a troca de marca: `/empresas`, `/cobranca`, `Produto360`,
  `Lancamento360` ficam. URL trocada é link quebrado no bolso de quem recebeu.
  `/sales` é a exceção que nasceu já com o nome certo.
- Nenhum travessão em código, tela, dado, documento ou commit.

---

## 4. Avalia One: pesquisa de score

### Jornada

1. O vendedor fecha a venda e entrega os dados à administração.
2. A administração cria a empresa, vincula o vendedor, configura plano e adesão.
3. A plataforma cadastra o cliente no Asaas e cria a cobrança.
4. O cliente recebe convite de acesso e os documentos de aceite.
5. O cliente consulta e acompanha consumo, franquia e excedente.
6. No fechamento, a fatura é calculada e a cobrança emitida com vencimento dia 10.
7. Webhooks do Asaas atualizam pagamento, atraso e inadimplência.

### Parâmetros comerciais

Provisórios até homologação; homologados, passam a viver no catálogo.

| Parâmetro | Valor |
|---|---|
| Mensalidade | R$ 79,90, consumindo ou não |
| Faixas de consumo mínimo | sem mínimo, 75, 200, 500, 900, 1.500, 5.000 |
| Consumo mínimo negociado | valor livre, independente da faixa |
| Taxa de adesão | livre; parcelável e isentável; 50% vendedor, 50% Avalia |
| Comissão | 10% do **lucro**, ajustável por vendedor, teto 50% |
| Vencimento | dia 10; bloqueio das consultas dia 20 |
| Imposto | 13,50% sobre a nota cheia (05/08/2026) |
| Retenção da resposta | 180 dias; metadados e auditoria são permanentes |

Todo valor é inteiro em centavos, do banco até a tela.

### Cálculo mensal, margem e piso

    consumo_bruto     = consultas com sucesso, pelo preço congelado
    consumo_excedente = consumo_bruto − o que a franquia cobriu
    fatura            = mensalidade + max(consumo_minimo, consumo_excedente)

    imposto  = preço × alíquota
    lucro    = preço − imposto − custo do fornecedor
    comissão = pct × lucro
    margem   = lucro − comissão
    piso     = menor preço em centavos que cobre custo e imposto

A franquia é medida por serviço e em quantidade, antes do excedente: sobre a
soma em reais, um serviço barato cobriria um caro. Consulta que falhou não ocupa
franquia e não é cobrada; o custo dela é absorvido pela Avalia.

O piso é **calculado, nunca cadastrado**, e preço abaixo dele é recusado na
gravação. `Margem::precoAlvoCents` busca em centavos depois da fórmula, porque o
imposto arredonda ao centavo: para custo de R$ 0,85 a 13,50% o piso é R$ 0,98.

A margem alvo é uma escada: vale inteira na faixa sem mínimo e cede um degrau a
cada faixa seguinte. O reajuste ao alvo **só sobe** e **nunca roda sozinho**.
Custo em branco é "não cadastrado", diferente de zero: sem o dado não se exibe
margem nem piso.

### Catálogo

Único e editável, com auditoria de toda alteração, edição linha a linha. **Não
há congelamento do catálogo, e isso é decisão.** O que impede um reajuste de
hoje de alterar cobrança de ontem é cada consulta e cada fatura gravarem preço e
custo **no momento da emissão**. Essa regra é o alicerce do faturamento.

A família veicular está precificada sem contrato fechado: a categoria fica
travada e nenhuma linha dela chega à matriz.

### Execução da consulta e conectores

Antes de chamar o fornecedor, grava a consulta com situação processando,
cliente, vendedor, operador, serviço, documento, competência, preço e custo.
Sucesso contabiliza consumo; erro não cobra e não comissiona; queda mantém o
registro para reconciliação. A finalidade é a do aceite dos termos, gravada
automaticamente; o responsável é quem está logado.

`app/Services/Conectores`: `ConectorSerasa`, `ConectorBoaVista`,
`ConectorSimulado` e `EscolherConector`, sobre o contrato `ConectorBureau`.
**Roteia por serviço**: cada linha do catálogo declara de quem vem. **Em
produção o simulado não responde**; sem credencial ativa a consulta é recusada
antes de qualquer cobrança. O que falta é credencial e homologação, não código.

O laudo tem ordem fixa: decisão no topo (score sempre com o modelo dele),
identidade, restrições da mais grave para a menos, consultas recentes. Bloco
que não veio aparece como **não incluído**, nunca como zero.

### Situação da conta

| Dia | Evento |
|---|---|
| 10 | Vencimento |
| 11 a 19 | Em atraso; consultas liberadas, cliente avisado |
| 20 | Consultas bloqueadas; login aberto para ver a fatura e regularizar |
| Liquidação | Consultas liberadas no mesmo ciclo |

A liquidação é idempotente, libera a comissão daquela fatura e reativa a
empresa só se não restar outra fatura pendente. `EstornarLiquidacao` desfaz a
liquidação e recolhe a comissão liberada.

---

## 5. Avalia Gestor: venda parcelada

Produto distinto, com conta própria. O produtor não consulta score e não recebe
fatura da Avalia. Cadastro é auto-serviço e entra pendente; quem decide se ele
vende é a aprovação. Sem `asaas_wallet_id` não há para onde o split mandar a
parte dele. O comprador chega por link e o checkout é **público e sem login**;
o que protege é teto por origem e campo armadilha.

| Objeto | O que guarda |
|---|---|
| `Produto360` / `Oferta360` | O que se vende e as condições; o slug nasce na oferta e não muda |
| `Pedido360` | Uma venda, com preço, parcelamento e taxa **copiados da oferta** |
| `Parcela360` | Uma parcela do carnê, ou a entrada quando o número é zero |
| `Lancamento360` | Linha do razão do Gestor. Nasce e nunca muda; corrigir é lançar o contrário |

| Parâmetro | Valor |
|---|---|
| Taxa da plataforma | 5% de cada pagamento, sobre o **líquido** da taxa do provedor |
| Teto de parcelas | 12 |
| Piso por parcela | R$ 100,00 |

As quatro partes de um pagamento somam zero (bruto, taxa do provedor, taxa da
plataforma, repasse); a sobra da divisão fica com o produtor. **A parcela só
nasce depois de `efetivado`**, com contrato assinado e entrada confirmada. A
situação da parcela segue **o que o provedor informa por webhook**, e não o
calendário. `InteressadoCobranca` guarda documento e WhatsApp cifrados.

**A página `/cobranca` promete o que o sistema ainda não faz**: régua de
lembrete antes do vencimento, cobrança de quem atrasa, negativação e envio do
link ao cliente. Está na seção 16.

---

## 6. Avalia Sales: plaquinhas, encurtador e base de negócios

O produto das vendas de rua, com lateral própria: Início, QR dinâmico, Gerar
códigos (admin), Meu estoque, Negócios (admin), Encurtador, Vendas (admin).

O código impresso é um endereço permanente da Avalia, e não o link do cliente.
O que muda quando o cliente troca de site é o **destino**, nunca o **código**. A
tag NFC da placa carrega a mesma URL curta, então "QR dinâmico" e "NFC
dinâmico" são o mesmo sistema. O encurtador existe atrás da mesma porta porque
endereço com parâmetros de campanha não cabe nos ~140 bytes de uma NTAG213.

| Parâmetro | Valor |
|---|---|
| Preço da placa | R$ 99,90 (QR e NFC juntos), editável na venda pela administração |
| Custo unitário | R$ 6,00, fechado: placa, fita dupla face, NFC, impressão e logística |
| Renovação | R$ 49,90 por ano; avulso mensal R$ 19,90 |
| Validade | 12 meses; carência de 30 dias; um aviso aos 15 dias (ainda sem chamador) |
| Teto de uma tiragem | 1.000 |

### Ciclo de vida e produção

`em_branco` → `ativa` → `suspensa` ou `baixada`. **Vencida não é situação**: é
conta de data em `Etiqueta::estado()`. Não existe exclusão, e código baixado
nunca volta ao sorteio: reciclar mandaria a freguesia de um cliente para a loja
de um estranho.

A numeração corre entre campanhas. O pacote sai em ZIP, SVG a 30×30mm com fundo
transparente e a marca no miolo, mais CSV de duas colunas (`codigo,photo`) com
o caminho da pasta de Downloads do dono. O QR é desenhado **no navegador**: a
hospedagem não tem composer, e isso tira cem renderizações do CPU
compartilhado. O desenho é determinado pelo código; nada é guardado como
imagem.

### Três colunas, três perguntas

| Coluna | Responde |
|---|---|
| `staff_id` | Quem gerou a tiragem (produção) |
| `dono_tipo` / `dono_id` | De quem é o código (permissão) |
| `vendedor_id` | Quem vendeu (repasse) |
| `consignada_para_id` | Com quem a placa está (estoque pessoal) |

### Estoque pessoal

Estoque é consulta, não contador: `Etiqueta::noEstoqueDe()` e `semDono()`. A
administração entrega N placas livres, ou placas escolhidas por código (lista
separada por vírgula, tudo ou nada), e recolhe o que não vendeu. O vendedor vê
só o que está na mão dele.

### A venda é o cadastro

Apontar uma placa com nome e contato cria o negócio na base de marketing, ou
acha o que já existe pelo telefone (8+ dígitos) e depois pelo nome. Não há link
de cadastro por vendedor. O formulário público `/cadastro` continua, porque
colhe o que a venda não colhe: endereço, horário e categoria para o perfil do
Google.

### Link de avaliação do Google

Serviço público e tela de Negócios: nome do estabelecimento → Places API (New)
`searchText` → `writereview?placeid=` → encurtado pela mesma porta. Sem campo de
cidade: só pede mais dado quando não acha. Chave em Conexões, cifrada, e a
conexão precisa estar **ativada** além de cadastrada. A tela diz "confira o link
antes de cadastrar", porque o gerador pode errar o homônimo.

### Cancelar venda

Apontar e vender são o mesmo clique, e nem toda placa em campo foi vendida.
Cancelar tira só o fato comercial: destino, código e histórico ficam. Suspender
é o inverso: apaga o redirecionamento e mantém a venda. Só a administração
cancela, porque mexe na comissão de mês fechado. Corrigir o valor estorna e
relança no razão.

---

## 7. Aquisição: site, leads e interessados

O site institucional vive no tema claro e só nele; o preto que usa é superfície
do tema claro. A home mostra os três produtos lado a lado, cada um com
**Conhecer** e **Entrar**; Entrar leva à área daquele produto para quem já tem
sessão, ou abre a porta de acesso.

O formulário de contato grava o interessado no banco em vez de abrir o WhatsApp
com dado pessoal na URL; a fila aparece para a administração, e o vendedor não
vê. A base de leads é tabela própria: lead não tem plano, fatura nem senha. A
distribuição mora em tabela de ligação com data e autor. Funil de seis estágios;
**virou cliente** é marcado pela conversão, que exige CNPJ válido e e-mail e abre
o mesmo formulário de empresa.

Isto é a parte do CRM que a seção 15 unifica: hoje são cinco cadastros de pessoa
(`clientes`, `negocios`, `leads`, `interessados`, `produtores`) sem elo entre
eles.

---

## 8. Serviços de software

Apresentados no site e fechados por projeto, fora do sistema. O conteúdo
(blog, páginas de serviço, serviços digitais) é catálogo de aquisição em
`config/softwares.php`, `config/servicos-digitais.php` e `config/blog.php`.
Receita de projeto entra no razão à mão, na categoria "Serviços de software".

---

## 9. Dinheiro: as regras transversais

### Congelamento na emissão

Consulta, fatura, pedido e plaquinha gravam preço e custo **no momento em que
acontecem**. Reajuste de hoje não reescreve cobrança de ontem. Vale nos três
produtos e é a regra mais importante deste documento.

### As três remunerações

| | Avalia One | Avalia Gestor | Avalia Sales |
|---|---|---|---|
| Quem recebe | Vendedor da carteira | Produtor | Vendedor |
| Base | Lucro do mês | Pagamento recebido | **Valor da venda** |
| Percentual | 10%, por vendedor, teto 50% | 95% (taxa da casa 5%) | 25% |
| Elegível | Liquidação da fatura | Liquidação da parcela | Venda registrada |
| Onde mora | `staff.comissao_pct` | `config/cobranca.php` | `config/etiquetas.php` |
| Paga por | `comissao_liberada_em` na fatura | split do Asaas | botão Pagar em Vendas QR |

`staff.comissao_pct` vale só para consultas. Cada fatura guarda o percentual
usado na emissão. A comissão da placa é sobre o valor de venda (02/10/2026)
porque o custo é fixo e o vendedor não influi nele; arredonda por venda.

**Não geram comissão** no Sales: venda de sócio (ele recebe pela divisão) e
venda sem vendedor (não houve venda de ninguém).

### Reparte de cada placa e a parte dos sócios

    líquido  = venda − custo
    comissão = 25% da venda
    lucro    = líquido − comissão
    parte    = lucro ÷ sócios, centavo ímpar ao primeiro do config

**Cada sócio leva 30% do lucro de tudo, vendesse quem vendesse; 40% fica na
empresa** (02/10/2026; antes era 25% e 50%). Em R$ 100 de lucro: R$ 30 para
cada um, R$ 40 ficam. No código isso é a parte de cada um (metade) com retenção
de 40%. O que fica não gera lançamento, porque já está no caixa desde a venda. A
divisão roda uma vez sobre o mês; a comissão, por venda.

**Os pagamentos saem toda sexta-feira**: pró-labore e comissões. A comissão do
Sales se paga em Vendas QR, por vendedor: marca cada venda (`comissao_paga_em`)
e lança no razão a baixa de `comissao-a-pagar` contra `caixa`, natureza
`pagamento`. A home do Sales mostra ao vendedor a comissão atual (em aberto, de
qualquer mês) e a do mês; ao sócio, o pró-labore do mês com a parte e a
retenção.

**Pendência:** divisão do resultado do One e do Gestor entre os sócios só existe
como regra implícita (50/50 pelo `socios.participacao_bps`), sem rotina.

### O que "lucro" não inclui

Lucro de produto é antes de custo fixo e imposto sobre o resultado. Hospedagem,
domínio, ferramenta e trabalho entram no razão como despesa por categoria, e é
o razão que diz o resultado da empresa. Somar lucro de produto com resultado da
empresa dá número errado: é a mesma receita vista de dois recortes.

---

## 10. O razão e o caixa dos sócios

Módulo com permissão própria (`pode_socios`), fora dos portais de cliente,
vendedor e produtor.

### Partidas dobradas, um escritor

`App\Contabil\Lancar` é o único escritor de `lancamentos_financeiros` e
`partidas_financeiras`; `Partidas` recusa o que não soma zero; a
`DiretivaTest` recusa um quarto razão. Quem lança escolhe a **natureza**, nunca
a conta: a tradução natureza → pernas vive em `RegistrarLancamento`. Corrigir é
**estornar**, que lança as mesmas pernas com sinal trocado e amarra as duas
linhas; a competência do estorno é a de hoje.

| Natureza | Efeito |
|---|---|
| Aporte | Caixa sobe, patrimônio do sócio sobe. Não é receita. |
| Empréstimo de sócio | Caixa sobe, empresa passa a dever. |
| Despesa paga pelo sócio | Despesa existe; caixa não se move; empresa deve ao sócio. |
| Reembolso | Quita a dívida e reduz o caixa. Não é segunda despesa. |
| Receita | Caixa sobe; recusada em competência que já tem receita automática. |
| Despesa | Caixa cai, por categoria. |
| Transferência | Entre contas de dinheiro. Resultado não muda. |
| Retirada | Caixa cai, dívida com o sócio cai. |
| Distribuição | Caixa cai, patrimônio do sócio cai. Depende de decisão. |
| Pagamento | Quita comissão ou conta a pagar. Sai do sistema, nunca do formulário. |
| Pró-labore | Caixa cai; despesa de pessoal do sócio. Sai da sexta-feira. |
| Provisão | Despesa reconhecida, dívida com fornecedor; o caixa só cai no pagamento. Sai de Contas a pagar. |

### Plano de contas

Por produto: `receita:one|gestor|plaquinha|servicos`, `custo:*`; `comissao` e
`comissao-a-pagar`; `imposto` e `imposto-a-pagar`; `clientes-a-receber`;
`fornecedores-a-pagar`; `taxa-provedor`; patrimônio e empréstimo por sócio
(`aporte:<id>`, `emprestimo:<id>`); categorias de despesa (`despesa:prolabore`,
`despesa:fornecedor`, `despesa:infra`, `despesa:marketing`, `despesa:pessoal`).
Conta nasce em migration, com código estável; conta criada em runtime foi o que
fez produção responder "a conta caixa não está cadastrada".

### Livro-caixa

A tela de Sócios é o livro-caixa: entradas e saídas do mês com saldo linha a
linha (`App\Contabil\LivroCaixa`, lendo as pernas nas contas de dinheiro:
ativo menos clientes a receber), o resultado por categoria e a planilha do mês
em duas abas. A operação é manual por decisão: olha-se o banco (conta PJ no
Nubank), lança-se aqui, e a diferença entre saldo do banco e saldo da conta
`caixa` é a conferência do fechamento. Importar o extrato OFX e conciliar
automaticamente está na seção 15.

### O que já lança sozinho, e o que não

| Evento | Lança no razão |
|---|---|
| Fatura do One liquidada | sim, inteira: receita, imposto, custo do fornecedor e comissão (`FaturaNoRazao`); estorno desfaz |
| Venda de plaquinha, cancelamento, correção de valor | sim |
| Comissão do Sales e do One pagas | sim, pela sexta-feira |
| Conta a pagar registrada e paga | sim (`provisao` e `pagamento`) |
| Parcela do Gestor paga | sim, a parte da casa como receita do Gestor (`ParcelaNoRazao`) |
| Consulta executada, fatura fechada (a receber) | **não**: ainda é coluna no documento |
| Aporte, retirada, pró-labore, despesa, receita de projeto | à mão, em Sócios ou na sexta |

Fatura marcada como paga sem a linha no razão é divergência que só aparece na
conferência do mês. Não duplica por construção: `origem_tipo`/`origem_id` é
único.

---

## 11. Diretivas

Hoje um único parâmetro é configurável pela tela: a alíquota de imposto. O resto
é constante em código.

| Regra | Valor | Onde |
|---|---|---|
| Dia do vencimento e tolerância | 10, 10 dias | `Fatura` |
| Aviso antes do vencimento | 3 dias | `AvisarVencimentoProximo` |
| Retenção da resposta | 180 dias | `Consulta::DIAS_DE_RETENCAO` |
| Teto diário por empresa, demonstrações | 500, 10/dia | `Consulta` |
| Janela anti cobrança dupla | 120 s | `Consulta::SEGUNDOS_SEM_REPETIR` |
| Validade do convite | 48 h | `Convite` |
| Comissão padrão e teto | 10%, 50% | `Comissao` |
| Placa: preço, custo, comissão, retenção | 99,90; 6,00; 25%; 40% | `config/etiquetas.php` |
| Gestor: taxa, parcelas, piso | 5%, 12, R$ 100 | `config/cobranca.php` |

Um cadastro de Diretivas com vigência, autor e motivo é pendência. Retenção e
validade do convite são compromisso legal e de segurança, não preferência.

A catraca do repositório (`tests/Unit/DiretivaTest.php`) cobra o que a skill
`enxugar` descreve: comentário sobre código só desce, aderência ao tema só sobe,
exatamente três tabelas de razão, e `app/Contabil` não importa modelo de
produto.

---

## 12. Segurança, LGPD e auditoria

- Senha com hashing do Laravel; sessão revogável por `sessao_versao`.
- Login limitado por conta e origem, com castigo progressivo em tabela.
- Nunca registrar senha, token, cookie ou chave de API em log. Segredos de
  conexão (Asaas, Google, bureaus) ficam cifrados em `conexoes`.
- `avalia:ambiente` impede subir com depurador ligado, cookie fora de HTTPS,
  e-mail desligado ou `.env` alcançável pela web (conferido pelo disco).
- Expurgo da resposta do bureau aos 180 dias, preservando metadados fiscais.
- Convite de acesso: ninguém digita a senha de outro; o link morre pelo prazo e
  pelo uso. Operadores têm conta própria, para "quem consultou este documento".
- Trilha de auditoria com nome de negócio em `Rotulos`, entidade congelada no
  registro, resumo encadeado que denuncia alteração. Nome de consumidor
  consultado nunca entra no congelado.

---

## 13. Operação

Hospedagem compartilhada da Hostinger, MySQL, sem SSH, sem Composer e sem Node
no servidor. Publicação por cron de minuto; `public/build` versionado. Comando
avulso em produção entra por cron criado pela API. Tudo em `DEPLOY.md`.

---

## 14. Decisões de desenho que já pagaram

Registradas para não serem desfeitas por engano.

- **Estoque é consulta, saldo é soma.** Nenhum contador de placas, nenhuma
  coluna de saldo no razão: o número é sempre a soma da tabela.
- **Natureza, não conta.** Quem lança escolhe o que aconteceu; o sistema sabe as
  pernas.
- **Erro de IA é volume, não bug.** A catraca mede comentário, tema e razões
  paralelos e só deixa descer. Procure antes de escrever.
- **Marca escrita uma vez.** `Empresa::marca*()` e `Marca` são a única fonte;
  escrita à mão foi o que deixou "Avalia 360" em tela de outro produto.
- **O site é tema claro.** Preto no site é superfície, não tema escuro.
- **A venda é o cadastro.** Todo evento operacional cria ou atualiza a pessoa;
  formulário separado só colhe o que o evento não colhe.

---

## 15. Rumo: CRM, ERP e o back office

Decidido em 02/10/2026, com os dois sócios de acordo.

### O desenho

Um monólito modular, não dois sistemas conversando por API:

    Frentes      Avalia One      Avalia Gestor      Avalia Sales
                      │                │                 │
                      ▼    eventos de negócio, em processo
    Núcleos      app/Crm  (contato, vínculo, interação, funil)
                 app/Contabil  (razão, contas a pagar e a receber, fechamento, sexta)
                      │
    Borda        Conexões (Asaas, Google, bureaus)  +  /api/v1 quando houver consumidor

Frente importa núcleo; núcleo nunca importa frente (teste de fronteira, como já
existe para `app/Contabil`). Frente não escreve no razão nem na base de contatos:
dispara `VendaRegistrada`, `FaturaLiquidada`, `ParcelaPaga`, `LeadConvertido`, e
o núcleo ouve, na mesma transação. API interna entre módulos, agregador
bancário pago e CRM de terceiro foram avaliados e descartados por custo e por
dupla fonte de verdade.

### Núcleo CRM (feito em 02/10/2026, menos o funil)

`contatos` (nome, documento, e-mail, WhatsApp, telefone, cidade, origem),
`vinculos` (que papel o contato tem em cada frente) e `interacoes` (linha do
tempo). As cinco tabelas **ficaram** e ganharam `contato_id`; cada uma diz quem é
pela interface `App\Crm\TemContato`, e `App\Crm\Contatos` acha ou cria no
momento do cadastro (observer no provider) e no lastro (`avalia:lastrear-contatos`).
Deduplica por documento, depois WhatsApp ou telefone, depois e-mail; nunca só
pelo nome. O núcleo não importa modelo de frente (teste de fronteira). A tela é
Contatos, no ERP. Falta o funil sobre a mesma tabela.

### Núcleo ERP

Uma regra decide tudo: **todo evento de dinheiro lança no razão; todo relatório
lê do razão.** Documento de operação (`faturas`, `pedidos_360`, etiqueta) segue
sendo onde a operação acontece e deixa de ser onde o resultado mora.

| Fase | Estado |
|---|---|
| 0. Catraca (três razões, comentário, tema) | feita |
| 1. Plano de contas por produto | feita |
| 2. Regras de lançamento, uma classe por evento | plaquinha, fatura liquidada do One e parcela do Gestor feitas; faltam consulta executada e fatura fechada (a receber) |
| 3. Lastro do histórico (lê, nunca escreve no documento) | plaquinha, faturas e parcelas feitos |
| 4. Conciliação subrazão × razão com teste | feita para a plaquinha e para a fatura |
| 5. Relatórios leem do razão (`PainelController`, `Caixa`, carteira, painéis) | pendente |
| 6. Coluna derivada sai do documento | depois da 5 |

O que a operação de dois sócios pede, feito em 02/10/2026: contas a pagar com
vencimento (`/erp/contas`); a tela de **Pagamentos** (`/erp/pagamentos`: comissões do
One e do Sales, pró-labore sugerido por sócio, contas da semana, lista de Pix);
natureza `prolabore` separada de retirada e distribuição. Falta: importação do
extrato OFX do Nubank e conciliação contra a conta `caixa` (operação manual por
decisão, seção 10).

### Back office

**Avalia ERP** e **Avalia CRM** (02/10/2026): duas laterais, no molde do Sales. O
ERP tem Financeiro, Pagamentos, Contas a pagar, Sócios, Controladoria,
Documentos, Equipe, Conexões e Auditoria; o CRM tem Contatos e Leads (o funil
entra aqui). Saiu tudo do painel do One, que ficou com consultas, carteira,
catálogo e campanhas. No pé de toda lateral, o grupo "Áreas" leva às outras que a
conta abre; é a única porta entre produtos. São áreas do mesmo sistema, não
serviços separados: a separação é de menu e de namespace (`app/Contabil`,
`app/Crm`), cobrada por teste de fronteira.

### Ordem

1. Lateral de back office, movendo o que existe. **Feita.**
2. `contatos` + `contato_id` + lastro. **Feito.**
3. Cadastro nas frentes vira contato (observer) e teste de fronteira. **Feito**; venda de placa anota na linha do tempo, os demais eventos ainda não.
4. Razão para One (fatura liquidada) e sexta unificada **feitos**; faltam consulta executada, fatura fechada e Gestor.
5. Funil; fase 5 (relatórios lendo do razão).
6. `/api/v1` quando aparecer o primeiro consumidor externo.

### Redundâncias a mitigar, por custo

Cinco cadastros de pessoa; dinheiro somado fora do razão em cinco lugares; duas
mecânicas de comissão paga; cinco classes de regra de dinheiro, três delas sobre
a mesma cascata do One; quatro filtros de listagem iguais; três formulários de
captação; quatro painéis com totais próprios; dois fluxos de login; 37 tabelas
desenhadas à mão nas views; planilhas e PDFs com a mesma moldura repetida. As
três primeiras erram dinheiro e vão antes.

---

## 16. Decisões pendentes

### Comercial

- **Contrato do vendedor de consultas**: a comissão saiu de 10% do consumo para
  10% do lucro em 05/08/2026, e o contrato precisa dizer isso. Conferir o que
  foi assinado.
- **Divisão do resultado** do One e do Gestor entre os sócios, como rotina.
- **Virada de plano no meio do mês**; **vigência** sem efeito; **carência
  especial** sem código; **cancelamento de contrato** inexistente; **reajuste
  anual**; homologar preços de referência e margem; franquia por serviço.
- **O que `/cobranca` promete e o Gestor não faz**: régua de lembrete, cobrança
  do atrasado, negativação, envio do link. Construir ou tirar da página.

### Cobrança

Pagamento parcial, duplicidade e renegociação; juros, multa, desconto e
tolerância; chargeback e Pix devolvido depois da comissão liberada; consulta
duplicada dois minutos depois; quem altera a adesão assinada; desligamento de
vendedor (a carteira fica, as comissões cessam: escrito, sem código).

### Técnico, por ordem de risco

1. Isolamento entre empresas e carteiras, testado e não presumido.
2. Conciliação entre o que o provedor recebeu e o que a Avalia registrou.
3. Consulta duplicada por clique repetido.
4. Pipeline que bloqueie merge com teste falhando (a suíte roda no GitHub; não gateia).
5. Diretivas configuráveis.
6. Fila de e-mail que falhou avisa alguém.
7. Exportação e expurgo por titular, antes do primeiro pedido real de LGPD.
8. Aviso de vencimento da placa (`Etiqueta::scopeAvisarEm` sem chamador; as
   primeiras vencem em setembro de 2027).
9. Correção de valor abaixo do custo deixa o lucro negativo; `dividir` com
   negativo não é testado.

### Deliberadamente fora do escopo

Dupla aprovação para baixa manual, criptografia por coluna, expiração periódica
de senha, fallback entre fornecedores, fila de processamento, ambiente de
homologação separado, antivírus em upload, BI com coorte e tendência, módulo de
projeto para os serviços de software, agregador bancário pago.
