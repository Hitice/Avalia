# Documento de Produto: Avalia

## 1. Visão do produto

A Avalia é uma plataforma B2B de revenda de consultas de crédito. A operação
vende planos a empresas clientes, disponibiliza consultas conforme o plano
contratado e centraliza faturamento, cobrança, repasse a vendedores, atendimento
e indicadores operacionais.

A plataforma consome bases de crédito. Ela não expõe uma API pública para seus
clientes. O cliente não visualiza qual bureau foi usado em uma consulta; ele vê
somente o serviço contratado, o resultado permitido e o seu consumo.

## 2. Objetivos

- Permitir que administração e vendedores criem e gerenciem carteiras de empresas clientes.
- Precificar planos com mensalidade, franquia, excedentes e taxa de adesão.
- Executar consultas com rastreabilidade, controle de consumo e retenção de dados.
- Automatizar cobrança recorrente e inadimplência por meio do Asaas.
- Dar a cada papel um painel restrito às informações necessárias.
- Permitir gestão de campanhas, documentos de implementação e inteligência de negócio.

## 3. Papéis e permissões

| Papel | Responsabilidades |
| --- | --- |
| Administrador | Configura catálogo, planos, serviços, equipe, documentos e campanhas; acompanha empresas, faturas e auditoria. A permissão financeira é concedida à parte. |
| Vendedor | Cadastra e mantém as empresas da própria carteira, acompanha o consumo delas e a própria comissão. |
| Empresa cliente | Consulta os serviços contratados, acompanha plano, franquia, consumo e faturas, e aceita os documentos exigidos. |

As contas de operação (administrador e vendedor) ficam na tabela `staff`;
empresas ficam em `clientes`. As tabelas, guards, políticas e sessões são
separados. Um papel nunca obtém acesso a rotas, registros ou indicadores de
outro papel sem permissão explícita.

### A separação é física, e não condicional

O vendedor tem telas próprias, e não as telas de administração com campos
escondidos. Custo do fornecedor, lucro e margem não passam por elas.

Reaproveitar a mesma tela com condicionais deixaria cada campo novo a um `@if`
de distância de vazar. Há teste afirmando que as palavras custo, lucro e margem
não aparecem na carteira nem no portal do cliente.

A carteira exibida é sempre a de quem está autenticado: não existe parâmetro de
rota que escolha o vendedor, então não há endereço que peça a de outro. Trocar o
papel de alguém revoga as sessões abertas dessa conta, senão ela continuaria com
a permissão antiga até o cookie expirar.

### Permissão financeira

Confirmar pagamento libera comissão sem que dinheiro tenha entrado, e fechar
competência emite cobrança. Não é o mesmo tipo de decisão que renomear um
serviço, e por isso não depende da mesma permissão.

Ela **nasce negada** e é concedida uma a uma no cadastro da equipe. O
superusuário passa por cima, e o vendedor nunca recebe, mesmo com a marca ligada
por engano.

Faltam ainda os papéis **comercial** e **operação**, previstos e não construídos:
hoje quem cuida de cadastro tem o mesmo alcance de quem mexe no catálogo.

### O que cada papel vê

| Informação | Administrador | Vendedor | Empresa |
| --- | --- | --- | --- |
| Preço de venda | sim | somente da carteira dele | do que contratou |
| Custo do fornecedor | sim | **nunca** | **nunca** |
| Margem e lucro | sim | **nunca** | **nunca** |
| Comissão | de todos | a própria | **nunca** |
| Fatura | de todas | da carteira dele | as próprias |
| Consulta e resultado | metadados | metadados da carteira | as próprias, íntegras |
| Trilha de auditoria | sim | não | não |

Onde está escrito **nunca**, é regra de produto e não de conveniência: o custo
do fornecedor é o que sustenta a negociação de reajuste, e a margem revelada ao
vendedor muda o que ele aceita conceder.

### Conflito assumido: comissionar sobre lucro revela o lucro

A comissão é 10% do lucro, e o vendedor vê a própria comissão. De R$ 38,59 ele
chega a R$ 385,90 de lucro, e daí ao custo do fornecedor. As duas regras da
tabela acima não podem valer ao mesmo tempo.

A decisão é manter a comissão visível. Ela aparece na carteira desde o primeiro
fechamento, e sem ela a simulação de proposta não serve para decidir desconto,
que é para o que ela existe. O que continua fora das telas do vendedor é o
número direto: custo, imposto, lucro e margem não são impressos nem chegam à
view. A dedução exige que ele saiba a regra e faça a conta, o que é diferente de
ler o valor na tela.

As alternativas, se o conflito incomodar depois, são comissionar sobre
faturamento com alíquota menor, ou publicar a alíquota e tratar o lucro como
informação aberta ao vendedor. Ambas mudam contrato, e nenhuma se resolve na
camada de tela.

### Lacunas conhecidas de visão

- Não existe ficha de cliente para o vendedor, porque a ficha atual carrega custo
  e lucro.
- O superusuário não tem indicação visual de que está usando um acesso que ignora
  permissões.

## 4. Jornada de cliente

1. O vendedor fecha a venda e entrega os dados comerciais à administração.
2. O administrador cria a empresa, vincula o vendedor, configura o plano e registra a taxa de adesão.
3. A plataforma cadastra ou atualiza o cliente no Asaas e cria a cobrança correspondente.
4. O cliente recebe acesso ao portal e documentos de aceite necessários.
5. O cliente realiza consultas e acompanha consumo, franquia e excedentes.
6. No fechamento, a plataforma calcula a fatura e envia boleto com QR Code.
7. Webhooks do Asaas atualizam cobrança, baixa, atraso, reemissão e inadimplência.
8. No ciclo seguinte, o consumo é liberado conforme o estado financeiro da conta.

## 5. Parâmetros comerciais

Esta seção é a fonte única dos valores citados no restante do documento. Todos
são provisórios até homologação comercial e, uma vez homologados, passam a viver
no catálogo versionado, nunca no código.

| Parâmetro | Valor | Observação |
| --- | --- | --- |
| Mensalidade da Avalia | R$ 79,90 | Fixa, cobrada consumindo ou não. |
| Faixas de consumo mínimo | sem mínimo, R$ 75,00, R$ 200,00, R$ 500,00, R$ 900,00, R$ 1.500,00, R$ 5.000,00 | A faixa escolhida define o preço unitário de todos os serviços. |
| Taxa de adesão | valor livre, definido pelo vendedor | Cobre licença de uso, liberação de acesso e implantação. Pode ser parcelada e pode ser isentada pelo vendedor. |
| Rateio da adesão | 50% vendedor, 50% Avalia | Isentar significa nenhum dos dois receber. |
| Comissão recorrente | 10% sobre o LUCRO do mês, ajustável por vendedor | O imposto sai primeiro, o custo do fornecedor em seguida, e o vendedor leva a fatia dele do que sobrar. A administração negocia caso a caso, com teto de 50%. |
| Vencimento da fatura | todo dia 10 | Data fixa de calendário, igual para todos os clientes. |
| Bloqueio por atraso | 10 dias após o vencimento | Na prática, dia 20. Bloqueia consultas; o login continua liberado para regularizar. |
| Vigência do contrato | escolhida pelo vendedor | Sem vigência; 12 meses; 24 meses; ou 3 meses de carência especial para teste, seguidos de 12 ou 24 meses. |
| Imposto sobre a venda | 13,50% | Alíquota apurada da Avalia, confirmada em 05/08/2026. Substitui a estimativa anterior de 8,60%, que era a faixa inicial do Simples. Incide sobre a nota cheia, antes de qualquer outro desconto. |
| Retenção da resposta do bureau | 180 dias | Metadados fiscais e auditoria são preservados. |

Todos os valores financeiros são inteiros em centavos, do banco até a tela. Não
se usa ponto flutuante em nenhuma etapa de cálculo, armazenamento ou exibição.

A mensalidade de R$ 49,00 que aparece nos documentos de referência é histórica do
fornecedor e não substitui a mensalidade da Avalia.

A taxa de adesão substitui e unifica o que antes eram dois parâmetros separados,
taxa de cadastro e taxa de licença: eram a mesma cobrança descrita duas vezes.

## 6. Clientes, planos e consumo

### Cadastro de empresa

O cadastro reúne identificação empresarial, contatos, responsável, vendedor,
dados financeiros, status, plano e histórico. A administração cria a empresa a
partir dos dados fechados pelo vendedor. Um vendedor não altera clientes de
outra carteira.

O contrato de serviço e o aceite são requisito jurídico da ativação. O cadastro
exige CPF/CNPJ, razão social, endereço e situação.

### Plano e preços

Cada plano combina a mensalidade fixa, uma faixa de consumo mínimo, franquia por
quantidade de cada serviço, preços por serviço, regras de excedente, serviços
liberados, condições comerciais, vigência e status. Os valores estão na seção 5.

O preço de tabela é o valor final ao cliente da Avalia. O custo efetivo do
fornecedor é interno, cadastrado separadamente, e nunca é exibido ao cliente ou
ao vendedor.

