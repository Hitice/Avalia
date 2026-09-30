# Documento de Produto: Avalia

Revisão de 29/09/2026. Descreve o que existe, o que está decidido e o que falta
decidir. Onde uma regra ainda não foi implementada, está dito; onde depende de
decisão comercial, está na seção 14.

**Aqui fica a regra de negócio, e só ela.** O resto tem endereço próprio:

| Assunto | Onde |
|---|---|
| Rodar o projeto | `README.md` |
| Publicar | `DEPLOY.md` |
| Para onde o financeiro vai | `PLANO-FINANCEIRO.md` |
| Custo do fornecedor | `PRECOS-FORNECEDOR.md` |
| Como se escreve código e tela | skill `padroes`, skill `enxugar` |
| Estado medido do repositório | `php vendor/bin/pest`, `php artisan avalia:inventario` |

A última linha é decisão: a versão anterior trazia uma seção com contagem de
rotas, models e testes, datada num commit. Ela nasceu errada no dia seguinte, e
quem quer o número tem o comando.

---

## 1. A casa e os produtos

**Avalia** é uma software house. A razão social é AVALIA ONE NEGOCIOS
CORPORATIVOS LTDA e o nome fantasia registrado é AVALIA 360; nenhum dos dois é a
marca exibida. O texto institucional sai de `App\Support\Empresa` e de
`config/empresa.php`, nunca escrito na tela.

A casa opera três produtos próprios e presta serviços de software sob contrato.

| Frente | Marca | O que vende | Receita |
|---|---|---|---|
| Consultas de crédito | **Avalia One** | Plano mensal com franquia de consultas | Mensalidade + consumo |
| Venda parcelada | **Avalia Gestor** | Carnê e cobrança para quem vende parcelado | Taxa sobre cada pagamento |
| QR dinâmico | (sem marca própria) | Plaquinha de QR e NFC com destino editável | Venda unitária + renovação |
| Serviços de software | **Avalia** | RPA, integrações, URA, desenvolvimento | Projeto, sob contrato |

As três primeiras são produtos de prateleira dentro da mesma aplicação. A quarta
é trabalho por projeto, apresentado no site e fechado fora do sistema.

**Cada produto tem regra de dinheiro própria.** Não existe "a comissão da Avalia":
existe a comissão de consultas, a taxa do Gestor e a comissão das plaquinhas, com
bases e percentuais diferentes. Confundi-las é o erro mais caro que este documento
existe para evitar. A seção 9 trata as três lado a lado.

### O que a comunicação pode prometer

A casa vende informação para decisão, não acesso a dado nem serviço financeiro.
O vocabulário permitido é "pesquisa de score", "pontuação e histórico público", "a
decisão é sempre sua", sempre amarrado a uma transação do próprio contratante.

Banidos da página pública e da vitrine de campanha, com teste que derruba a suíte:
"análise de crédito" e "consulta de crédito", que sugerem atividade de bureau ou de
concessão; "empréstimo" e qualquer garantia de aprovação. O rodapé declara que a
Avalia não concede empréstimo, não garante aprovação e não decide em nome do
contratante.

Base legal: a Lei 12.414/2011 define o consulente pela finalidade permitida e
responsabiliza solidariamente a cadeia (art. 16); a LGPD dispensa consentimento na
base de proteção ao crédito (art. 7º, X) apenas quando a finalidade é essa.

Marca de fornecedor não aparece em lugar nenhum: nem no catálogo, nem no portal,
nem em documento gerado. Citar bureau, mesmo genericamente, depende de contrato de
distribuição que autorize.

---

## 2. Contas, papéis e o que cada um enxerga

São **três tabelas de conta**, com guards separados, e cinco papéis.

| Papel | Tabela / guard | Entra por | Responsabilidade |
|---|---|---|---|
| Administrador | `staff` / `staff` | `/entrar` | Catálogo, equipe, financeiro, documentos, campanhas, conexões |
| Vendedor | `staff` / `staff` | `/entrar` | Carteira própria, prospecção distribuída, demonstração, venda de plaquinha |
| Empresa (conta master) | `clientes` / `empresa` | `/entrar` | Contrata plano, consulta, aceita documentos, paga fatura |
| Operador | `operadores` (sessão da empresa) | `/entrar` | Consulta em nome da empresa, com identidade própria |
| Produtor | `produtores` / `produtor` | Página do Gestor | Vende parcelado, recebe repasse |

Superusuário é marca em `staff.super`, não um papel: ignora policies e existe em
um exemplar, criado pelo seeder.

**Sócio não é papel de sistema.** Participação societária não concede acesso
técnico, e a recíproca vale: sócio que não opera o sistema também tem quota, por
isso `socios` é tabela própria e não uma marca em `staff`. Amarrá-las faria remover
o acesso de alguém apagar a participação dele.

A gestão financeira dos sócios é a terceira permissão da casa, ao lado de `admin` e
`pode_financeiro`, e as três respondem perguntas diferentes: administrar é operar o
produto, financeiro é confirmar pagamento de cliente, e sócios é o caixa dos donos.

### A separação é física, e não condicional

Vendedor tem telas próprias, e não as telas de administração com campos escondidos.
Custo do fornecedor, lucro e margem não chegam à view: o controller escolhe os
campos que envia. Há teste afirmando que as palavras custo, lucro e margem não
aparecem na carteira nem no portal do cliente.

A carteira exibida é sempre a de quem está autenticado. Não existe parâmetro de
rota que escolha o vendedor, então não há endereço que peça a de outro. Trocar o
papel de alguém revoga as sessões abertas daquela conta.

