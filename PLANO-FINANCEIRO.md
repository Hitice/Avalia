# Plano do financeiro

Estado em 29/09/2026. Este documento diz **para onde o financeiro vai e em que
ordem**. Regra de negocio fica na `PDD.md`; como publicar, na `DEPLOY.md`.

## 1. O que existe, e em que categoria cai

"SAP" nao e categoria: e uma suite de ERP. A comparacao util e com os modulos
dela, e o nosso desenho responde a dois, **FI** (contabilidade financeira) e
**CO** (controladoria, resultado por produto e por responsavel). A tabela abaixo
classifica as 48 tabelas do banco pelo modulo equivalente.

| Modulo | Equivalente SAP / ERP | Tabelas nossas |
|---|---|---|
| Razao contabil | FI-GL | `contas_financeiras`, `lancamentos_financeiros`, `partidas_financeiras` |
| Contas a receber | FI-AR | `faturas`, `itens_fatura`, `pedidos_360`, `parcelas_360`, `lancamentos_360` |
| Tesouraria e banco | FI-BL | `cobrancas_asaas`, `eventos_asaas` |
| Controladoria | CO | `socios`, resultado hoje recalculado em tela |
| Cadastro de cliente | SD / mestre | `clientes`, `operadores`, `staff`, `produtores` |
| Catalogo e preco | SD-MM | `servicos`, `planos`, `precos`, `franquias_plano`, `versoes_catalogo` |
| Venda e consumo | SD | `consultas`, `adesoes`, `produtos_360`, `ofertas_360` |
| Produto fisico | MM | `etiquetas`, `lotes_etiquetas`, `links`, `destinos_etiqueta`, `renovacoes_etiqueta`, `acessos_etiqueta` |
| CRM | CRM | `leads`, `lead_staff`, `interessados`, `interessados_cobranca`, `campanhas`, `campanha_cliente`, `campanha_servico` |
| Integracao externa | (nenhum) | `conexoes` |
| Documento e aceite | (nenhum) | `documentos`, `aceites_documento` |
| Governanca | (nenhum) | `auditoria`, `tentativas_login` |
| Infra do Laravel | (nenhum) | `cache`, `cache_locks`, `sessions`, `jobs`, `job_batches`, `failed_jobs` |

Leitura disso: **o ERP esta razoavelmente coberto e o CRM esta cru.** Sete
tabelas de CRM sem processo em cima, contra um financeiro que ja tem partidas
dobradas. Nao e falta de peca; e peca no lugar errado.

## 2. O diagnostico

Nao ha tres razoes competindo por falta de razao. **O razao existe e esta
correto**: `lancamentos_financeiros` com `partidas_financeiras` e partida dobrada
de verdade, com plano de contas em `contas_financeiras`, origem unica por
`(origem_tipo, origem_id)`, estorno por `estorna_id` e autor em `staff_id`. E o
desenho do FI-GL.

O problema e outro, e e um so:

> **O razao existe mas nao e a fonte da verdade.** Resultado mora como coluna nos
> documentos de operacao, e nao como saldo de conta.

`faturas` guarda `imposto_cents`, `custo_cents`, `lucro_cents` e
`comissao_cents`. `itens_fatura` guarda custo e valor por item.
`lancamentos_360` guarda `tipo` e `valor_cents` sem conta contabil. Sao
**resultados gravados dentro do documento**, e resultado gravado em documento
tem que ser recalculado por quem quiser somar.

Dai saem, em linha reta, os numeros que doem:

- **24 arquivos** tocam comissao, 11 tocam lucro, 10 calculam imposto
- a divergencia de **R$ 169,84 contra R$ 148,61** na mesma tela, com 899 testes
  passando, porque cartao e tabela somavam por caminhos diferentes
- o vies de centavo no reparte, que so apareceu quando a divisao saiu da venda
  para o mes

Nenhum desses e bug de calculo. Todos sao o mesmo defeito estrutural: **varios
lugares com direito de calcular o mesmo numero.**

## 3. O alvo

Uma regra, e ela decide tudo o que vem depois:

> Todo evento de dinheiro **lanca no razao**. Todo relatorio **le do razao**.

Documento de operacao (`faturas`, `pedidos_360`, venda de etiqueta) continua
existindo e continua sendo onde a operacao acontece. Ele deixa de ser onde o
resultado mora: passa a ser **subrazao que lanca**, como AR lanca em GL em
qualquer ERP serio. Coluna derivada que sobrar vira cache, e cache tem prova.

Isso nao pede tabela nova, nao pede stack nova e nao perde dado. E o padrao que
SAP FI, NetSuite, Odoo e ERPNext implementam, e o que os sistemas de livro texto
(`ledger`, `beancount`) implementam sem mais nada.

## 4. Ordem de execucao