### Margem e piso de preço

A administração vê a mesma matriz do catálogo sob três visões: preço de venda,
custo do fornecedor e margem. As duas últimas são internas e ficam atrás da mesma
restrição de acesso do catálogo: vendedor não entra.

    imposto  = preço de venda × alíquota
    lucro    = preço de venda − imposto − custo do fornecedor
    comissão = 10% do lucro
    margem   = lucro − comissão
    piso     = custo ÷ (1 − imposto)

A ordem importa. O imposto sai primeiro, porque incide sobre a nota cheia. O
custo do fornecedor sai em seguida, porque é a Avalia que o paga. O que sobra é
lucro, e o vendedor leva 10% dele.

Duas consequências aritméticas de comissionar sobre lucro:

- **a Avalia fica sempre com 90% do lucro**, qualquer que seja o preço praticado;
- **o piso não depende da comissão**, porque no piso o lucro é zero e a comissão
  também. Quando a comissão lia faturamento, ela empurrava o piso para cima.

Margem calculada sem a comissão superestima o resultado em um décimo. É a
diferença entre 53% e 48% numa linha de custo baixo, e é sobre o número menor que
se decide desconto.

O piso não é cadastrado, é calculado, e **preço abaixo dele é recusado na
gravação**. Relatar prejuízo depois do fato não impede ninguém de vender no
negativo.

Preço e custo são editados manualmente por serviço e faixa. A tela usa a visão
de margem apenas para informar o resultado; ela não recalcula nem altera preços
em lote.

Custo em branco significa **custo ainda não cadastrado**, e é diferente de custo
zero: sem o dado, a plataforma não exibe margem nem piso em vez de exibir um
número inventado. A alíquota de imposto vive no catálogo, não no código, porque
muda com o regime tributário e precisa de rastro.

Cada serviço do catálogo tem código, descrição, preço de venda por faixa, custo
interno, franquia, regras de consumo e congelamento por contrato.

### Edição do catálogo

O catálogo é único e editável a qualquer momento pelo administrador com permissão
comercial ou financeira. Toda alteração de preço, custo, franquia ou
disponibilidade gera auditoria.

A edição é **linha a linha**, na página do serviço, e não numa matriz de 301
campos: lá ficam juntos o cadastro, o custo do fornecedor e o preço de cada
faixa, e o operador vê a margem do que digitou antes de salvar. A matriz do
catálogo é de leitura, com um botão de edição por linha. Preço abaixo do piso
recusa o lote inteiro, e não apenas a célula: gravar só o que passou deixaria o
operador achando que salvou tudo.

A disponibilidade do serviço é um interruptor que grava no próprio clique. A
alíquota de imposto fica em página separada porque é um parâmetro financeiro
global; ela não altera preços automaticamente.

Não há congelamento do catálogo, e isso é decisão consciente. O que impede um
reajuste de hoje de alterar cobrança de ontem é cada consulta e cada fatura
gravarem preço e custo **no momento da emissão** (seções 7 e 8). Quem cobra
guarda o próprio valor; o catálogo responde apenas quanto custa hoje.

Essa regra é o alicerce de todo o faturamento: se uma consulta for gravada sem o
preço da época, ou se a fatura passar a ler o catálogo em vez do valor congelado
no fechamento, um reajuste passa a reescrever o passado silenciosamente.

Preço é sempre valor numérico por faixa. Nenhuma célula do catálogo aceita texto
monetário, intervalo ou dois valores no mesmo campo.

### Origem dos preços de referência

Os preços de partida estão nos anexos A e B, transcritos dos PDFs em `temp/`.
Eles precisam ser homologados pelo time comercial antes da ativação e então
cadastrados no catálogo, em centavos.

O diretório `temp/` é referência documental apenas: guarda a tabela de crédito, a
tabela veicular e o contrato de referência do fornecedor, em PDF, usados na
transição. Não é parte da aplicação, não é versionado e não deve ser lido pelo
código em produção.

Os nomes comerciais dos serviços são da Avalia. Marca, razão social e
nomenclatura do fornecedor não aparecem no catálogo, no portal nem em documento
gerado para o cliente.

### Família suprimida

Os serviços veiculares estão precificados, mas o contrato com o fornecedor não
foi fechado e o custo não foi levantado. Enquanto isso, seus números são
estimativa, e estimativa exibida sem aviso vira proposta.

Por isso a categoria fica **travada**: a aba não navega, o filtro por veicular
não abre nem digitado na URL, e nenhuma linha veicular chega à matriz. A aba
"Todos" trava junto enquanto houver categoria suprimida, porque a opção mais
ampla mostraria justamente o que está travado.

O serviço continua visível na lista de serviços, com cadeado, para a
administração saber que existe e o que falta liberar, e sua página continua
aceitando preço e custo: suprimir na vitrine não pode impedir de manter a
estimativa. Fechado o contrato, a regra cai em um lugar só.

### Planilha do módulo

O catálogo sai e volta numa planilha de três abas, catálogo, planos e serviços,
para quem negocia com o fornecedor trabalhar com custo, preço e plano lado a
lado.

A importação casa as colunas pelo **título**, nunca pela posição, porque quem
edita no Excel move coluna. A comparação é feita sobre uma forma canônica, sem
acento e em minúsculas, então cabeçalho redigitado à mão continua sendo
reconhecido. Linha com código desconhecido é ignorada e não cria serviço: criar
serviço é decisão comercial, não efeito colateral de importação.

Serviço pausado não vai na planilha, que existe para reprecificar o que a Avalia
vende hoje. Isso não apaga nada: a importação só mexe nas linhas que recebe.

### Calculadora de contrato

A margem por serviço responde se um preço dá lucro. A calculadora responde a
outra pergunta, a que se faz antes de assinar: este cliente, nesta faixa,
consumindo isto, deixa quanto.

    fatura   = mensalidade + max(consumo realizado, consumo mínimo)
    imposto  = alíquota × fatura
    custo    = custo do fornecedor sobre o consumo REALIZADO
    lucro    = fatura − imposto − custo
    comissão = 10% do lucro

O custo vem da tabela e não é digitado: custo e preço de venda são fixos por
serviço, então a proporção entre eles já está decidida e vale para o consumo
total, qualquer que seja o mix consultado.

Daí sai o efeito que o comercial precisa enxergar: **o cliente que paga o mínimo
sem usar é o mais lucrativo**, porque o piso da fatura entra quase inteiro no
lucro, sem gerar custo de fornecedor. Na faixa de R$ 900 isso é 78% de margem
contra 35% de quem consome o mínimo inteiro.

Nada é gravado, e o endereço carrega o cenário: a simulação vira link em vez de
captura de tela.

### Cálculo mensal

    consumo_bruto     = valor das consultas concluídas com sucesso
    consumo_excedente = consumo_bruto − valor das consultas incluídas na franquia
    valor_de_consumo  = max(consumo_minimo, consumo_excedente)
    fatura            = mensalidade + valor_de_consumo

A franquia é medida por quantidade de consultas de cada serviço. Cada consulta
concluída reduz uma unidade disponível e possui preço unitário no catálogo. O
excedente é o consumo que ultrapassa a franquia contratada e entra de forma
consolidada na fatura mensal; não há cobrança avulsa durante a consulta.

No fechamento, cada serviço gera item próprio com quantidade utilizada na
franquia, quantidade excedente, valores e custo congelados. O painel do cliente mostra plano e vigência, franquia contratada, utilizada e
disponível, valor consumido, excedentes previstos, serviços disponíveis e a
situação da última fatura. Ele não mostra fornecedor, custo interno, margem ou comissão.

### Situação da conta

| Situação | Login | Consulta |
| --- | --- | --- |
| Ativo | Permitido | Permitida |
| Inadimplente | Permitido para regularização | Bloqueada |
| Bloqueado | Conforme decisão administrativa | Bloqueada |
| Inativo | Bloqueado | Bloqueada |

A linha do tempo da inadimplência é fixa:

| Dia | Evento |
| --- | --- |
| 10 | Vencimento da fatura. |
| 11 a 19 | Fatura em atraso. Consultas continuam liberadas; o cliente é avisado. |
| 20 | Bloqueio das consultas. O login permanece aberto para o cliente ver a fatura, obter a segunda via e regularizar. |
| Liquidação | Consultas liberadas de volta no mesmo ciclo, sem esperar a competência seguinte. |

Cada fatura começa como **pendente**, torna-se **vencida** após o vencimento e
só se torna **liquidada** por confirmação idempotente do Asaas. A liquidação
libera a comissão daquela fatura e reativa a empresa inadimplente apenas se não
houver outra fatura pendente ou vencida.

O bloqueio existe para forçar o pagamento, não para punir: por isso ele fecha a
consulta e mantém o acesso à fatura. Cliente que não consegue ver o que deve não
tem como pagar.

O ciclo seguinte só é restaurado para contas elegíveis. Prazos de vencimento,
bloqueio e reativação devem ser parametrizáveis e auditados.

## 7. Serviços e consultas

### Bases