### Permissão financeira

Confirmar pagamento libera comissão sem dinheiro ter entrado, e fechar competência
emite cobrança. Não é o mesmo tipo de decisão que renomear um serviço.

A permissão **nasce negada** e é concedida uma a uma no cadastro da equipe. O
superusuário passa por cima; o vendedor nunca recebe, mesmo com a marca ligada por
engano.

### O que cada papel vê

| Informação | Administrador | Vendedor | Empresa | Produtor |
|---|---|---|---|---|
| Preço de venda | tudo | da carteira dele | do que contratou | da oferta dele |
| Custo do fornecedor | sim | **nunca** | **nunca** | **nunca** |
| Margem e lucro | sim | **nunca** | **nunca** | **nunca** |
| Comissão | de todos | a própria | **nunca** | — |
| Fatura | todas | da carteira | as próprias | — |
| Consulta e resultado | metadados | metadados da carteira | as próprias, íntegras | — |
| Extrato de repasse | todos | — | — | o próprio |
| Trilha de auditoria | sim | não | não | não |
| Finanças dos sócios | só com permissão própria | não | não | não |

Onde está **nunca**, é regra de produto: o custo do fornecedor sustenta a
negociação de reajuste, e a margem revelada ao vendedor muda o que ele aceita
conceder.

### Conflito assumido: comissionar sobre lucro revela o lucro

A comissão de consultas é percentual do lucro, e o vendedor vê a própria comissão.
De R$ 38,59 ele chega a R$ 385,90 de lucro, e daí ao custo. As duas linhas da
tabela acima não podem valer ao mesmo tempo.

A decisão é manter a comissão visível: sem ela a simulação de proposta não serve
para decidir desconto, que é para o que ela existe. O que fica fora das telas do
vendedor é o número direto. A dedução exige saber a regra e fazer a conta, o que é
diferente de ler o valor.

Alternativas, se o conflito incomodar: comissionar sobre faturamento com alíquota
menor, ou publicar a alíquota e tratar o lucro como informação aberta ao vendedor.
Ambas mudam contrato.

### Visibilidade das plaquinhas é a exceção deliberada

No módulo de QR, **a equipe inteira enxerga a tiragem inteira**: administração e
vendedor. A casa é pequena, o atendimento não é de carteira fechada, e placa parada
porque o vendedor dela está em campo custa mais que o risco. O controle é a
auditoria, que guarda nome em cada troca de destino.

Cliente e produtor continuam limitados ao que é deles, e é essa linha que impede um
cliente de trocar o destino da placa de outro.

Ver tudo não move comissão: `vendedor_id` só é escrito na primeira venda.

---

## 3. Vocabulário

Código em português sem acento; tela em português correto. O nome na tela muda
conforme quem lê.

| Termo | O que é |
|---|---|
| Catálogo | A tabela de preços do Avalia One. Uma só, editável, sem versionamento. |
| Serviço | Uma consulta vendável, com código imutável e nome comercial da Avalia. |
| Faixa | Degrau de consumo mínimo. Define a coluna de preços da empresa. |
| Plano | O que a empresa contrata: faixa, mensalidade e franquias. |
| Franquia | Consultas de um serviço já inclusas na mensalidade. Conta em unidades. |
| Consumo mínimo | Piso de **cobrança**, não de consumo. |
| Excedente | O que passou da franquia e por isso é cobrado. |
| Competência | Mês de referência do consumo, AAAA-MM. |
| Fatura | A competência fechada, com a cascata congelada. |
| Cobrança | O documento de pagamento gerado da fatura. |
| Piso de preço | Menor preço que paga fornecedor e imposto sem prejuízo. Calculado. |
| Margem | O que sobra depois de imposto, fornecedor e comissão. |
| Adesão | Taxa de entrada, parcelável, rateada meio a meio com o vendedor. |
| Carteira | As empresas de um vendedor. |
| Retenção | Prazo até a resposta do bureau ser apagada. 180 dias. |
| Oferta | Condição de venda de um produto no Gestor: valor, parcelas, entrada. |
| Pedido | Uma venda parcelada do Gestor, do fechamento à última parcela. |
| Repasse | O que a plataforma devolve ao produtor ou ao vendedor. |
| Plaquinha | Placa física de QR e NFC com destino editável. |
| Tiragem | Uma campanha de plaquinhas geradas juntas. |
| Aporte | Dinheiro que um sócio põe na empresa. Não é receita. |

### Regras de nomenclatura

- Valor interno nunca aparece na tela. `liquidado`, `pendente` e `sucesso` são
  chaves de banco; a tradução vive em `App\Support\Rotulos`, em um lugar só. Ação
  nova sem rótulo derruba a suíte.
- Unidade de medida é vocabulário: o banco guarda espera em milissegundos porque é
  o que o fornecedor devolve; a tela mostra segundos.
- Nada de palavra de quem escreve o sistema na tela de quem opera o negócio. Numa
  tela técnica de administração, termo técnico é correto.
- A rota não acompanha a troca de marca. `/empresas`, `/cobranca` e os nomes de
  classe (`Produto360`, `Lancamento360`) ficam como estão: URL trocada é link
  quebrado no bolso de quem já recebeu o antigo.
- **Nenhum travessão** em código, tela, dado, documento ou commit.

---

## 4. Avalia One: consultas de crédito

### Jornada

