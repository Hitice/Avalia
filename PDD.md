# Documento de Produto — Avalia

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
| Administrador | Acesso concedido por permissões de comercial, financeiro, operação ou superadmin; configura catálogo, bases, campanhas e documentos e acompanha os módulos autorizados. |
| Vendedor | Administra apenas suas carteiras, acompanha clientes, consumo, previsão de ganhos, participação na adesão e materiais liberados. |
| Empresa cliente | Consulta os serviços contratados, acompanha franquia, excedentes, plano, faturas e atendimento. |

As contas de operação (admin e vendedor) ficam na tabela staff; empresas ficam
em clientes. As tabelas, guards, políticas e sessões são separados. Um papel
nunca obtém acesso a rotas, registros ou indicadores de outro papel sem permissão explícita.

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
no catálogo versionado — nunca no código.

| Parâmetro | Valor | Observação |
| --- | --- | --- |
| Mensalidade da Avalia | R$ 79,90 | Fixa, cobrada consumindo ou não. |
| Faixas de consumo mínimo | sem mínimo, R$ 75,00, R$ 200,00, R$ 500,00, R$ 900,00, R$ 1.500,00, R$ 5.000,00 | A faixa escolhida define o preço unitário de todos os serviços. |
| Taxa de adesão | valor livre, definido pelo vendedor | Cobre licença de uso, liberação de acesso e implantação. Pode ser parcelada e pode ser isentada pelo vendedor. |
| Rateio da adesão | 50% vendedor, 50% Avalia | Isentar significa nenhum dos dois receber. |
| Comissão recorrente | 10% sobre o consumo realizado no mês | Lê o que o cliente usou, não a franquia nem o valor da fatura. |
| Adicional de excedente | +10 pontos, totalizando 20% | Aplica-se ao mês em que houver excedente. |
| Vencimento da fatura | todo dia 10 | Data fixa de calendário, igual para todos os clientes. |
| Bloqueio por atraso | 10 dias após o vencimento | Na prática, dia 20. Bloqueia consultas; o login continua liberado para regularizar. |
| Vigência do contrato | escolhida pelo vendedor | Sem vigência; 12 meses; 24 meses; ou 3 meses de carência especial para teste, seguidos de 12 ou 24 meses. |
| Imposto sobre a venda | 27% (provisório) | Média estimada. Depende do regime tributário e precisa de confirmação contábil. |
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
restrição de acesso do catálogo — vendedor não entra.

    imposto = preço de venda × alíquota
    margem  = preço de venda − custo do fornecedor − imposto
    piso    = menor preço de venda cuja margem ainda não é negativa

O piso não é cadastrado, é calculado: abaixo dele a venda não paga fornecedor e
imposto. A tela marca em vermelho toda célula vendida abaixo do piso.

Custo em branco significa **custo ainda não cadastrado**, e é diferente de custo
zero: sem o dado, a plataforma não exibe margem nem piso em vez de exibir um
número inventado. A alíquota de imposto vive no catálogo, não no código, porque
muda com o regime tributário e precisa de rastro.

Cada serviço do catálogo tem código, descrição, preço de venda por faixa, custo
interno, franquia, regras de consumo e congelamento por contrato.

### Edição do catálogo

O catálogo é único e editável a qualquer momento pelo administrador com permissão
comercial ou financeira, célula a célula ou por reajuste percentual. Toda
alteração de preço, custo, franquia ou disponibilidade gera auditoria.

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

### Cálculo mensal

    consumo_realizado = valor das consultas concluídas com sucesso
    valor_de_consumo  = max(consumo_minimo, consumo_realizado)
    fatura            = mensalidade + valor_de_consumo

A franquia é medida por quantidade de consultas de cada serviço. Cada consulta
concluída reduz uma unidade disponível e possui preço unitário no catálogo. O
excedente é o consumo que ultrapassa a franquia contratada e entra de forma
consolidada na fatura mensal; não há cobrança avulsa durante a consulta.

O painel do cliente mostra plano e vigência, franquia contratada, utilizada e
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