As integrações previstas para a primeira versão são SPC, Serasa, Boa Vista/SCPC,
SCR e Veicular. Cada base é um conector separado, com credenciais por ambiente,
timeout, tratamento de erro, custo e contrato de resposta próprios.

O administrador configura bases e produtos disponíveis. Vendedores acessam
somente as bases liberadas a eles. O cliente consome serviços do plano sem saber
qual base realizou a pesquisa.

SCR e Veicular têm catálogo e permissões implementados desde já. A trava por
serviço é operada na tela de Serviços: enquanto marcada, o serviço aparece no
catálogo e pode ser precificado, mas não entra em plano nenhum.

Os serviços de SCR estão liberados no catálogo, e o arranjo jurídico foi
confirmado em 05/08/2026. Isso os torna vendáveis; **não** os torna consultáveis,
porque o conector do fornecedor ainda não existe.

Estar seguro de que se pode consultar é diferente de conseguir demonstrar, dois
anos depois, que aquela consulta específica tinha autorização. O que se guarda
não é a permissão de operar, é a **evidência de cada uso**. Por isso, quando o
conector de SCR for construído, duas coisas são obrigatórias no próprio conector,
e não na trava do catálogo:

- **prova da autorização vinculada à consulta**, guardada junto dela;
- **trilha específica** para consultas de SCR, separada da trilha geral.

O SCR recebe nome comercial mascarado.

### Serviços apresentados ao cliente

O portal apresenta serviços pelo nome comercial e em botões ou cartões, por
exemplo, uma consulta SCR em destaque. Antes da confirmação, o sistema informa o
impacto no consumo sem revelar a base subjacente.

### Execução segura

Antes de chamar o fornecedor, criar consulta com status processando, cliente,
vendedor, operador, origem, serviço, documento, competência, preço e custo.

- Sucesso: marcar ok, salvar resposta normalizada e contabilizar consumo.
- Erro: marcar erro, não cobrar o cliente, não gerar comissão e registrar motivo técnico seguro.
- Queda ou timeout: manter registro rastreável para reconciliação.

Se o fornecedor tiver processado uma consulta que falhou antes da resposta final,
o custo é absorvido pela Avalia; a empresa cliente não é cobrada.

Consultas bem-sucedidas geram relatório visual e imprimível. A resposta bruta é
dado sensível e fica no servidor, com acesso restrito e retenção limitada.

O cliente sempre consulta terceiros. O relatório deve apresentar aviso de
confidencialidade, finalidade permitida, responsabilidade do solicitante e canal
para dúvidas. O aviso deve ser claro e informativo, com base legal e documento de
autorização vinculados à consulta; não deve simular orientação jurídica.

### Boa Vista/Equifax

A integração já mapeada usa OAuth2 client_credentials com Basic Auth e requer os
cabeçalhos app e secondaryCode. O caminho conhecido é
/business/reporting-orchestrator/v1/consulta. O Orchestrator não está publicado
no sandbox; payload e resposta devem ser validados em ambiente de teste homologado.

CPF e CNPJ são validados no servidor. O CNPJ alfanumérico segue a RFB 2.229/2024.

## 8. Cobrança e Asaas

O Asaas será o provedor de cadastro financeiro, cobrança e liquidação.

### Integração

- Criar e manter um customer do Asaas para cada empresa.
- Criar cobranças de adesão, recorrência, excedentes e ajustes.
- Oferecer boleto com QR Code e Pix.
- Salvar IDs externos, URLs de boleto, linha digitável, QR Code, vencimento,
  valor, status e histórico.
- Permitir ao administrador visualizar, editar, reemitir, cancelar ou baixar
  cobranças de acordo com as regras do Asaas.

### Webhooks

O endpoint valida segredo ou assinatura, registra evento bruto de forma segura,
garante idempotência e processa em fila. Deve cobrir criação, atualização,
vencimento, pagamento, atraso, estorno, cancelamento, reembolso e falhas.

Um webhook não altera uma fatura diretamente sem correlação pelo ID externo e
trilha de auditoria. Falhas devem ser reprocessáveis.

### Fechamento automático

Um comando agendado deve:

1. Fechar competências vencidas e congelar preços, consumo, comissão e impostos.
2. Criar ou atualizar a fatura interna.
3. Solicitar a cobrança no Asaas.
4. Enviar aviso ao cliente.
5. Aplicar a política de atraso e suspensão.
6. Abrir a competência seguinte quando houver condição de acesso.

O fechamento de mês em andamento exige ação administrativa explícita e auditoria.

Toda fatura vence no dia 10, independentemente do dia em que a competência foi
fechada ou em que o cliente aderiu. É data de calendário, não contagem a partir
do fechamento: o cliente que adere no dia 28 recebe a primeira fatura no
vencimento seguinte, com a competência proporcional.

A taxa de adesão pode ser parcelada. Cada parcela é uma cobrança própria no
Asaas, com o mesmo vencimento dia 10, e o não pagamento de uma parcela segue a
mesma política de bloqueio das demais faturas.

## 9. Vendedores e carteiras

Cada vendedor possui carteira pessoal isolada. O administrador vê todas; o vendedor vê
somente clientes, consumo, cobranças permitidas, oportunidades e indicadores da
própria carteira.

Carteira legada e mailing distribuído ficam fora do escopo. O sistema preserva
responsável, histórico de transferências e regras de visibilidade de cada cliente.

### Ganhos do vendedor

O painel mostra previsão de comissão, participação na adesão, repasses pagos,
pendências e histórico por competência. Comissão e adesão tornam-se elegíveis
somente após a liquidação da cobrança correspondente no Asaas.

### Comissão recorrente

A alíquota é **10% sobre o lucro do mês** e vale para todos os planos e todas as
faixas. É o padrão, não uma trava: a administração define o percentual de cada
vendedor no cadastro da equipe, com teto de 50%.

A taxa vale a partir do próximo fechamento. Cada fatura guarda o percentual usado
na emissão, então renegociar hoje não reescreve competência já fechada nem o
repasse que foi combinado. Percentual fora da faixa aceita cai no padrão, para
que um erro de digitação não vire pagamento.

A base é lucro, e não faturamento. O imposto de 13,50% sai primeiro, o custo do
fornecedor sai em seguida, e o vendedor leva 10% do que sobrar. Duas vendas do
mesmo tamanho pagam comissões diferentes quando o custo do fornecedor é
diferente, e é isso que alinha o interesse do vendedor ao da operação: ele deixa
de ser indiferente à margem do que vende.

Mês no prejuízo não gera comissão, e não gera comissão negativa: o vendedor não
ganha sobre lucro que não existiu, mas também não paga por ter vendido.

A consequência aritmética é que a Avalia fica sempre com 90% do lucro, qualquer
que seja o preço praticado.

A base é o que o cliente efetivamente usou, não a franquia contratada nem o valor
da fatura. Isso tem uma consequência que precisa estar clara no treinamento
comercial: um cliente com consumo mínimo de R$ 900,00 que consome R$ 300,00 paga
R$ 979,90 de fatura e gera R$ 30,00 de comissão, não R$ 97,99. O vendedor ganha
sobre uso, e o piso da fatura protege a Avalia, não a comissão.

Não há adicional por excedente. Ele existia quando a comissão lia faturamento e
servia para o vendedor ganhar quando o cliente consumia acima da franquia. Sobre
lucro isso já acontece sozinho: consumo a mais gera lucro a mais, e 10% dele
também. Somar um adicional em cima pagaria o mesmo ganho duas vezes.

    fatura          = mensalidade + max(consumo do mês, consumo mínimo)
    imposto         = 13,50% × fatura
    custo           = custo do fornecedor sobre o consumo REALIZADO
    lucro           = fatura − imposto − custo
    comissao        = lucro > 0 ? 10% × lucro : 0

A mensalidade entra na base, porque entra no lucro. A taxa de adesão não: ela tem
rateio próprio de 50%, descrito adiante, e contar duas vezes pagaria o vendedor
duas vezes pelo mesmo dinheiro.

Consulta que não aconteceu não tem custo de fornecedor. Por isso o cliente que
paga o mínimo sem usar é o mais lucrativo: o piso da fatura entra quase inteiro
no lucro. A calculadora do módulo Catálogo mostra esse efeito faixa a faixa.

### Taxa de adesão

O valor é livre: quem define é o vendedor, na proposta. Pode ser parcelada, e
uma adesão de R$ 12.000,00 em doze parcelas de R$ 1.000,00 é caso previsto. Cada
parcela vira uma cobrança própria.

O rateio é **50% para o vendedor e 50% para a Avalia**, e cada metade só se torna
elegível conforme as parcelas forem liquidadas.

O vendedor pode isentar a adesão. Isentar significa que ninguém recebe: não é
desconto na parte da Avalia, é ausência da cobrança inteira. O valor acordado, ou
a isenção, deve constar na proposta e no contrato.

### Vigência

A vigência é escolhida pelo vendedor na proposta, entre quatro opções:

| Opção | Efeito |
| --- | --- |
| Sem vigência | Cliente pode encerrar a qualquer momento. |
| 12 meses | Vigência padrão. |
| 24 meses | Vigência longa. |
| Carência especial | Três meses de acesso liberado para teste, sem vigência; ao fim do período, o contrato passa a valer por 12 ou 24 meses, definidos na assinatura. |

A carência especial é período de avaliação da plataforma, não de gratuidade: as
regras de cobrança de consumo continuam valendo, o que não corre é o prazo de
permanência.

Cliente que não pagou não gera comissão; se atrasar, o vendedor aguarda a
liquidação e é responsável pelo relacionamento e acompanhamento da cobrança.
Enquanto o vendedor for PJ ativo, a venda é recorrente; após seu desligamento, a
carteira permanece com a Avalia e não gera novas comissões para ele.

A plataforma registra custos fiscais, notas fiscais e taxa administrativa para
apuração de repasses. A fórmula de descontos e repasse deve ser configurável,
congelada no fechamento e auditada.

## 10. Campanhas sazonais

O administrador configura campanhas para clientes específicos ou segmentos. Uma
campanha tem nome, período, público elegível, oferta, regras de preço, serviços,
limite de uso, prioridade, estado e termo aplicável.

O cálculo registra a campanha aplicada a cada venda ou consulta para que
alterações futuras não mudem valores históricos.

### A campanha na página pública

A campanha vigente (ativa e dentro do período) veste o banner da página
pública: o nome vira o selo e a oferta vira o texto do convite. Sem campanha
vigente, o banner volta ao texto fixo. Encerrar é um clique na lista de
campanhas, com efeito imediato lá fora, e a trilha registra quem encerrou.

A regra dura da página pública vale também aqui, em execução e não só no
teste: campanha cujo texto menciona preço, custo, margem ou nome de fornecedor
não sobe para a vitrine, e o banner fica com o texto fixo. O texto é livre para
a administração, mas a vitrine não publica o que a seção 7 manda guardar.

## 11. Atendimento e documentos

### Pedidos de contato da página pública

O formulário da campanha na página pública grava o interessado no nosso banco
(nome, empresa, telefone, e-mail, faixa de funcionários e a origem do pedido),
em vez de abrir o WhatsApp com dado pessoal na URL. A fila de quem ainda espera
retorno aparece no painel da administração, que é a primeira tela aberta do
dia; o botão Atendido tira o pedido da fila sem apagá-lo, porque o registro
mede qual porta converte, e a trilha de auditoria guarda quem atendeu. O
vendedor não vê nem atende a fila: lead ainda não tem carteira, e distribuí-lo
é decisão da administração.

### Atendimento

O portal do cliente oferece acesso direto ao SAC pelo WhatsApp +55 34 99117-6599.
O link abre conversa com mensagem contextual contendo, quando aplicável,
identificador da empresa, fatura ou consulta. Não enviar dados pessoais ou
resultado de crédito na URL.

Posteriormente, chamados internos podem incluir status, responsável, prazo e
histórico, sem substituir o canal principal.

### Comunicação entre os papéis

Hoje nenhum papel fala com outro dentro do sistema. Vendedor, administração e
empresa se combinam por fora, e nada disso fica no registro: quem prometeu o quê
some junto com a conversa.

O que precisa existir, em ordem de falta:

| Quem avisa | Quem recebe | Sobre o quê |
| --- | --- | --- |
| Sistema | Empresa | Fatura emitida, vencimento próximo, bloqueio e desbloqueio |
| Sistema | Vendedor | Cliente dele pagou, atrasou ou está a caminho do bloqueio |
| Sistema | Vendedor | Mudança na comissão dele, porque muda o que ele recebe |
| Sistema | Administração | Baixa manual, concessão de permissão financeira, falha de cobrança |
| Vendedor | Administração | Pedido de remoção de empresa com fatura emitida |
| Administração | Vendedor | Retorno sobre o pedido |

Falta também o histórico de contato por empresa: quem ligou, quando, o que ficou
combinado. Sem ele, a informação vive na memória de quem atendeu, e a carteira
não sobrevive à troca de vendedor.

O aceite do contrato não avisa ninguém. A empresa aceita e nada indica que ela já
pode operar, então a ativação continua dependendo de alguém conferir à mão.
### Base de implementação

Uma seção administrativa disponibiliza versões controladas de PDFs:

- contratos de cliente;
- termos de aceite;
- termo de confidencialidade;
- termo de conduta ética corporativa e anticorrupção;
- orientações de LGPD;
- POP de emissão de nota fiscal para vendedor PJ;
- POP de faturamento de parceiros.

Os arquivos possuem versão, categoria, vigência, público, status e trilha de
aceite ou download. O administrador define os acessos. Contratos gerados
registram a versão usada e os valores comerciais congelados. Assinatura
eletrônica integrada fica fora do escopo inicial.

O que já está implementado desta seção:

- **PDF de todo documento**, gerado sem dependência externa (a hospedagem
  bloqueia processos externos, e o gerador próprio segue o precedente da
  planilha XLSX). O rodapé de cada página carrega tipo, versão e o resumo
  sha256 da íntegra, então uma cópia impressa é conferível contra o banco.
- **Aceite como evidência**, não como clique: exige o nome de quem aceita (a
  conta é da empresa, mas quem clica é uma pessoa), a confirmação explícita de
  leitura, e o hash do texto que estava na tela, conferido no ato. Se o
  documento mudou entre a leitura e o clique, o aceite é recusado e a pessoa
  relê a versão vigente.
- **Comprovante de aceite em PDF**: quem aceitou, quando, de onde (IP e
  navegador), o hash aceito e a íntegra do texto, disponível para a empresa a
  qualquer momento depois do aceite.

## 12. Painéis, indicadores e BI

### Um painel por trabalho, e não um painel filtrado

Administrador e vendedor fazem trabalhos diferentes. Mostrar a mesma lista de
números para os dois, mudando apenas o filtro, obriga cada um a ignorar metade da
tela e ensina os dois a não olhar.

A separação foi feita: administrador e vendedor abrem a mesma URL e recebem telas
inteiras diferentes, escolhidas em um único ponto do controller. Nenhum número da
operação chega à view do vendedor, e nenhum indicador de comissão pessoal chega à
do administrador, que não recebe comissão.

| Painel | Pergunta que ele responde |
| --- | --- |
| Administração | A operação está saudável? Quanto entra, quanto sai, quem está devendo e qual margem sobrou. |
| Vendedor | Minha carteira está bem? Quem consome, quem parou de consumir, quem vai ser bloqueado e quanto eu ganhei. |
| Empresa cliente | Estou usando o que contratei? Quanto já consumi, quanto falta para o mínimo, quanto vou pagar e o que ainda tenho de franquia. |

### O que cada um precisa ver, e hoje não vê

**Administração**: o custo total pago ao fornecedor no mês e a comissão liberada
aberta por vendedor passaram a estar no painel da operação. O custo era a segunda
maior conta da empresa e só aparecia linha a linha na matriz; a comissão só
existia como total, o que obrigava o financeiro a somar à mão para pagar cada um.

Tempo de resposta e taxa de falha por serviço passaram a aparecer no painel de
consultas, que lê `duracao_ms` e `situacao` do recorte escolhido. O tempo médio
considera apenas as consultas concluídas: tentativa encerrada por tempo esgotado
tem duração alta e não representa a resposta do fornecedor, e misturar as duas
piora a média justamente quando o serviço melhora.

**Vendedor**: demonstrativo de repasse por competência para conferir e emitir
nota, e a base sobre a qual a comissão dele incidiu, não só o valor.

Empresas a caminho da suspensão e empresas que pararam de consultar são as duas
listas do painel dele. A primeira mostra a janela entre o vencimento e a suspensão
automática, que é o único momento em que uma ligação ainda evita a interrupção. A
segunda coloca quem nunca consultou no topo: contrato assinado e nunca usado não
vira renovação.

As duas ainda dependem de o vendedor abrir a tela. Não existe aviso ativo.

**Empresa cliente**: preço unitário antes de consultar, quanto falta para atingir
o consumo mínimo, previsão da fatura do mês e a composição da fatura fechada, que
já existe em `itens_fatura` e não é usada.

A área do cliente deixou de ser página única e passou a ter uma tela por assunto:
painel, consultar, consultas, faturas e documentos. Franquia restante por serviço
e consumo do mês corrente estão no painel; o histórico completo, filtrável por
período, serviço e resultado, está em consultas.

Não há filtro por CPF ou CNPJ consultado em nenhuma das três telas. Filtro vira
query string, e query string vai para o log do servidor, para o histórico do
navegador e para o link colado no chat. O protocolo responde à mesma pergunta sem
carregar dado pessoal.

### Indicadores de negócio

Estes respondem se o negócio anda, e não se o mês fechou:

| Indicador | Por que importa |
| --- | --- |
| Receita recorrente mensal | Base de tudo: mensalidade mais consumo previsível. |
| Cancelamento no período | Um cliente que sai custa mais que dois que entram. |
| Receita média por empresa | Mostra se a estratégia de faixas está funcionando. |
| Margem realizada por empresa | A calculadora simula; isto mostra o que aconteceu de fato. |
| Serviços por receita e por margem | Quais consultas puxam dinheiro e quais podem sair do catálogo. |
| Tempo médio de contrato | Diz se a vigência está segurando alguém. |

### Quando vale a pena construir BI

**Não agora.** Com um punhado de clientes, série histórica, coorte e tendência não
dizem nada que uma consulta ao banco não responda, e custam manutenção contínua.

O corte sugerido é o **décimo cliente pagante**. Até lá, o que vale é exportar os
números para planilha, porque a decisão sai de uma reunião e não de um gráfico.

O que **não** pode esperar é a série histórica existir: hoje todos os indicadores
são do mês corrente, e sem guardar o fechamento de cada competência não haverá
passado para comparar quando a comparação passar a importar.

## 13. Dados e entidades

| Entidade | Responsabilidade | Situação |
| --- | --- | --- |
| staff | Administração e vendedores, com papel e percentual de comissão. | Existe |
| clientes | Empresa, CNPJ, situação, plano contratado e vendedor da carteira. | Existe, sem endereço, contato, vigência e adesão |
| catalogos, precos, servicos, planos e franquias_plano | Catálogo, quantidade incluída, regras de consumo e preço. | Existe |
| consultas | Preço e custo congelados, competência. | Existe, sem resultado, evidência e retenção |
| faturas e itens_fatura | Valores congelados, vencimento, situação de pagamento e composição por serviço. | Existe |
| auditoria | Rastro de ações administrativas, financeiras e de aceite. | Existe |
| documentos e aceites_documento | Materiais, versões e aceite do cliente. | Existe, sem trava de ativação |
| cobrancas_asaas e eventos_asaas | Correlação externa, histórico e idempotência. | Existe; depende das credenciais Asaas para envio real. |
| adesoes | Taxa de adesão, parcelas e rateio entre vendedor e Avalia. | Existe; a emissão das parcelas depende da definição da primeira data de vencimento. |
| campanhas e elegibilidade | Regras promocionais e histórico de aplicação. | Existe; campanhas não alteram valores sem regra comercial homologada. |
| chamados | Evolução opcional do atendimento. | Não existe |

Dados pessoais e resultados de crédito exigem menor privilégio, controle de
acesso, retenção e criptografia quando aplicável.

## 14. Segurança, LGPD e auditoria

- Senhas usam hashing do Laravel; sessões são regeneradas e revogáveis por sessao_versao.
- Limitar login por conta e IP com bloqueio progressivo.
- Nunca registrar senhas, tokens, autorizações, cookies ou chaves de API em log.
- ~~Separar segredos por ambiente e validar configuração insegura no deploy.~~
  Feito em `avalia:ambiente`, que roda no fim do deploy e impede a versão de
  subir com depurador ligado, cookie fora de HTTPS, raiz do servidor em `public`
  ou envio de e-mail desligado.
- Expurgar resposta de bureau conforme a retenção da seção 5, preservando metadados fiscais e auditoria.
- Cada consulta deve ter finalidade e responsável rastreáveis.
- Auditoria não pode impedir a operação principal caso a gravação falhe.

## 15. Entrega por fases

| Fase | Escopo | Situação |
| --- | --- | --- |
| Fundação | Usuários, clientes, planos, preços, políticas, auditoria e catálogo. | Entregue, exceto ficha completa da empresa |
| Consultas | Conectores, serviços, consumo, relatórios, retenção e painel de cliente. | Só o registro de consumo com preço congelado; falta o conector |
| Financeiro | Faturas, fechamento automático, Asaas, webhooks, boleto, QR Code, inadimplência e reativação. | Entregue no código; o envio real começa após configurar e homologar a conta Asaas. |
| Comercial | Carteiras, taxa de adesão, comissões, repasses e campanhas. | Carteira, comissão, adesão e campanhas entregues; pagamento de repasse continua uma operação financeira externa. |
| Operação | Documentos, atendimento, indicadores e BI. | Documentos, aceite, atendimento via WhatsApp e indicadores essenciais entregues. |

Cada fase inclui migrations, políticas, testes automatizados, filas, tratamento de
falhas, logs seguros e documentação de operação.

## 16. Decisões pendentes

- **A base da comissão é o lucro** (05/08/2026), e o adicional por excedente foi
  removido na mesma data por pagar duas vezes o mesmo ganho. O percentual passou
  a ser por vendedor, com 10% de padrão. Falta constar do contrato do vendedor:
  a comissão saiu de 10% do consumo para 10% do que sobra depois do imposto e do
  fornecedor, e isso reduz o valor a receber.
- **A confirmação manual de pagamento não pede justificativa.** Qualquer
  administrador confirma na tela, sem motivo registrado e sem permissão
  financeira separada. Antes de vender, isso precisa de justificativa
  obrigatória e de um papel próprio.
- **O aceite de documento não trava a ativação.** A empresa aceita, e o aceite
  fica registrado, mas consultar não depende dele. Para LGPD e SCR a trava é
  requisito, não conveniência.
- **O fechamento de competência é manual, empresa a empresa.** A rotina diária
  cobre vencimento e bloqueio, mas ninguém fecha o mês sozinho.
- **Alíquota de imposto confirmada em 13,50%** (05/08/2026), substituindo a
  estimativa de 8,60%. Falta o fechamento contábil dizer se ela varia com a
  faixa de faturamento, porque o imposto sai antes do lucro e mexe no piso de
  cada preço.
- **Levantar o custo do fornecedor dos serviços veiculares.** Os 26 serviços de
  crédito já estão com custo cadastrado; os veiculares não, e por isso ficam de
  fora da visualização de margem e do cálculo de piso.
- **Virada de plano no meio do mês.** Cliente muda de faixa no dia 12: cobra
  proporcional, cobra a faixa nova pelo mês inteiro ou cobra a antiga e passa a
  nova a valer no mês seguinte? A resposta muda a fatura e o discurso de venda.
- **Vigência não tem efeito.** Está gravada e ninguém verifica se venceu, nem
  avisa renovação. Contrato de 12 meses que passa despercebido é desconto dado
  sem contrapartida.
- **Carência especial não faz nada.** Três meses de avaliação é regra escrita e
  sem código.
- **Cancelamento de contrato não existe.** Marcar como inativo não encerra
  vigência, não emite fatura final e não avisa o vendedor.
- **Consulta duplicada.** Mesmo documento, mesmo serviço, dois minutos depois:
  cobra de novo ou reaproveita? É diferente da retenção: aqui é sobre cobrar
  duas vezes pela mesma informação.
- **Prazo de reconsulta gratuita**, se houver, precisa de número.
- **Reajuste anual.** O catálogo se reajusta; falta dizer se contrato vigente
  acompanha ou fica congelado até a renovação.
- Homologar comercialmente os preços dos anexos A e B e a margem sobre o custo do fornecedor.
- Definir a quantidade incluída na franquia de cada serviço, por faixa.
- Definir quais serviços dos anexos entram no catálogo inicial e quais ficam desativados.
- Formalizar fórmulas de imposto, taxa administrativa e repasse.
- Confirmar conectores, contratos e credenciais de SPC, Serasa, Boa Vista/SCPC e SCR.
- Definir política comercial de campanhas e reativação após inadimplência.
- Validar documentos jurídicos, LGPD, base legal de consulta e fluxos de aceite.

### Regras de cobrança sem definição

Nenhuma delas é trabalho de programação ainda: é regra que precisa existir antes
de aparecer o primeiro caso, porque no dia em que aparecer não haverá tempo de
decidir com calma.

- **Pagamento parcial.** O cliente paga metade da fatura: fica em aberto pelo
  saldo, é recusado, ou vira duas cobranças?
- **Pagamento em duplicidade.** Pagou duas vezes a mesma fatura: devolve,
  credita na competência seguinte, ou fica como saldo?
- **Renegociação de fatura vencida.** Prazo novo, parcelamento e efeito no
  bloqueio das consultas.
- **Juros, multa, desconto e tolerância.** Hoje não há nenhum dos quatro, e o
  boleto sai sem encargo por atraso.
- **Chargeback, Pix devolvido e boleto baixado indevidamente.** O pagamento foi
  desfeito depois de a comissão ter sido liberada: como se reverte, e quem
  comunica o vendedor?
- **Volta a consultar quando.** O código já devolve a empresa ao estado ativo
  assim que não resta fatura em aberto. Falta o texto concordar com isso e dizer
  se vale também para bloqueio administrativo.
- **Quem altera a adesão depois do contrato assinado**, e com qual aprovação.
- **Consequência do cancelamento antecipado**, quando houver vigência.
- **Desligamento de vendedor.** A carteira fica com a Avalia e as comissões
  futuras cessam, o que está escrito na seção 9 e não existe em código.

## 17. Estado atual do repositório

### Tabelas

Acesso: `staff` (administração e vendedores, com papel e percentual de comissão),
`clientes` (empresas contratantes com CNPJ, situação, plano e vendedor),
`tentativas_login` (bloqueio progressivo por conta e origem) e `sessions`.