1. O vendedor fecha a venda e entrega os dados à administração.
2. A administração cria a empresa, vincula o vendedor, configura o plano e a adesão.
3. A plataforma cadastra o cliente no Asaas e cria a cobrança.
4. O cliente recebe convite de acesso e os documentos de aceite.
5. O cliente consulta e acompanha consumo, franquia e excedente.
6. No fechamento, a fatura é calculada e a cobrança emitida com vencimento dia 10.
7. Webhooks do Asaas atualizam pagamento, atraso e inadimplência.
8. O ciclo seguinte é liberado conforme o estado financeiro.

### Parâmetros comerciais

Fonte única. Provisórios até homologação; homologados, passam a viver no catálogo.

| Parâmetro | Valor | Observação |
|---|---|---|
| Mensalidade | R$ 79,90 | Fixa, consumindo ou não. |
| Faixas de consumo mínimo | sem mínimo, 75, 200, 500, 900, 1.500, 5.000 | Define o preço unitário de todos os serviços. |
| Consumo mínimo negociado | valor livre | Independente da faixa de preço contratada. |
| Taxa de adesão | livre, definida pelo vendedor | Parcelável e isentável. |
| Rateio da adesão | 50% vendedor, 50% Avalia | Isentar zera os dois. |
| Comissão | 10% do **lucro**, ajustável por vendedor, teto 50% | Padrão da casa. |
| Vencimento | dia 10 | Data de calendário, igual para todos. |
| Bloqueio por atraso | 10 dias após o vencimento | Na prática, dia 20. |
| Imposto | 13,50% | Confirmado em 05/08/2026. Incide sobre a nota cheia. |
| Retenção da resposta | 180 dias | Metadados e auditoria são permanentes. |

Todo valor é inteiro em centavos, do banco até a tela. Float não entra em cálculo,
armazenamento nem exibição.

### Cálculo mensal

    consumo_bruto     = consultas concluídas com sucesso, pelo preço congelado
    consumo_excedente = consumo_bruto − o que a franquia cobriu
    valor_de_consumo  = max(consumo_minimo, consumo_excedente)
    fatura            = mensalidade + valor_de_consumo

A franquia é medida **por serviço e em quantidade**, antes de apurar o excedente.
Sobre a soma em reais, um serviço barato cobriria um caro. Consulta que falhou não
ocupa vaga de franquia e não é cobrada.

### Margem e piso

    imposto  = preço × alíquota
    lucro    = preço − imposto − custo do fornecedor
    comissão = pct × lucro
    margem   = lucro − comissão
    piso     = menor preço em centavos que cobre custo e imposto

A ordem importa: o imposto incide sobre a nota cheia e sai primeiro; o custo do
fornecedor sai em seguida.

Duas consequências de comissionar sobre lucro:

- a Avalia fica com `1 − pct` do lucro, e com 10% isso é 90%. **Com percentual
  negociado, muda**: a afirmação "sempre 90%" vale apenas na alíquota padrão;
- o piso não depende da comissão, porque no piso o lucro é zero.

O piso é **calculado, nunca cadastrado**, e preço abaixo dele é recusado na
gravação. `Margem::precoAlvoCents` aplica a fórmula contínua e depois busca em
centavos, porque o imposto arredonda ao centavo: para custo de R$ 0,85 a 13,50% o
piso é **R$ 0,98**, e não os R$ 0,99 que a fórmula contínua sugeriria.

### A margem alvo é uma escada

A margem alvo vale inteira na faixa sem mínimo e cede um degrau a cada faixa
seguinte. Dizer a política pelo lado da margem, e não do desconto, corrige um
defeito real: desconto percentual igual para todos os serviços afunda o serviço
barato, porque o custo do fornecedor é fixo.

O reajuste ao alvo **só sobe** e **nunca roda sozinho**. O alvo é o mínimo que a
casa aceita, não a média que ela persegue. Cada preço alterado entra na trilha com
origem, destino e alvo.

Salvar parâmetro não altera preço nenhum. São duas decisões, e a tela diz isso.

Custo em branco significa **não cadastrado**, diferente de zero: sem o dado, não se
exibe margem nem piso.

### Catálogo

Único e editável, com auditoria de toda alteração. A edição é **linha a linha**, na
página do serviço, e não numa matriz de 301 campos. A matriz é de leitura, com um
botão de edição por linha. Preço abaixo do piso recusa o lote inteiro.

**Não há congelamento do catálogo, e isso é decisão.** O que impede um reajuste de
hoje de alterar cobrança de ontem é cada consulta e cada fatura gravarem preço e
custo **no momento da emissão**. Quem cobra guarda o próprio valor; o catálogo
responde apenas quanto custa hoje.

Essa regra é o alicerce do faturamento inteiro.

### Família suprimida

Os serviços veiculares estão precificados e sem contrato fechado, então o custo é
estimativa. A categoria fica **travada**: a aba não navega, o filtro não abre nem
digitado na URL, e nenhuma linha veicular chega à matriz. O serviço continua
visível na lista, com cadeado, e sua página aceita preço e custo.

### Execução da consulta

Antes de chamar o fornecedor, grava consulta com situação processando, cliente,
vendedor, operador, origem, serviço, documento, competência, preço e custo.

- **Sucesso**: marca ok, salva resposta normalizada, contabiliza consumo.
- **Erro**: marca erro, não cobra, não gera comissão, registra motivo técnico seguro.
- **Queda ou timeout**: mantém registro rastreável para reconciliação.

Custo do fornecedor em consulta que falhou é absorvido pela Avalia.

A finalidade é a declarada no aceite dos termos, gravada automaticamente; ninguém a
digita, porque campo digitado a cada clique vira formalidade vazia. O responsável é
quem está logado: o operador com nome, ou a conta master.