**Fase 0. Congelar a dispersao.** Feito. `tests/Unit/DiretivaTest.php` recusa um
quarto razao, trava a densidade de comentario em 30,6% e a aderencia de tela em
51%, so para baixo e so para cima respectivamente.

**Fase 1. Plano de contas completo. FEITO.** Havia caixa, receita e despesa. Falta receita, custo, imposto, comissao a pagar e a receber **por
produto** (One, Gestor, plaquinha), mais patrimonio por socio. Conta nasce em
migration, com codigo estavel, porque conta criada sob demanda em runtime e como
a `ContasFinanceirasSeeder` que nunca rodou: producao respondeu "A conta caixa
nao esta cadastrada".

**Fase 2. Regras de lancamento em um lugar. FEITA PARA A PLAQUINHA.**
`app/Contabil/Lancar` e agora o unico escritor do razao, e
`RegistrarLancamento` delega a ele: a invariante de soma zero virou o
construtor de `Partidas`, em vez de viver dentro do portao das naturezas.
`VendaDeEtiqueta` traduz a venda em seis pernas e le o reparte de
`RepartePlaquinha`, e o painel passou a ler a mesma funcao. `SociosDaPlaquinha`
saiu do controller pelo mesmo motivo. Faltam os outros oito eventos:
consulta executada, fatura fechada, fatura liquidada, parcela paga, comissao
apurada, comissao paga, aporte e retirada.

O desenho, para eles: `app/Contabil/`, uma classe por
evento de negocio (consulta executada, fatura fechada, fatura liquidada, parcela
paga, etiqueta vendida, comissao apurada, comissao paga, aporte, retirada). Cada
uma devolve as partidas e nada mais. `RegistrarLancamento` continua sendo o unico
portao de escrita, e continua recusando o que nao fecha em zero.

**Fase 3. Lastro do historico. FEITA PARA A PLAQUINHA.**
`avalia:lastrear-plaquinhas` le as etiquetas vendidas e lanca no razao, com
`--simular` para conferir antes. Teste prova que rodar duas vezes nao duplica e
que nenhuma coluna de `etiquetas` muda. Falta o lastro de `faturas`,
`pedidos_360` e `lancamentos_360`.

A regra, para eles: as vendas e os links que ja existem sao
intocaveis, entao o lastro **le e nunca escreve** neles: percorre `faturas`,
`pedidos_360`, `lancamentos_360` e as vendas de etiqueta e lanca no razao com
`origem_tipo`/`origem_id` apontando para o documento. O indice unico de origem ja
torna a operacao repetivel sem duplicar, o que significa que ela pode rodar em
producao mais de uma vez sem medo. Comando proprio, somente com esse fim.

**Fase 4. Conciliacao com prova. FEITA PARA A PLAQUINHA.** O teste
`LastroDasPlaquinhasTest` compara o saldo das contas do razao contra a soma que
o painel mostra, e recusa a divergencia. Ele ja pagou: a primeira versao de
`VendaDeEtiqueta` descontava a comissao do caixa alem de registra-la como
passivo, contando o mesmo dinheiro duas vezes, e o lancamento nao fechou em
zero. O erro morreu antes de existir em producao.

Falta o resto: um comando e um teste que comparem, por
competencia, a coluna gravada no documento contra o saldo da conta no razao, e
listam a diferenca. E o controle de subrazao contra GL que todo ERP tem, e e o
que transforma a classe de erro dos R$ 169,84 em coisa **detectada** em vez de
coisa descoberta por acidente.

**Fase 5. Relatorio le do razao.** Dashboard de socios, painel de vendas QR,
visao geral e fechamento passam a somar saldo de conta. O recalculo sai dos 24
arquivos. Cada arquivo limpo baixa `TETO_DE_COMENTARIO` na mesma mudanca.

**Fase 6. Coluna derivada sai.** Somente depois que nenhum relatorio a le, e
depois que a Fase 4 passou limpa por uma competencia fechada. `imposto_bps` fica,
porque taxa na emissao e dado historico, nao resultado.

## 5. O que nao se toca

`etiquetas`, `links`, `lotes_etiquetas`, `destinos_etiqueta`,
`renovacoes_etiqueta`, `acessos_etiqueta` e as primeiras vendas. QR impresso
aponta para link publicado: link que muda e plaquinha que morre no bolso do
cliente. Toda fase aqui e aditiva sobre essas tabelas.

`leads`, `interessados`, `campanhas` e o resto do CRM podem ser reorganizados
sem cerimonia, porque o dado atual nao tem valor de operacao.

## 6. Onde isto e cobrado

```bash
php vendor/bin/pest tests/Unit/DiretivaTest.php   # catraca das diretivas
php artisan avalia:inventario                      # linhas por tabela
php artisan avalia:conferir-integridade            # cadeia de auditoria
```

Diretiva de escrita (codigo, tela, documento) fica na skill `enxugar`.