Catálogo: `catalogos` (a tabela de preços, com alíquota de imposto e parâmetros de
margem), `servicos`, `precos` (preço de venda e custo interno por serviço e
faixa), `planos` e `franquias_plano`.

Consumo e cobrança: `consultas` (preço e custo congelados na emissão), `faturas`
(a cascata inteira, mais a alíquota e o percentual de comissão usados) e
`itens_fatura` (composição por serviço, com quantidade na franquia e excedente).

Registro: `auditoria`, `documentos` e `aceites_documento`.

### Telas

A área de gestão é dividida por papel, e a divisão é física.

**Administração** vê Empresa, Consultas, Catálogo, Financeiro, Equipe e Auditoria.

- **Empresa**: cadastro com CNPJ validado por dígito, plano e vendedor; ficha com
  o consumo da competência aberta, fechamento e as faturas emitidas.
- **Consultas**: todas as empresas em uma lista, filtrável por período, serviço,
  resultado e protocolo, com tempo médio de resposta e falhas por serviço.
  Somente leitura: consulta é registrada pela ação de consumo, nunca à mão.
- **Catálogo**: quatro abas, Planos, Catálogo, Serviços e Calculadora, mais a
  página de parâmetros comerciais. A matriz é de leitura, com edição por linha na
  página do serviço.
- **Financeiro**: faturas de todas as empresas com o que há a receber, o que
  venceu e o que foi liquidado; confirmação de pagamento e o total de comissão a
  repassar por vendedor.
- **Equipe**: quem trabalha na Avalia e o percentual de comissão de cada vendedor.
- **Auditoria**: a trilha, apenas leitura.

**Vendedor** vê apenas a carteira, em quatro abas:

- **Empresas**: as empresas dele, o consumo da competência aberta e a comissão
  por competência, separando o que já foi liberada do que aguarda o pagamento.
- **Consultas**: as consultas das empresas da carteira, com os mesmos filtros da
  tela da administração. O recorte vem do vínculo da empresa com o vendedor, e
  não de parâmetro de URL.
- **Serviços**: o que ele pode vender em cada plano, com o preço que a empresa
  paga. Substitui a captura de tela da planilha, que envelhece a cada reajuste.
- **Simulação**: quanto a empresa paga por mês no cenário proposto e quanto ele
  recebe de comissão e de adesão.

Custo, lucro, imposto e margem não aparecem em nenhuma delas, e nem chegam à
view: o controller escolhe os campos que envia.

**Empresa cliente** entra na área dela, dividida por assunto: **Painel** com
plano, franquias, consumo do mês e pendências; **Consultar** com o formulário
sozinho; **Consultas** com o histórico filtrável; **Faturas** com a segunda via
quando disponível; e **Documentos** com os aceites.

### Regras que o código garante

- Preço e custo são **copiados** para a consulta e para a fatura no momento da
  emissão. É essa cópia, e não travar o catálogo, que impede um reajuste de hoje
  de reescrever a cobrança de ontem.
- A franquia é aplicada **por serviço e em quantidade**, antes de apurar o
  excedente. Sobre a soma em reais, um serviço barato cobriria um caro.
- O consumo mínimo é piso de **cobrança**, não de consumo: o cliente paga o maior
  entre o consumido e o contratado, mas só custa o que consultou.
- Uma competência fechada não aceita consulta nova, e não fecha duas vezes: o
  banco tem chave única por empresa e competência.
- A comissão só é liberada quando o pagamento é confirmado, e a confirmação é
  idempotente: a mesma confirmação repetida não libera duas vezes.
- Serviço e membro da equipe não são excluídos, apenas desativados: fatura,
  franquia e trilha apontam para eles.
- Revogar acesso derruba a sessão e o cookie de lembrança na hora.

### Cálculo

O dinheiro vive em `app/Support`: `Dinheiro` (centavos inteiros, leitura e
formatação), `Margem` (imposto, lucro, comissão, piso e preço alvo), `Comissao`
(alíquota e rateio da adesão), `Simulacao` (o mês de um contrato), `Documento`
(CNPJ, incluindo o alfanumérico) e `Planilha` (xlsx sem dependência externa).
Regra de negócio que grava vive em `app/Actions`.

A suíte tem 441 testes.

### Convite de acesso e senha

Ninguém digita a senha de outra pessoa, nem a conhece. O cadastro de vendedor e
de empresa não tem campo de senha: a conta nasce com uma senha aleatória que
ninguém sabe, e um e-mail de convite (enviado por financeiro@avaliaone.com.br)
leva o link para o próprio dono definir a dele.

O link morre duas vezes: pelo prazo, com assinatura temporária de 48 horas
conferida antes do controller; e pelo uso, com um carimbo derivado do hash da
senha atual embutido na assinatura, o que invalida todo link anterior no
momento em que uma senha nova é definida. Definir a senha também revoga as
sessões e o cookie de lembrança da conta, e entra na trilha de auditoria.

A redefinição é o mesmo mecanismo: o botão "Enviar redefinição de senha" na
edição gera um convite novo. O vendedor só reenvia para empresa da própria
carteira. Falha de envio não desfaz cadastro e não passa em silêncio: a tela
avisa e o botão de reenvio resolve.

Quem esqueceu a senha não depende de ninguém: a página "Esqueci minha senha"
(`/esqueci`, com link na tela de entrada) recebe o e-mail e dispara o mesmo
convite. A resposta na tela é idêntica exista ou não a conta, para o formulário
não virar um verificador público de quem é cliente; conta desligada e empresa
sem acesso não recebem nada, e o envio tem teto de cinco tentativas por minuto
por origem.

A conferência de ambiente reprova produção com driver de e-mail em arquivo
desde que o primeiro envio passou a existir: convite que não chega seria conta
que ninguém consegue acessar, sem erro em tela nenhuma.

### O que não existe

Conector de consulta ao fornecedor e resultado de consulta. As credenciais,
contratos e homologação dos bureaus são externos e indispensáveis para ativá-lo;
não há registro manual de consulta como substituto. A emissão real de cobrança
também requer credenciais Asaas e URL pública para o webhook. O pagamento de
repasses e a assinatura eletrônica são operações externas ainda sem integração.

## 18. Glossário e nomenclatura

Seis conceitos deste produto têm significado preciso e nome parecido. Sem uma
decisão escrita, cada tela nova reinventa como chamá-los, e o operador aprende
que a mesma coisa muda de nome conforme onde ele clica.

### Glossário

| Termo | O que é |
| --- | --- |
| Catálogo | A tabela de preços da Avalia. Uma só, editável, sem versionamento. |
| Serviço | Uma consulta vendável, com código imutável e nome comercial da Avalia. |
| Faixa | Um degrau de consumo mínimo. Define a coluna de preços que vale para a empresa. |
| Plano | O que a empresa contrata: uma faixa, uma mensalidade e as franquias. |
| Franquia | Quantidade de consultas de um serviço já inclusa na mensalidade. Conta em unidades, não em reais. |
| Consumo mínimo | Piso de **cobrança**, não de consumo. A empresa paga o maior entre o consumido e o contratado. |
| Excedente | O que passou da franquia contratada e por isso é cobrado. |
| Competência | O mês de referência do consumo, no formato AAAA-MM. |
| Fatura | A competência fechada, com a cascata congelada. |
| Cobrança | O documento de pagamento gerado a partir da fatura. |
| Piso de preço | O menor preço que paga fornecedor e imposto sem prejuízo. Calculado, nunca cadastrado. |
| Margem | O que sobra para a Avalia depois de imposto, fornecedor e comissão. |
| Adesão | Taxa de entrada, parcelável, rateada meio a meio com o vendedor. |
| Carteira | O conjunto de empresas de um vendedor. |
| Retenção | Prazo até a resposta do bureau ser apagada. São 180 dias. |

### Como chamar cada coisa

A regra geral: **código em português sem acento, tela em português correto**, e o
nome na tela muda conforme quem lê.

| Conceito | No código | Para a administração | Para o vendedor | Para a empresa |
| --- | --- | --- | --- | --- |
| Empresa contratante | `Cliente` | Empresa | Cliente da carteira | (ela mesma) |
| Fatura paga | `liquidado` | Paga | Comissão liberada | Paga |
| Fatura em aberto | `pendente` | Em aberto | Aguardando pagamento | Em aberto |
| Fatura vencida | `vencido` | Vencida | Vencida | Em atraso |
| Consulta que deu certo | `sucesso` | Concluída | Concluída | Concluída |
| Consulta que falhou | `falha` | Não concluída | Não concluída | Não concluída, sem cobrança |
| Consumo acima da franquia | `excedente` | Excedente | Excedente | Consumo além do incluído |

### Decisões de nomenclatura

- **Empresa** é o nome na tela; `Cliente` é o nome no código. A rota `/empresas`
  segue a tela. Não usar "cliente" em texto de administração para não confundir
  com o consumidor final consultado.