### Conectores

Existem em `app/Services/Conectores`: `ConectorSerasa`, `ConectorBoaVista`,
`ConectorSimulado` e `EscolherConector`, sobre o contrato `ConectorBureau`.

O que falta é credencial e homologação, não código.

O laudo canônico tem ordem fixa, que é a do mercado: resumo de decisão no topo
(score sempre com o modelo dele, porque modelos diferentes não se comparam), depois
identidade, depois as restrições da mais grave para a menos, e por fim o contexto de
consultas recentes.

Bloco que não veio aparece como **não incluído**, nunca como zero fingindo dado.

`EscolherConector` roteia **por serviço**: cada linha do catálogo declara de quem
ela vem, e trocar de fornecedor em um serviço é cadastro, e não publicação. A
escolha global só vale para serviço sem fornecedor declarado. Serviço apontando
para bureau desligado cai no comportamento geral em vez de falhar, porque desligar
uma conexão é ação de emergência e emergência não pode derrubar o catálogo junto.

**Em produção, o simulado não responde.** A cascata terminava nele, e sem
credencial ativa a empresa recebia laudo fabricado, era cobrada pelo preço cheio e
a tela ainda filtrava a palavra "simulado" da linha de bases. Hoje a consulta é
recusada com mensagem que diz o que fazer, antes de qualquer cobrança. A única
saída é `services.bureau.conector` apontar para o simulado de propósito, que é
decisão de instalação e o que a homologação usa.

### Situação da conta

| Situação | Login | Consulta |
|---|---|---|
| Ativo | Permitido | Permitida |
| Inadimplente | Permitido para regularizar | Bloqueada |
| Bloqueado | Conforme decisão administrativa | Bloqueada |
| Inativo | Bloqueado | Bloqueada |

| Dia | Evento |
|---|---|
| 10 | Vencimento. |
| 11 a 19 | Em atraso. Consultas liberadas; o cliente é avisado. |
| 20 | Bloqueio das consultas. Login aberto para ver a fatura e regularizar. |
| Liquidação | Consultas liberadas no mesmo ciclo. |

O bloqueio existe para forçar o pagamento, não para punir: fecha a consulta e
mantém o acesso à fatura. Cliente que não vê o que deve não tem como pagar.

A liquidação é idempotente, libera a comissão daquela fatura e reativa a empresa
apenas se não restar outra fatura pendente ou vencida.

---

## 5. Avalia Gestor: venda parcelada

Produto distinto, com conta própria e regra de dinheiro própria. O produtor não
consulta score, não recebe fatura da Avalia e não tem o que fazer nas telas de staff
ou de empresa.

### Quem é quem

- **Produtor**: quem vende parcelado. Cadastro é auto-serviço e entra como
  pendente; quem decide se ele pode vender é a aprovação, não o formulário. Sem
  `asaas_wallet_id` não há para onde o split mandar a parte dele.
- **Comprador**: chega por link que o produtor mandou. O checkout é **público e sem
  login**: exigir cadastro antes de mostrar o preço é o jeito mais rápido de perder
  a venda. O que protege é teto por origem e campo armadilha.

### Objetos

| Objeto | O que guarda |
|---|---|
| `Produto360` | O que o produtor vende. As condições ficam na oferta. |
| `Oferta360` | Valor, parcelas e entrada. O slug nasce aqui e não muda quando o título muda. |
| `Pedido360` | Uma venda, com preço, parcelamento e taxa **copiados da oferta**. |
| `Parcela360` | Uma parcela do carnê, ou a entrada quando o número é zero. |
| `Lancamento360` | Linha do razão. Nasce e nunca muda; corrigir é lançar o contrário. |

### Regras de dinheiro

| Parâmetro | Valor |
|---|---|
| Taxa da plataforma | 5% de cada pagamento (`COBRANCA_TAXA_BPS`) |
| Teto de parcelas | 12 |
| Piso por parcela | R$ 100,00 |

O teto existe porque acima dele a inadimplência cresce mais rápido que o ganho, e o
carnê passa a durar mais que a memória da compra. O piso existe porque um ticket de
R$ 600 em doze parcelas de R$ 50 custa mais em cobrança do que traz de receita.

O rateio segue a ordem do provedor, e não a mais intuitiva: do que o cliente pagou
sai primeiro a taxa do provedor, e o split percentual da plataforma incide sobre o
**líquido**. Cobrar sobre o bruto faria o razão divergir do que cai na carteira do
produtor, e a diferença aparece no extrato que ele confere todo mês.

As quatro partes de um pagamento somam **zero**: o bruto entra positivo e as três
saídas saem negativas. Conferir um pedido é somar a coluna.

A sobra da divisão fica com o produtor: um centavo a mais na parte maior nunca gerou
reclamação; um centavo a menos no repasse, sim.

**A parcela só nasce depois de `efetivado`**, que exige contrato assinado e entrada
confirmada (`Pedido360::podeParcelar()`). Emitir boleto antes disso é cobrar por um
contrato que ninguém assinou.

**O razão do Gestor é imutável**: `lancamentos_360` não tem `updated_at`, e estorno
é lançamento de sinal contrário. Saldo não é coluna em lugar nenhum, é a soma da
tabela. Essa é a regra que a `PLANO-FINANCEIRO.md` generaliza para o resto do
sistema, onde resultado ainda mora como coluna em `faturas`.

### Situação da parcela

A situação segue **o que o provedor informa por webhook**, e não o calendário.
Parcela vence no papel, mas só vira vencida quando o provedor diz que venceu sem
pagamento. Calendário local decidindo dinheiro é como nasce divergência com o
extrato.