Os serviços de SCR foram liberados dessa trava por decisão comercial. Isso os
torna vendáveis no catálogo; **não** os torna consultáveis, porque o conector
ainda não existe. A homologação jurídica, contratual e técnica continua sendo
pré-requisito da integração, e o SCR depende de autorização expressa verificável
para cada consulta. Quando o módulo Consultas for construído, essa verificação
precisa existir no próprio conector — a trava do catálogo não a substitui.

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

A alíquota é **10% sobre o consumo realizado no mês**, uma só para todos os
planos e todas as faixas.

A base é o que o cliente efetivamente usou, não a franquia contratada nem o valor
da fatura. Isso tem uma consequência que precisa estar clara no treinamento
comercial: um cliente com consumo mínimo de R$ 900,00 que consome R$ 300,00 paga
R$ 979,90 de fatura e gera R$ 30,00 de comissão, não R$ 97,99. O vendedor ganha
sobre uso, e o piso da fatura protege a Avalia, não a comissão.

No mês em que houver consumo excedente, a alíquota sobe **10 pontos, para 20%**,
e essa elevação vale para o plano como um todo naquela competência.

    consumo_do_mes  = valor das consultas concluídas com sucesso
    houve_excedente = consumo acima da franquia contratada
    aliquota        = houve_excedente ? 20% : 10%
    comissao        = aliquota × consumo_do_mes

Mensalidade e taxa de adesão não entram na base da comissão. Mensalidade e
consumo mínimo continuam sendo colunas separadas do plano por causa do cálculo da
fatura, não mais por causa da comissão.

### Taxa de adesão

O valor é livre: quem define é o vendedor, na proposta. Pode ser parcelada — uma
adesão de R$ 12.000,00 em doze parcelas de R$ 1.000,00 é caso previsto — e cada
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

## 11. Atendimento e documentos

### Atendimento

O portal do cliente oferece acesso direto ao SAC pelo WhatsApp +55 34 99117-6599.
O link abre conversa com mensagem contextual contendo, quando aplicável,
identificador da empresa, fatura ou consulta. Não enviar dados pessoais ou
resultado de crédito na URL.

Posteriormente, chamados internos podem incluir status, responsável, prazo e
histórico, sem substituir o canal principal.

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
registram a versão usada e os valores comerciais congelados. Nesta fase,
documentos são disponibilizados para download e aceite; assinatura eletrônica
integrada fica fora do escopo inicial.

## 12. Painéis e BI

| Painel | Conteúdo |
| --- | --- |
| Administração | Consumo por cliente, vendedor, base, serviço e competência; faturamento previsto e realizado; inadimplência; margem; comissões; campanhas; cobranças Asaas e integrações pendentes. |
| Vendedor | Carteira, consumo de clientes, previsão de ganhos, participação na adesão, situação de pagamento, metas, campanhas e bases liberadas. |
| Cliente | Plano, consumo, franquia, consultas disponíveis, excedentes, serviços, faturas e atendimento. |

As métricas possuem filtros por período, vendedor, cliente, serviço, base,
campanha e situação financeira, sempre respeitando permissões.

## 13. Dados e entidades

| Entidade | Responsabilidade |
| --- | --- |
| clientes e dados complementares | Empresa, contatos, vendedor, situação, vínculo comercial e permissões. |
| planos, precos, servicos e franquias_plano | Catálogo, quantidade incluída, regras de consumo e preço. |
| consultas | Solicitação, resultado, custo, preço, status, competência e retenção. |
| faturas e itens_fatura | Valores congelados, vencimento, situação e composição. |
| cobrancas_asaas e eventos_asaas | Correlação externa, histórico e idempotência. |
| comissoes, bonus_cadastro e repasses | Apuração após liquidação, descontos, pagamento e documentos fiscais. |
| campanhas e elegibilidade | Regras promocionais e histórico de aplicação. |
| documentos e aceites_documento | Materiais, versões, acesso e aceite. |
| chamados | Evolução opcional do atendimento. |
| auditoria | Rastro de ações administrativas e sensíveis. |

Dados pessoais e resultados de crédito exigem menor privilégio, controle de
acesso, retenção e criptografia quando aplicável.

## 14. Segurança, LGPD e auditoria