- **Valor interno nunca aparece na tela.** `liquidado`, `pendente` e `sucesso` são
  chaves de banco, e o operador não deve aprender o vocabulário do esquema. A
  tradução vive em `App\Support\Rotulos`, em um lugar só: a mesma situação aparece
  na tela do cliente, na do vendedor e na da administração, e três listas soltas
  viram três nomes para o mesmo estado no primeiro ajuste. A cor da etiqueta sai
  de lá pelo mesmo motivo. Situação nova sem rótulo derruba o teste em vez de
  aparecer crua para alguém.
- **Unidade de medida também é vocabulário.** O banco guarda a espera em
  milissegundos porque é o que o fornecedor devolve; a tela mostra segundos,
  porque ninguém decide nada com "847 ms".
- **Nada de palavra de quem escreve o sistema.** Integração, credenciais,
  parâmetro, coluna de preços, configuração, gerenciamento e tentativa descrevem
  como a coisa foi construída, e não o que ela resolve. O teste é simples: se a
  frase só faz sentido para quem viu o código, ela está errada na tela.
- **Plano precisa de nome comercial.** "Consumo mínimo R$ 900,00" descreve o campo,
  não o produto. Nome que se venda, com a faixa como atributo ao lado.
- **A aba de preços do módulo Catálogo chama-se Preços**, e não Catálogo, para não
  repetir o nome do módulo dentro dele.
- **Jargão financeiro fica na administração.** "Liquidado" não vai para o portal
  do cliente nem para a carteira.

## 19. Backlog priorizado

O que falta, na ordem em que atrapalha. A régua é operação enxuta: o que exige
alguém disponível para atender à mão vem antes do que exige alguém para manter.

### Os cinco primeiros

Cada um protege dinheiro ou impede que um erro chegue em produção, e nenhum leva
mais de um dia.

1. ~~**Testar restauração de backup.**~~ Feito, incluindo o destino externo.
   `avalia:exportar` e `avalia:importar` não dependem de `pg_dump`, a
   restauração num banco vazio foi conferida registro a registro, e a rotina
   das 02:00 roda com `--enviar`: a cópia comprimida sai da máquina por e-mail
   para financeiro@avaliaone.com.br (`AVALIA_COPIA_EMAIL` muda o destino). Se o
   envio falhar, a cópia local permanece e a rotina termina em erro, para a
   falha aparecer no log em vez de passar em silêncio.
2. **Estorno com reversão de comissão.** Hoje a comissão liberada não volta
   atrás. É o buraco mais grave do fluxo financeiro.
3. **Conciliação diária entre faturas e recebimentos do provedor.**
4. **Impedir consulta duplicada por clique repetido**, que é cobrança dupla
   esperando acontecer.
5. **Pipeline que bloqueia o merge com teste de negócio falhando.** Hoje isso
   depende de alguém lembrar de rodar.

### Dinheiro que pode sumir sem ninguém ver

6. Alerta de webhook recebido sem cobrança correspondente.
7. Alerta de cobrança sem identificador externo no provedor.
8. Idempotência também na reemissão, no cancelamento e na atualização de cobrança.
9. Validação de configuração obrigatória antes de ativar integração externa,
   falhando na largada em vez de silenciosamente.
10. Alerta imediato a um segundo par de olhos em baixa manual, mudança de
    comissão e concessão de permissão financeira.
11. Conferência de fechamento: soma das faturas contra soma das consultas.
12. Alerta de falha do fechamento mensal e do envio de cobrança.

### Antes de vender para o primeiro cliente

Sem isto, a operação depende de alguém atender chamado no lugar do sistema.

13. ~~**Recuperação de senha.**~~ Feita pelo convite de acesso, em dois
    caminhos: o botão "Enviar redefinição de senha" na edição de vendedor e de
    empresa, e a página pública "Esqueci minha senha", em que a própria pessoa
    pede o link sem depender de ninguém. O link é assinado, vale 48 horas e
    morre no uso; definida a senha nova, todo link anterior morre.
14. ~~**E-mails transacionais de cobrança.**~~ Feitos, num layout único de
    e-mail (`mail/base`): fatura emitida (no fechamento, da rotina ou do
    clique), lembrete até 3 dias antes do vencimento (rotina das 09:00, com
    carimbo na fatura para nunca repetir), recibo de pagamento confirmado (que
    também avisa quando o pagamento reativa as consultas) e aviso de suspensão
    por atraso. Falha de envio nunca desfaz o evento financeiro, e evento
    repetido nunca repete e-mail.
15. ~~Aviso entre o vencimento e o bloqueio.~~ Feito: quando a rotina marca a
    fatura como vencida, o cliente recebe o aviso com a data limite antes da
    suspensão. A transição acontece uma vez por fatura, então o aviso também.
16. Limite de requisição nas consultas e limite diário por empresa.
17. **Preço visível antes de consultar** e **quanto falta para o mínimo**, no
    portal. Os dados já existem no banco.
18. Reemissão de cobrança quando a criação no provedor falha.
19. Painel do vendedor separado do painel do administrador.
20. Glossário e nomenclatura aplicados às telas (seção 18).

### Segurança de baixo custo e alto retorno

21. Cookies `Secure`, `HttpOnly` e `SameSite`.
22. Cabeçalhos de segurança HTTP e política de conteúdo.
23. Duplo fator na administração.
24. Limite de tentativas em endpoints sensíveis além do login.
25. Proteção contra enumeração de empresas, e-mails e documentos.
26. Revisão das mensagens de erro para não expor detalhe interno.
27. Rotação de chaves de API e do token de webhook, como política escrita.
28. Monitoramento de dependências vulneráveis, que é automático e gratuito.
29. Resumo criptográfico encadeado na trilha de auditoria: denuncia alteração
    posterior sem trocar a arquitetura por um banco imutável.

### Consultas e consumo

30. **PDF do resultado** com identificação da empresa, data, protocolo e aviso de
    confidencialidade. É o entregável que o cliente mostra ao gerente dele.
31. Histórico de consultas para a empresa, com filtro por período, serviço e
    situação, e exportação controlada.
32. Alerta ao atingir e ao exceder a franquia.
33. Registrar o fornecedor efetivamente usado, sem expor ao cliente.
34. Taxa de falha por serviço e por fornecedor. Os dados já estão gravados em
    cada consulta e não aparecem em lugar nenhum.
35. Prova de autorização vinculada a cada consulta de SCR, e trilha específica
    para elas.
36. Custo total pago ao fornecedor no mês, que é a segunda maior conta da empresa.
37. Conciliação mensal do consumo com cada fornecedor.

### Comercial

38. Congelar as condições comerciais na proposta e no contrato.
39. Bloquear alteração comercial que afete cobrança já emitida.
40. Aditivo quando plano, vigência ou adesão mudarem.
41. Histórico de preço negociado por empresa.
42. Cobrança das parcelas da adesão, hoje calculada e nunca cobrada.
43. Registro de repasse ao vendedor, com data, comprovante e demonstrativo por
    competência.
44. Nota fiscal do vendedor: número, data e vínculo com o repasse.
45. Transferência de carteira com motivo, responsável e data.
46. Calendário de vigência, carência e renovação, com alerta de contrato próximo
    do fim.
47. Alerta ao vendedor sobre cliente a caminho do bloqueio.

### Interface

48. Paginação nas listas, que vão quebrar sozinhas quando a base crescer. Feita
    nas listas de consultas e de faturas do cliente; falta nas demais.
49. Busca global por empresa, CNPJ e fatura.
50. Máscara e validação de CPF, CNPJ, CEP e telefone.
51. Estado vazio com ação direta em toda tabela.
52. Confirmação específica em ação irreversível ou financeira.
53. Composição da fatura no portal do cliente, a partir de `itens_fatura`.
54. Franquia restante por serviço e previsão da fatura do mês.
55. Ficha do cliente para o vendedor, sem custo e sem margem.
56. Foco visível, navegação por teclado, contraste e nome acessível nos ícones,
    como padrão contínuo e não como tarefa isolada.

### Quando a operação crescer

57. Papéis comercial e operação, separados do administrador.
58. Registro de sessões ativas e encerramento remoto.
59. Trilha distinta para uso do superusuário.
60. Retenção definida para auditoria e faturas, que hoje crescem sem prazo.
61. Registro de quem leu o resultado de uma consulta.
62. Prova do conteúdo aceito, e não só da versão do documento.
63. Histórico de custo do fornecedor, para a margem de ontem não ser recalculada
    com o custo de hoje.
64. Consulta em lote por arquivo.
65. Módulo de atendimento com chamado, responsável e prazo.
66. Notificação entre papéis dentro do sistema.
67. Indicadores de recorrência, cancelamento, receita média e margem realizada.
68. Série histórica dos fechamentos, guardada desde já para existir passado.
69. Análise estática de tipos e de segurança no pipeline.
70. Banco na mesma região da aplicação. Hoje são 172 ms por ida.

### Deliberadamente fora do escopo agora

Cada um resolve um problema que a operação ainda não tem, e cobra manutenção
contínua desde o primeiro dia.