### Pré-cadastro

`InteressadoCobranca` guarda documento e WhatsApp **cifrados pelo cast**: quem lê o
banco direto vê texto cifrado. Isso custa a busca por documento, que não existe
nessa tela, e paga com backup que não entrega dado pessoal.

---

## 6. QR dinâmico: plaquinhas e encurtador

O código impresso é um endereço permanente da Avalia, e não o link do cliente. O que
muda quando o cliente troca de site é o **destino**, nunca o **código**. É isso que
faz a placa no balcão continuar valendo.

### Produto

| Parâmetro | Valor |
|---|---|
| Preço da placa | R$ 89,90 (QR e NFC juntos) |
| Custo unitário | R$ 5,50 |
| Renovação | R$ 49,90 |
| Avulso mensal | R$ 19,90 |
| Validade | 12 meses |
| Carência após vencer | 30 dias |
| Aviso antes do vencimento | 15 dias, um só |
| Teto de uma tiragem | 1.000 |

A carência existe porque quem esquece de pagar quase nunca está desistindo, e
derrubar a loja do cliente no dia seguinte ao vencimento transforma atraso de boleto
em cliente perdido.

### Ciclo de vida

`em_branco` → `ativa` → `suspensa` ou `baixada`. **Vencida não é situação**: é
conta de data, calculada em `Etiqueta::estado()`. Gravar "vencida" exigiria alguém
virando a chave todo dia, e no dia em que falhasse a placa estaria ativa no banco e
morta na data.

Não existe exclusão. Placa quebrada ou cliente que saiu vira `baixada`, e o código
nunca volta ao sorteio: reciclar mandaria a freguesia do cliente antigo para a loja
de um estranho.

### Três conceitos, três colunas

Confundi-los creditava toda venda a quem operou a impressora.

| Coluna | Responde |
|---|---|
| `staff_id` | Quem gerou a tiragem (produção) |
| `dono_tipo` / `dono_id` | De quem é o código (permissão) |
| `vendedor_id` | Quem vendeu (repasse) |

### Reparte de cada placa

    liquido  = venda − custo
    comissão = 25% do líquido, para quem vendeu
    lucro    = líquido − comissão
    split    = lucro dividido entre os sócios

A comissão incide sobre o **líquido**, pelo mesmo motivo do Avalia One: sobre
faturamento, uma venda que rende e uma que sangra pagariam igual.

**Não geram comissão:** venda de sócio, porque ele já recebe pela divisão; e venda
sem vendedor, quando o cliente apontou o próprio código, porque não houve venda de
ninguém. Comissionar uma venda órfã criaria dinheiro sem destinatário.

**A comissão arredonda por venda; a divisão entre sócios roda uma vez sobre o mês.**
A assimetria é deliberada: o vendedor confere placa a placa, então cada linha tem de
fechar; dividir a sobra placa a placa daria o centavo ímpar sempre ao primeiro do
config, e em cem placas isso vira cinquenta centavos de viés.

### Cancelar venda

Apontar e vender são o mesmo clique, e nem toda placa em campo foi vendida: a da
própria Avalia, a de demonstração, a de teste, a cadastrada por engano.

Cancelar tira **só o fato comercial**. O destino, o código e o histórico ficam, e a
placa continua redirecionando. Sem `vence_em` ela não vence mais, que é o certo para
placa da casa.

Não é o mesmo que suspender. Suspender apaga o redirecionamento e mantém a venda;
cancelar mantém o redirecionamento e apaga a venda. São duas perguntas: a placa
funciona, e alguém pagou por ela.

Só a administração cancela, porque cancelar venda de mês fechado muda a comissão
daquele mês.

### Produção

A numeração **corre entre campanhas**: quem está na bancada diz "a 143", e não "a 3
da campanha Floripa". O pacote sai em ZIP, no formato escolhido, com SVG no padrão,
a 30×30mm.

O desenho é determinado pelo código: a tiragem de 2026 pode ser refeita idêntica em
2031 a partir da mesma lista. Nada é guardado como imagem.

O QR é desenhado **no navegador**, e não no servidor: a hospedagem não tem composer
para instalar biblioteca PHP, e de quebra isso tira cem renderizações do CPU
compartilhado.

### Encurtador

Atrás da mesma porta, com a mesma regra de dono. Existe porque endereço de campanha
com parâmetros de origem não cabe nos ~140 bytes úteis de uma tag NTAG213.

---

## 7. Serviços de software

Apresentados no site e fechados por projeto, fora do sistema: automação e integração
(RPA), inteligência de mercado, cobranças, URA de WhatsApp, gestão e
desenvolvimento sob medida.

Não há módulo de projeto, proposta, horas ou entrega na aplicação. **Fora do escopo
do software hoje**, e registrado aqui para o documento não sugerir que existe.

O conteúdo do site (blog e páginas de serviço) é catálogo de aquisição, em
`config/softwares.php`, `config/servicos-digitais.php` e `config/blog.php`.

---

## 8. Aquisição: site, leads e interessados

### Site institucional

Vive no tema claro e só nele, e por isso não leva interruptor de tema nem escreve
variante `dark:`. O preto que ele usa é superfície escolhida dentro do tema claro:
bastaria o navegador lembrar do escuro do CRM para o site abrir com metade de cada
tema. A exceção é reconhecida por caminho no teste.

### Pedidos de contato