- Senhas usam hashing do Laravel; sessões são regeneradas e revogáveis por sessao_versao.
- Limitar login por conta e IP com bloqueio progressivo.
- Nunca registrar senhas, tokens, autorizações, cookies ou chaves de API em log.
- Separar segredos por ambiente e validar configuração insegura no deploy.
- Expurgar resposta de bureau conforme a retenção da seção 5, preservando metadados fiscais e auditoria.
- Cada consulta deve ter finalidade e responsável rastreáveis.
- Auditoria não pode impedir a operação principal caso a gravação falhe.

## 15. Entrega por fases

| Fase | Escopo |
| --- | --- |
| Fundação | Usuários, clientes, planos, preços, políticas, auditoria e catálogo. |
| Consultas | Conectores, serviços, consumo, relatórios, retenção e painel de cliente. |
| Financeiro | Faturas, fechamento automático, Asaas, webhooks, boleto, QR Code, inadimplência e reativação. |
| Comercial | Carteiras, taxa de adesão, comissões, repasses e campanhas. |
| Operação | Documentos, atendimento, indicadores e BI. |

Cada fase inclui migrations, políticas, testes automatizados, filas, tratamento de
falhas, logs seguros e documentação de operação.

## 16. Decisões pendentes

- **Confirmar o adicional de excedente.** A regra escrita é: houve excedente no
  mês, a alíquota do mês inteiro vai a 20%. A leitura alternativa seria aplicar
  20% só sobre a parcela excedente e 10% sobre o resto. A diferença é grande e
  muda o incentivo do vendedor.
- **Confirmar a base da comissão.** Está escrito consumo realizado. Cliente que
  paga o mínimo sem usar gera comissão menor que o valor da fatura — confirmar se
  é isso mesmo ou se a base deve ser o valor faturado de consumo.
- **Confirmar a alíquota de imposto.** Os 27% são estimativa. Os 6% citados
  correspondem ao ISS de serviço em muitos municípios, que é só uma parte da
  carga; PIS, COFINS e tributo sobre o lucro dependem do regime. Precisa de
  fechamento contábil antes de virar cálculo de margem.
- Cadastrar o custo do fornecedor por serviço e faixa. A tela já existe; o dado é
  que ainda não foi levantado. Sem ele não há margem nem piso de preço, e as 301
  linhas aparecem como custo não cadastrado.
- Homologar comercialmente os preços dos anexos A e B e a margem sobre o custo do fornecedor.
- Definir a quantidade incluída na franquia de cada serviço, por faixa.
- Definir quais serviços dos anexos entram no catálogo inicial e quais ficam desativados.
- Formalizar fórmulas de imposto, taxa administrativa e repasse.
- Confirmar conectores, contratos e credenciais de SPC, Serasa, Boa Vista/SCPC e SCR.
- Definir política comercial de campanhas e reativação após inadimplência.
- Validar documentos jurídicos, LGPD, base legal de consulta e fluxos de aceite.

## 17. Estado atual do repositório

Acesso: `staff` (contas de administração e vendedores), `clientes` (empresas
contratantes com login, situação e controle de sessão), `tentativas_login`
(bloqueio progressivo por conta e origem) e `sessions`.

Catálogo: `versoes_catalogo` (tabela de preços datada, com situação e
congelamento), `servicos` (código, nome comercial, categoria e trava de
liberação), `precos` (preço de venda e custo interno por serviço e faixa, dentro
de uma versão), `planos` (versão contratada, mensalidade, consumo mínimo) e
`franquias_plano` (quantidade incluída por serviço).

Os preços dos anexos A e B entram pelo `CatalogoSeeder` como **rascunho**, a
partir de `database/seeders/dados/`, gerado por `tools/gera_precos_catalogo.py`.
Ativar a versão é ação administrativa consciente — o seeder nunca ativa.

As telas de catálogo (`/catalogo`, restritas a administração) têm três abas:

- **Planos** — cadastro de plano, faixa contratada e franquia por serviço;
- **Catálogo** — a matriz de preços por faixa, em três visões (venda, custo e
  margem), com edição direta, reajuste percentual e alíquota de imposto;
- **Serviços** — cadastro, renomeação, categoria, ativação e trava de liberação
  jurídica.