- **Dupla aprovação para baixa manual acima de valor.** Numa operação de uma ou
  duas pessoas vira alguém aprovando a si mesmo, ou o processo sendo contornado.
  O item 10 dá o mesmo controle sem travar o dia.
- **Criptografia por coluna.** O banco já cifra o disco. Por coluna, quebra busca
  e ordenação e adiciona gestão de chave. Faria apenas na resposta do bureau, e
  só se um contrato exigir.
- **Expiração periódica de senha.** As recomendações atuais desaconselham: leva a
  senha fraca com número no fim. O duplo fator do item 23 resolve melhor.
- **Fallback entre fornecedores** e **circuito de bloqueio para fornecedor
  instável.** Não há dois contratos para alternar, nem evidência de instabilidade.
- **Fila de processamento.** Só compensa quando o tempo de resposta do fornecedor
  incomodar de verdade.
- **Ambiente de homologação separado.** Hoje a homologação acontece com dados
  fictícios, o que é aceitável enquanto não há cliente real.
- **Conector separado por bureau** antes de existir contrato com cada um. A
  interface já está pronta e é o que importa.
- **Antivírus em upload** e **usuários de banco por função.** Não há upload, e a
  separação de usuários custa mais do que protege nesta escala.
- **BI com coorte e tendência.** Ver seção 12: antes do décimo cliente, planilha
  responde melhor.
- **Campanhas.** Ou implementa com efeito em preço e elegibilidade, ou sai do
  menu. Cadastro que não faz nada ensina o operador a desconfiar do sistema.

## Anexo A. Preços de referência: crédito

> Transcrito da tabela de crédito do fornecedor (`temp/`). Valores unitários
> em reais, por faixa de consumo mínimo contratada. Provisórios até homologação
> comercial. Serviços marcados com `*` dependem de liberação do SCR.

| Serviço | Sem mínimo | 75 | 200 | 500 | 900 | 1.500 | 5.000 |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| Cheques sem fundos - Banco Central PF/PJ | 2,09 | 1,88 | 1,70 | 1,53 | 1,37 | 1,24 | 0,98 |
| Ações judiciais - nacional PF/PJ | 5,94 | 5,22 | 4,60 | 4,05 | 3,56 | 3,13 | 2,34 |
| SCPC BVS PF/PJ - Base III | 6,31 | 5,94 | 5,58 | 5,24 | 4,93 | 4,63 | 3,70 |
| Relatório Plus PF/PJ + cartórios e CCF Bacen - Base III | 7,81 | 7,42 | 7,05 | 6,70 | 6,36 | 6,04 | 5,40 |
| Crédito Net Básica PF/PJ - Base I | 15,93 | 15,13 | 14,37 | 13,66 | 12,97 | 12,32 | 10,97 |
| Mix PF/PJ - Base I e II | 18,52 | 17,41 | 16,37 | 15,39 | 14,46 | 13,60 | 12,01 |
| Crédito Net PF/PJ - Base I e III | 18,97 | 17,83 | 16,76 | 15,75 | 14,81 | 13,92 | 12,26 |
| Crédito Net Top + cartórios e CCF Bacen - Base I e III | 21,91 | 20,59 | 19,36 | 18,20 | 17,10 | 16,08 | 14,21 |
| Relatório Score Positivo + filtros - Base III | 9,93 | 9,44 | 8,97 | 8,52 | 8,09 | 7,69 | 6,94 |
| Risco de Crédito Top PF/PJ + filtros - Base I | 20,95 | 19,69 | 18,51 | 17,40 | 16,36 | 15,38 | 13,59 |
| Relatório Top PF/PJ + filtros - Base I e III | 28,57 | 27,43 | 26,33 | 25,28 | 24,27 | 23,30 | 21,25 |
| Relatório Top + SCR Bacen - Base I e III * | 44,50 | 40,94 | 37,67 | 34,65 | 31,88 | 29,33 | 26,99 |
| Maxi Top PF/PJ + score e filtros - Base I e II | 28,15 | 27,03 | 25,95 | 24,91 | 23,91 | 22,96 | 20,94 |
| Relatório Prime Básica + cartórios e CCF Bacen - Base I, II e III | 26,70 | 25,10 | 23,59 | 22,18 | 20,85 | 19,60 | 17,09 |
| Relatório Prime Completa + filtros - Base I, II e III | 37,05 | 35,20 | 33,44 | 31,77 | 30,18 | 28,67 | 25,60 |
| Relatório Prime Completa + SCR Bacen - Base I, II e III * | 52,89 | 48,66 | 44,77 | 41,18 | 37,89 | 34,86 | 32,42 |
| SCR Bacen + score PF/PJ * | 20,03 | 18,83 | 17,70 | 16,64 | 15,64 | 14,70 | 12,99 |
| Cadastro especial PF - endereço, telefone, e-mail, trabalho, renda | 3,03 | 2,73 | 2,46 | 2,21 | 1,99 | 1,79 | 1,45 |
| Cadastro especial PJ - dados da empresa, sócios, regime fiscal, faturamento | 3,03 | 2,73 | 2,46 | 2,21 | 1,99 | 1,79 | 1,45 |
| Telefones por CPF/CNPJ | 1,11 | 1,00 | 0,90 | 0,81 | 0,73 | 0,66 | 0,53 |
| Endereços por CPF/CNPJ | 1,11 | 1,00 | 0,90 | 0,81 | 0,73 | 0,66 | 0,53 |
| InfoBusca por CPF/CNPJ - telefone, endereço e e-mails | 2,07 | 1,86 | 1,67 | 1,51 | 1,36 | 1,22 | 0,99 |
| InfoBusca por nome (mostra CPF) | 2,07 | 1,86 | 1,67 | 1,51 | 1,36 | 1,22 | 0,99 |
| Localizador por telefone (mostra nome e CPF/CNPJ) | 2,07 | 1,86 | 1,67 | 1,51 | 1,36 | 1,22 | 0,99 |
| Localizador por CEP (mostra nomes e CPF/CNPJ) | 2,07 | 1,86 | 1,67 | 1,51 | 1,36 | 1,22 | 0,99 |
| Negativação | 17,90 | 17,90 | 17,90 | 17,90 | 17,90 | 17,90 | 17,90 |

Os filtros extras citados nos nomes são score, faturamento e renda presumida,
pontualidade de pagamento, balanço máximo e mínimo, quantidade de funcionários,
endereços, telefones, pessoas de contato, cartórios e Bacen direto.

## Anexo B. Preços de referência: veicular

> Transcrito da tabela veicular do fornecedor (`temp/`). Mesmas regras do
> anexo A.

| Serviço | Sem mínimo | 75 | 200 | 500 | 900 | 1.500 | 5.000 |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| Localiza veículos por CPF/CNPJ | 21,56 | 20,48 | 19,46 | 18,49 | 17,56 | 16,68 | 14,26 |
| Histórico de proprietário (somente São Paulo) | 14,30 | 13,87 | 13,45 | 13,05 | 12,66 | 12,28 | 11,55 |
| Proprietário atual | 5,94 | 5,76 | 5,59 | 5,42 | 5,26 | 5,10 | 4,80 |
| Agregados | 3,63 | 3,52 | 3,42 | 3,31 | 3,21 | 3,12 | 2,93 |
| RenaJud | 10,55 | 10,13 | 9,73 | 9,34 | 8,96 | 8,61 | 7,93 |
| RenaInf - infrações completa | 8,97 | 8,61 | 8,27 | 7,94 | 7,62 | 7,31 | 6,74 |
| CRLV - documento de licenciamento | 23,78 | 23,07 | 22,37 | 21,70 | 21,05 | 20,42 | 19,21 |
| BIN - base estadual e nacional | 5,96 | 5,72 | 5,49 | 5,27 | 5,06 | 4,86 | 4,48 |
| Leilão - Base I | 11,94 | 11,58 | 11,23 | 10,89 | 10,57 | 10,25 | 9,54 |
| Leilão conjugado completo + score do veículo - Base II | 21,95 | 21,29 | 20,65 | 20,03 | 19,43 | 18,84 | 17,55 |
| CSV - certificado de segurança veicular | 7,95 | 7,71 | 7,48 | 7,25 | 7,03 | 6,82 | 6,35 |
| Histórico de roubo e furto | 7,99 | 7,67 | 7,37 | 7,07 | 6,79 | 6,52 | 5,94 |
| Gravame indicativo | 7,95 | 7,63 | 7,32 | 7,03 | 6,75 | 6,48 | 5,97 |
| Gravame indicativo + agregados | 11,00 | 10,56 | 10,14 | 9,73 | 9,34 | 8,97 | 8,27 |
| Confere RG/CNH | 5,88 | 5,70 | 5,53 | 5,37 | 5,21 | 5,05 | 4,75 |
| Precificador / decodificador | 5,95 | 5,77 | 5,60 | 5,43 | 5,27 | 5,11 | 4,81 |
| VIP Car - informação completa do veículo | 55,30 | 53,64 | 52,03 | 50,47 | 48,96 | 47,49 | 44,68 |