O formulário grava o interessado no banco (nome, empresa, telefone, e-mail, faixa de
funcionários e origem), em vez de abrir o WhatsApp com dado pessoal na URL. A fila
aparece no painel da administração; **o vendedor não vê nem atende**, porque lead
ainda não tem carteira e distribuí-lo é decisão da administração.

### Base de leads

Base própria (`leads`), e não uma situação a mais em `clientes`: lead não tem plano,
não tem fatura, não tem senha, e enfiá-lo na tabela do contratante deixaria metade
das colunas vazia e um filtro esquecido bastaria para ele aparecer num fechamento.

A tela é de **filtro antes de ser de busca**: o recorte inteiro vive na barra de
endereços, então a tela vira link, a exportação leva o que está nela, e a ação em
lote age sobre o filtro inteiro e não sobre os cinquenta da página aberta.

A distribuição mora em tabela de ligação com data e autor, e não numa coluna
`vendedor_id`: o mesmo lead pode ser trabalhado por mais de um vendedor, e "está com
ele desde quando" é a pergunta que se faz quando o lead não andou.

### Funil

Seis estágios: **novo**, **em atendimento**, **agendado**, **recusado**, **não
atender**, **virou cliente**. Agendado é o único com data, e a data é o motivo de o
estágio existir.

O vendedor alcança quatro. **Não atender** é decisão da casa; **virou cliente** é
marcado pela conversão, quando o cadastro é gravado de verdade — deixá-lo na mão de
quem prospecta criaria lead convertido sem empresa do outro lado.

**Um lead vira cliente quando o vendedor converte, e a conversão exige CNPJ válido e
e-mail.** São os dois campos que ninguém inventa: o CNPJ é quem vai ser cobrado, o
e-mail é o acesso. O dígito verificador é conferido aqui porque documento inventado
reaparece na primeira cobrança, quando já é problema financeiro.

Converter não cria um segundo caminho de cadastro: abre o mesmo formulário de
empresa, com os campos copiados e o vínculo conferido no servidor.

---

## 9. Dinheiro: as regras transversais

### Congelamento na emissão

Consulta, fatura, pedido e plaquinha gravam preço e custo **no momento em que
acontecem**. Reajuste de hoje não reescreve cobrança de ontem. É a regra mais
importante deste documento, e vale nos quatro produtos.

Exceção registrada: a migration de 29/09/2026 realinhou preço e custo das plaquinhas
já cadastradas, porque nenhuma tinha sido vendida — eram dado de montagem. Fora esse
caso, correção de valor passa pela tela, com trilha.

### As três remunerações, lado a lado

| | Avalia One | Avalia Gestor | Plaquinhas |
|---|---|---|---|
| Quem recebe | Vendedor da carteira | Produtor | Vendedor |
| Base | Lucro do mês | Pagamento recebido | Líquido da venda |
| Percentual | 10%, por vendedor, teto 50% | 95% (taxa da casa é 5%) | 25% |
| Quando é elegível | Liquidação da fatura | Liquidação da parcela | Venda registrada |
| Onde o percentual mora | `staff.comissao_pct` | `config/cobranca.php` | `config/etiquetas.php` |

O percentual em `staff.comissao_pct` **vale só para consultas**. A tela da equipe diz
"Comissão de consultas" por isso.

Cada fatura guarda o percentual usado na emissão: renegociar hoje não reescreve
competência fechada.

### Divisão entre os sócios

O lucro das plaquinhas é dividido em partes iguais entre os sócios listados em
`ETIQUETAS_SOCIOS`. A **ordem do config é a ordem da divisão**, e é ela que mantém o
centavo ímpar sempre na mesma pessoa.

Sócio configurado sem conta na equipe aparece como aviso na tela: lista errada em
silêncio vira repasse errado.

**Pendência:** não existe regra escrita para a divisão do resultado do Avalia One nem
do Gestor entre os sócios. Hoje só as plaquinhas têm split definido.

### O que "lucro" não inclui

O lucro calculado em qualquer produto é **lucro do produto**, antes de custo fixo e
imposto sobre o resultado. Hospedagem, domínio, ferramenta e trabalho não são por
unidade vendida e não entram. Quem ler esses números como resultado da empresa vai
superestimar.

É exatamente essa lacuna que a seção 10 fecha.

---

## 10. Gestão financeira e aportes dos sócios

**Implementado em 29/09/2026.** Módulo interno com permissão própria
(`staff.pode_socios`, middleware `socios`): não é visível a administrador comum, e
fica fora dos portais de cliente, vendedor e produtor. A permissão nasce negada
inclusive para quem já é admin, e o item some do menu de quem não a tem.

### Por que partidas dobradas

O método não é preciosismo contábil: é o que torna **estruturais** as três
confusões que custam dinheiro numa sociedade de dois. Com valor único numa linha
só, cada uma depende de quem digita lembrar da regra. Com duas pernas que somam
zero, viram invariante, e o lançamento que não fecha não grava.

Quem lança escolhe a **natureza**, nunca a conta. Escolher conta na tela é
exatamente onde o erro mora, e a tradução natureza → pernas vive num lugar só, em
`App\Actions\Socios\RegistrarLancamento`.

Corrigir é **estornar**, que lança as mesmas pernas com o sinal trocado e amarra
as duas linhas. A competência do estorno é a de hoje, e não a do original: mês
fechado continua com o número que teve, e a correção pertence ao mês em que foi
decidida.

### O que ele responde

Quanto há em caixa, quanto cada sócio pôs, quanto a empresa deve a cada um, quanto
custa operar e o que sobra de verdade depois de tudo.

### Naturezas de lançamento

Cada uma é distinta, e tratá-las como sinônimo é o erro que o módulo existe para
evitar:

| Natureza | Efeito |
|---|---|
| Aporte de capital | Aumenta caixa e saldo do sócio. **Não é receita.** |
| Empréstimo de sócio | Aumenta caixa e cria obrigação da empresa. |
| Despesa paga pelo sócio | Gera despesa e valor a reembolsar. |
| Reembolso | Liquida a obrigação e reduz caixa. **Não é segunda despesa.** |
| Receita | Reconhecida por competência, vinculada à origem. |
| Despesa | Operacional, por categoria e competência. |
| Transferência | Entre contas da empresa. **Não muda o resultado.** |
| Retirada | Saída para o sócio, distinta de reembolso. |
| Distribuição de resultado | Depende de decisão, não de cálculo automático. |

### Campos mínimos

Identificador, natureza, valor em centavos, moeda, competência, vencimento,
pagamento, conta de origem e destino, sócio ou pagador, contraparte, categoria,
documento de origem, comprovante, situação, responsável e trilha.

Vínculo de origem e unicidade integram fatura, recebimento e comissão **sem duplicar
receita já reconhecida**.

### Custos a inventariar

Hospedagem, banco, armazenamento, e-mail, domínio, backup, serviços de IA, bureaus,
Asaas, impostos, comissões, demonstrações e trabalho contratado. Para cada um:
fonte, moeda, competência, periodicidade, fixo ou variável, unidade, volume
incluído, excedente, estimativa e realizado.

**Contrato de fornecedor com mensalidade fixa muda a natureza do custo**: o custo
unitário do catálogo vira rateio estimado, e a margem real do mês depende do volume.
Ratear a mensalidade pelas consultas informa custo atribuído, mas não cria despesa
nova. Com zero consultas o custo fixo continua existindo, e não se divide por zero.

### O que falta

Comprovante anexado, conciliação contra extrato bancário, orçamento e projeção de
caixa. A ordem em que isso entra está na `PLANO-FINANCEIRO.md`.

### A ligação com o financeiro, e por que ela tem uma direção só

**Fatura liquidada vira receita no caixa dos sócios, automaticamente.** Acontece
dentro da mesma transação da liquidação: fatura marcada como paga sem a linha
correspondente no razão é uma divergência que só aparece na conferência do mês, e
aí ninguém lembra qual das duas está certa.

Não duplica por construção: o par `origem_tipo`/`origem_id` é único no banco,
então rotina repetida, webhook duplicado ou competência reprocessada encontram o
trabalho feito. A conta de receita nasce sob demanda, porque liquidação não pode
falhar por falta de uma linha do plano de contas.

**Sócios não alimenta financeiro.** Aporte e retirada não têm o que fazer nas
telas de cliente e de vendedor.

O risco que sobra é humano: alguém lançar à mão a receita da mesma fatura que já
entrou sozinha. A proteção é regra e não aviso — receita digitada é recusada em
competência que já tem receita automática, porque ela existe para o que **não**
tem origem no sistema, como um projeto de software fechado por fora.

Falta ainda a ligação de parcela do Gestor e de venda de plaquinha, e o
reconhecimento do custo (comissão devida, custo do fornecedor), que hoje só entra
quando alguém lança a despesa.

### Margem do produto não se soma ao resultado da empresa

A Visão geral mostra **lucro do produto**: por unidade vendida, antes de custo
fixo. Os Sócios mostram o resultado da empresa. Não são parcelas da mesma conta,
são a mesma receita vista de dois recortes, e somá-los dá número errado.

BI, integração bancária e automação fiscal entram por necessidade demonstrada. Não
prometer emissão de nota fiscal sem integração e regra fiscal definidas.

---

## 11. Diretivas

Hoje **um único parâmetro é configurável pela tela**: a alíquota de imposto. Todo o
resto é constante em código, e mudar exige publicar versão.

| Regra | Valor | Onde |
|---|---|---|
| Dia do vencimento | 10 | `Fatura::DIA_VENCIMENTO` |
| Tolerância até bloquear | 10 dias | `Fatura::DIAS_ATE_BLOQUEIO` |
| Aviso antes do vencimento | 3 dias | `AvisarVencimentoProximo` |
| Retenção da resposta | 180 dias | `Consulta::DIAS_DE_RETENCAO` |
| Teto diário por empresa | 500 | `Consulta::LIMITE_DIARIO` |
| Teto de demonstrações | 10/dia | `Consulta::LIMITE_DIARIO_DEMONSTRACAO` |
| Janela anti cobrança dupla | 120 s | `Consulta::SEGUNDOS_SEM_REPETIR` |
| Validade do convite | 48 h | `Convite::HORAS_DE_VALIDADE` |
| Comissão padrão e teto | 10% e 50% | `Comissao` |
| Alerta de cliente parado | 30 dias | `Alertas::DIAS_SEM_CONSULTAR` |
| Preço e custo da plaquinha | 89,90 e 5,50 | `config/etiquetas.php` |
| Taxa e parcelamento do Gestor | 5%, 12, R$ 100 | `config/cobranca.php` |

Para uma operação que negocia contrato a contrato, isso é rígido demais.

Um cadastro de Diretivas com vigência, autor e motivo é pendência, não desenho
fechado.

**Retenção da resposta e validade do convite não são preferência comercial**: são
compromisso legal e de segurança, e entram como leitura ou com trava de faixa.

Alterar Diretiva não recalcula fatura histórica, e exceção comercial não desativa
trava de segurança.

---

## 12. Segurança, LGPD e auditoria