Serviço não é excluído, apenas desativado: franquia de plano, consulta e fatura
apontam para ele, e apagar levaria a franquia junto por cascata e deixaria
histórico órfão. O código do serviço é imutável depois da criação.

Ainda futuros: `consultas`, `faturas` e `itens_fatura`, `cobrancas_asaas` e
`eventos_asaas`, `comissoes`, `bonus_cadastro` e `repasses`, `campanhas` e
`elegibilidade`, `documentos` e `aceites_documento`, `chamados`.

A navegação já expõe Consultas, Histórico, Empresas clientes, Financeiro e
Auditoria como módulos futuros (`emBreve`). Isso reflete o plano estrutural, mas
permite que a entrega seja feita por fases sem quebrar o fluxo de uso.

## Anexo A — Preços de referência: crédito

> Transcrito da tabela de crédito do fornecedor (`temp/`). Valores unitários
> em reais, por faixa de consumo mínimo contratada. Provisórios até homologação
> comercial. Serviços marcados com `*` dependem de liberação do SCR.

| Serviço | Sem mínimo | 75 | 200 | 500 | 900 | 1.500 | 5.000 |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| Cheques sem fundos — Banco Central PF/PJ | 2,09 | 1,88 | 1,70 | 1,53 | 1,37 | 1,24 | 0,98 |
| Ações judiciais — nacional PF/PJ | 5,94 | 5,22 | 4,60 | 4,05 | 3,56 | 3,13 | 2,34 |
| SCPC BVS PF/PJ — Base III | 6,31 | 5,94 | 5,58 | 5,24 | 4,93 | 4,63 | 3,70 |
| Relatório Plus PF/PJ + cartórios e CCF Bacen — Base III | 7,81 | 7,42 | 7,05 | 6,70 | 6,36 | 6,04 | 5,40 |
| Crédito Net Básica PF/PJ — Base I | 15,93 | 15,13 | 14,37 | 13,66 | 12,97 | 12,32 | 10,97 |
| Mix PF/PJ — Base I e II | 18,52 | 17,41 | 16,37 | 15,39 | 14,46 | 13,60 | 12,01 |
| Crédito Net PF/PJ — Base I e III | 18,97 | 17,83 | 16,76 | 15,75 | 14,81 | 13,92 | 12,26 |
| Crédito Net Top + cartórios e CCF Bacen — Base I e III | 21,91 | 20,59 | 19,36 | 18,20 | 17,10 | 16,08 | 14,21 |
| Relatório Score Positivo + filtros — Base III | 9,93 | 9,44 | 8,97 | 8,52 | 8,09 | 7,69 | 6,94 |
| Risco de Crédito Top PF/PJ + filtros — Base I | 20,95 | 19,69 | 18,51 | 17,40 | 16,36 | 15,38 | 13,59 |
| Relatório Top PF/PJ + filtros — Base I e III | 28,57 | 27,43 | 26,33 | 25,28 | 24,27 | 23,30 | 21,25 |
| Relatório Top + SCR Bacen — Base I e III * | 44,50 | 40,94 | 37,67 | 34,65 | 31,88 | 29,33 | 26,99 |
| Maxi Top PF/PJ + score e filtros — Base I e II | 28,15 | 27,03 | 25,95 | 24,91 | 23,91 | 22,96 | 20,94 |
| Relatório Prime Básica + cartórios e CCF Bacen — Base I, II e III | 26,70 | 25,10 | 23,59 | 22,18 | 20,85 | 19,60 | 17,09 |
| Relatório Prime Completa + filtros — Base I, II e III | 37,05 | 35,20 | 33,44 | 31,77 | 30,18 | 28,67 | 25,60 |
| Relatório Prime Completa + SCR Bacen — Base I, II e III * | 52,89 | 48,66 | 44,77 | 41,18 | 37,89 | 34,86 | 32,42 |
| SCR Bacen + score PF/PJ * | 20,03 | 18,83 | 17,70 | 16,64 | 15,64 | 14,70 | 12,99 |
| Cadastro especial PF — endereço, telefone, e-mail, trabalho, renda | 3,03 | 2,73 | 2,46 | 2,21 | 1,99 | 1,79 | 1,45 |
| Cadastro especial PJ — dados da empresa, sócios, regime fiscal, faturamento | 3,03 | 2,73 | 2,46 | 2,21 | 1,99 | 1,79 | 1,45 |
| Telefones por CPF/CNPJ | 1,11 | 1,00 | 0,90 | 0,81 | 0,73 | 0,66 | 0,53 |
| Endereços por CPF/CNPJ | 1,11 | 1,00 | 0,90 | 0,81 | 0,73 | 0,66 | 0,53 |
| InfoBusca por CPF/CNPJ — telefone, endereço e e-mails | 2,07 | 1,86 | 1,67 | 1,51 | 1,36 | 1,22 | 0,99 |
| InfoBusca por nome (mostra CPF) | 2,07 | 1,86 | 1,67 | 1,51 | 1,36 | 1,22 | 0,99 |
| Localizador por telefone (mostra nome e CPF/CNPJ) | 2,07 | 1,86 | 1,67 | 1,51 | 1,36 | 1,22 | 0,99 |
| Localizador por CEP (mostra nomes e CPF/CNPJ) | 2,07 | 1,86 | 1,67 | 1,51 | 1,36 | 1,22 | 0,99 |
| Negativação | 17,90 | 17,90 | 17,90 | 17,90 | 17,90 | 17,90 | 17,90 |