- Senha com hashing do Laravel; sessão regenerada e revogável por `sessao_versao`.
- Login limitado por conta e por origem, com castigo progressivo em tabela, não em
  cache: precisa sobreviver a restart e virar evidência.
- Nunca registrar senha, token, autorização, cookie ou chave de API em log.
- `avalia:ambiente` roda no fim do deploy e impede a versão de subir com depurador
  ligado, cookie fora de HTTPS ou envio de e-mail desligado.
- Expurgo da resposta do bureau conforme a retenção, preservando metadados fiscais.
- Auditoria não pode impedir a operação principal se a gravação falhar.

### Convite de acesso

Ninguém digita a senha de outra pessoa nem a conhece. A conta nasce com senha
aleatória e um convite leva o link para o dono definir a dele.

O link morre duas vezes: pelo prazo, com assinatura de 48 horas; e pelo uso, com um
carimbo derivado do hash da senha atual, que invalida todo link anterior no momento
em que uma senha nova é definida.

### Operadores

Cada pessoa que consulta em nome da empresa tem conta própria. É a resposta de LGPD
para "quem consultou este documento". Desativar um operador derruba a sessão dele na
hora, sem tocar na conta master. Sem "manter conectado" para operador, porque o
cookie restauraria a sessão sem a marca da pessoa.

### Trilha

A tela de Auditoria traduz tudo: ação, entidade e detalhes têm nome de negócio em
`Rotulos`, e um teste derruba a suíte quando alguém registra ação nova sem rótulo.

O registro congela o nome da entidade no momento em que é escrito, então a linha se
explica sozinha mesmo depois de a entidade ser removida. **Nome de consumidor
consultado nunca entra no congelado**: a trilha vive para sempre e o dado pessoal
tem prazo.

Resumo encadeado denuncia alteração posterior. Ele **não torna a trilha imutável**
contra quem pode reescrever a cadeia inteira, e apagar o último registro não quebra
elo nenhum.

### A checagem de raiz pergunta ao disco

A versão anterior decidia pela string do `APP_URL`, então imprimia `ok` mesmo com a
raiz errada: dizia uma coisa e verificava outra. Agora confere se o `.env` está
dentro do diretório servido, que é o que prova o risco. Conferência por caminho, e
não por requisição, porque o comando roda no servidor e não pode depender de a
aplicação responder a si mesma durante a publicação.

---

## 13. Operação

Hospedagem compartilhada da Hostinger, MySQL, sem npm e sem composer no servidor.
`public/build` é versionado porque o front precisa chegar pronto. O resto está na
`DEPLOY.md`.

---

## 14. Decisões pendentes

### Comercial

- **Contrato do vendedor de consultas.** A comissão saiu de 10% do consumo para 10%
  do lucro em 05/08/2026, e o contrato precisa dizer isso. A versão anterior deste
  documento trazia um exemplo de R$ 30 que é da regra antiga; pela regra vigente o
  mesmo caso dá cerca de R$ 84,76. **Conferir o que foi assinado.**
- **Divisão do resultado** do Avalia One e do Gestor entre os sócios.
- **Virada de plano no meio do mês**: proporcional, faixa nova pelo mês inteiro, ou
  antiga com a nova valendo no mês seguinte.
- **Vigência não tem efeito.** Está gravada e ninguém verifica nem avisa renovação.
- **Carência especial** é regra escrita sem código.
- **Cancelamento de contrato** não existe: inativo não encerra vigência, não emite
  fatura final e não avisa o vendedor.
- **Reajuste anual**: contrato vigente acompanha ou fica congelado até renovar.
- Homologar os preços de referência e a margem sobre o custo do fornecedor.
- Definir franquia por serviço e faixa, e o que entra no catálogo inicial.

### Cobrança

- **Pagamento parcial, duplicidade e renegociação.**
- **Juros, multa, desconto e tolerância**: hoje não há nenhum dos quatro.
- **Chargeback e Pix devolvido** depois de a comissão ter sido liberada.
- **Consulta duplicada**: mesmo documento e serviço dois minutos depois, cobra ou
  reaproveita.
- **Quem altera a adesão** depois do contrato assinado, e com qual aprovação.
- **Desligamento de vendedor**: a carteira fica com a Avalia e as comissões futuras
  cessam, o que está escrito e não existe em código.

### Técnico, por ordem de risco

1. **Isolamento entre empresas e carteiras**, testado e não presumido.
2. **Conciliação** entre o que o provedor recebeu e o que a Avalia registrou.
3. **Consulta duplicada por clique repetido.**
4. **Pipeline que bloqueia merge** com teste de negócio falhando.
5. **Diretivas configuráveis**, para negociar sem publicar versão.
6. **Fila de e-mail que falhou**: hoje registra em log e não avisa ninguém.
7. **Exportação e expurgo por titular**, antes do primeiro pedido real de LGPD.

Saíram desta lista por já estarem resolvidos, e não por terem sido despriorizados:
**estorno com reversão de comissão** (`EstornarLiquidacao` desfaz a liquidação e
recolhe a comissão liberada) e **roteamento de fornecedor por serviço**. A versão
anterior deste documento os listava como pendentes.

### Deliberadamente fora do escopo

Cada um resolve problema que a operação ainda não tem e cobra manutenção desde o
primeiro dia: dupla aprovação para baixa manual, criptografia por coluna, expiração
periódica de senha, fallback entre fornecedores, fila de processamento, ambiente de
homologação separado, antivírus em upload, BI com coorte e tendência.

**Módulo de projeto para os serviços de software** também fica de fora: é trabalho
fechado por contrato, e não há volume que justifique tela.