Os filtros extras citados nos nomes são score, faturamento e renda presumida,
pontualidade de pagamento, balanço máximo e mínimo, quantidade de funcionários,
endereços, telefones e pessoas de contato.

## Anexo B — Preços de referência: veicular

> Transcrito da tabela veicular do fornecedor (`temp/`). Mesmas regras do
> anexo A.

| Serviço | Sem mínimo | 75 | 200 | 500 | 900 | 1.500 | 5.000 |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| Localiza veículos por CPF/CNPJ | 21,56 | 20,48 | 19,46 | 18,49 | 17,56 | 16,68 | 14,26 |
| Histórico de proprietário (somente São Paulo) | 14,30 | 13,87 | 13,45 | 13,05 | 12,66 | 12,28 | 11,55 |
| Proprietário atual | 5,94 | 5,76 | 5,59 | 5,42 | 5,26 | 5,10 | 4,80 |
| Agregados | 3,63 | 3,52 | 3,42 | 3,31 | 3,21 | 3,12 | 2,93 |
| RenaJud | 10,55 | 10,13 | 9,73 | 9,34 | 8,96 | 8,61 | 7,93 |
| RenaInf — infrações completa | 8,97 | 8,61 | 8,27 | 7,94 | 7,62 | 7,31 | 6,74 |
| CRLV — documento de licenciamento | 23,78 | 23,07 | 22,37 | 21,70 | 21,05 | 20,42 | 19,21 |
| BIN — base estadual e nacional | 5,96 | 5,72 | 5,49 | 5,27 | 5,06 | 4,86 | 4,48 |
| Leilão — Base I | 11,94 | 11,58 | 11,23 | 10,89 | 10,57 | 10,25 | 9,54 |
| Leilão conjugado completo + score do veículo — Base II | 21,95 | 21,29 | 20,65 | 20,03 | 19,43 | 18,84 | 17,55 |
| CSV — certificado de segurança veicular | 7,95 | 7,71 | 7,48 | 7,25 | 7,03 | 6,82 | 6,35 |
| Histórico de roubo e furto | 7,99 | 7,67 | 7,37 | 7,07 | 6,79 | 6,52 | 5,94 |
| Gravame indicativo | 7,95 | 7,63 | 7,32 | 7,03 | 6,75 | 6,48 | 5,97 |
| Gravame indicativo + agregados | 11,00 | 10,56 | 10,14 | 9,73 | 9,34 | 8,97 | 8,27 |
| Confere RG/CNH | 5,88 | 5,70 | 5,53 | 5,37 | 5,21 | 5,05 | 4,75 |
| Precificador / decodificador | 5,95 | 5,77 | 5,60 | 5,43 | 5,27 | 5,11 | 4,81 |
| VIP Car — informação completa do veículo | 55,30 | 53,64 | 52,03 | 50,47 | 48,96 | 47,49 | 44,68 |
