# Auditoria AvaliaOne — Etapa A, primeira passada

Commit `5754c8f` · 29/09/2026 · Laravel 12.26.4 · PHP 8.5.10 · MySQL em produção

Escopo desta passada: reconciliação da PDD com o código, nos conflitos da seção 3
do prompt que podiam ser resolvidos com evidência. Não é a Etapa A inteira. O que
ficou de fora está listado no fim, nomeado.

## Como ler a classificação

| Marca | Significa |
|---|---|
| **código** | Confirmado lendo o código, com arquivo e linha. |
| **execução** | Confirmado rodando: suíte, cálculo ou requisição a produção. |
| **doc** | A PDD afirma e ninguém verificou. |
| **obsoleto** | A PDD afirma e o código já contradiz. |
| **decisão** | Depende de alguém decidir, não de programar. |

## Estado real, medido

| Afirmação da PDD | Medido | Marca |
|---|---|---|
| "A suíte tem 460 testes" (§17) | **929 testes, 3371 asserções, 0 falhas**, 21,7s | execução |
| Laravel, versão não declarada | 12.26.4, PHP 8.5.10 | execução |
| — | 167 rotas web, 62 migrations, 34 models, 41 actions, 96 arquivos de teste | execução |

## Conflitos resolvidos

### 1. O piso de preço está certo no código; o exemplo da PDD está velho

A PDD §6 diz que "Endereços por CPF/CNPJ", com custo de R$ 0,85, tem piso de
R$ 0,93. O prompt suspeitou de erro de fórmula (valor usado como alíquota).

Não é erro de fórmula. **R$ 0,93 é o piso da alíquota antiga de 8,60%**, que a
própria PDD §5 diz ter sido substituída por 13,50% em 05/08/2026. Com a alíquota
vigente o piso é **R$ 0,98**.

    custo 0,85 · alíquota  8,60%  ->  piso R$ 0,93   (exato 0,929978)
    custo 0,85 · alíquota 13,50%  ->  piso R$ 0,98   (exato 0,982659)

E há um detalhe a favor do código: a fórmula contínua daria 0,982659, que
arredondado para cima é R$ 0,99. O código devolve R$ 0,98, e está **mais certo
que a fórmula**: `Margem::precoAlvoCents` ([app/Support/Margem.php:120](app/Support/Margem.php#L120))
faz busca discreta em centavos depois de aplicar a fórmula, porque o imposto é
arredondado ao centavo. A 98 centavos o imposto é `round(13,23) = 13`, e
`98 − 13 − 85 = 0`: cobre exatamente. Cobrar 99 seria um centavo a mais do que a
regra exige.

**Correção:** trocar o exemplo na PDD §6 de R$ 0,93 para R$ 0,98. Nada no código.
**Regressão:** já coberta por `MargemTest`.

### 2. O exemplo de comissão de R$ 30 contradiz a própria fórmula, e o código

A PDD §9 afirma: cliente com mínimo de R$ 900 que consome R$ 300 "paga R$ 979,90
de fatura e gera R$ 30,00 de comissão, não R$ 97,99", e conclui "o vendedor ganha
sobre uso".

O código faz outra coisa, e faz o que a fórmula da própria §9 manda
([FecharCompetencia.php:55-72](app/Actions/Consumo/FecharCompetencia.php#L55-L72)):

    total    = mensalidade + max(realizado, minimo)   = 79,90 + 900,00 = 979,90
    imposto  = 13,5% × total                          = 132,29
    lucro    = total − imposto − custo
    comissao = 10% × lucro

Com custo do fornecedor perto de zero sobre os R$ 300 consumidos, a comissão fica
em torno de **R$ 84,76**, não R$ 30. Para dar R$ 30 o custo teria de ser
R$ 547,61 sobre um consumo de R$ 300, ou seja, custo maior que a receita que ele
gera: impossível.

De onde vem o R$ 30: é 10% de R$ 300, o consumo realizado. **É a regra antiga**,
comissão sobre uso, que a §16 diz ter sido substituída por comissão sobre lucro
em 05/08/2026. O exemplo e a frase "ganha sobre uso" são sobreviventes da mesma
troca que deixou o piso velho no item 1.

**Isto importa além do documento.** A §16 registra como pendência: "Falta constar
do contrato do vendedor: a comissão saiu de 10% do consumo para 10% do que sobra".
Se algum vendedor foi contratado com o texto antigo, o que está escrito promete
R$ 30 e o sistema paga R$ 84,76 neste exemplo, e o inverso em outros. **Não é
divergência documental apenas: é exposição contratual.**

**Correção:** refazer o exemplo pela fórmula vigente e apagar "ganha sobre uso".
**Decisão de vocês:** conferir o que foi assinado com Warley.

### 3. Os conectores existem. A PDD §17 está obsoleta

A §17 lista em "O que não existe": "Conector de consulta ao fornecedor". As §7 e
§11 descrevem conectores reais. O código dá razão às §7 e §11:

    app/Contracts/ConectorBureau.php
    app/Services/Conectores/ConectorSerasa.php
    app/Services/Conectores/ConectorBoaVista.php
    app/Services/Conectores/ConectorSimulado.php
    app/Services/Conectores/EscolherConector.php

**Correção:** §17 sai. O que de fato falta é credencial e homologação, não código.
**Não verificado nesta passada:** se `EscolherConector` roteia por serviço ou pela
"primeira conexão ativa", que o prompt aponta como risco de mandar o produto ao
fornecedor errado. Fica na lista do fim.

### 4. A checagem "Raiz do servidor em public" não verifica a raiz do servidor

O prompt suspeitou de frase invertida. Não está invertida: está **verificando
outra coisa**.

[ConferirAmbiente.php:167-174](app/Console/Commands/ConferirAmbiente.php#L167-L174)
decide por `! str_contains($url, '/public')`, ou seja, lê a string do `APP_URL`.
Um servidor apontado para a raiz do projeto continua tendo
`APP_URL=https://avaliaone.com.br`, sem `/public` nenhum, e a checagem imprime
`ok` enquanto o `.env` está acessível pela web.

**Em produção o risco não existe.** Provado por requisição:

    /.env                     403  (página do servidor)
    /.git/config              403  (mesma página)
    /storage/logs/laravel.log 403  (bloqueio da camada de segurança)
    /composer.json            404  com a página 404 DO LARAVEL
    /artisan                  404  idem
    /vendor/autoload.php      404  idem

O 404 do Laravel em `/composer.json` é a prova: a requisição chegou ao roteador da
aplicação em vez de encontrar o arquivo, o que só acontece com a raiz em `public/`.

**Severidade:** não é vulnerabilidade, é rede de proteção furada. O `ok` que o
deploy imprime não prova o que diz. Eu mesmo já citei essa linha como evidência em
publicações anteriores, e ela não sustentava a afirmação.

**Correção mínima:** trocar a heurística por verificação de fato, conferindo se um
arquivo sensível responde fora de `public/`, ou comparando `public_path()` com a
raiz servida. **Regressão:** teste que simula raiz errada e exige reprovação.

## O que NÃO foi verificado nesta passada

Nomeado para não virar falso conforto:

- **Isolamento entre empresas e carteiras** — a matriz papel × ação × recurso do
  prompt §4 não foi construída. Não testei troca de ID, payload, PDF nem export.
- **Roteamento de fornecedor** (`EscolherConector`) — item 3 acima.
- **Asaas**: correlação de webhook, deduplicação, ordem e reprocessamento.
- **Concorrência**: clique repetido, última unidade de franquia, fechamento
  concorrente, janela de 120s.
- **Estorno de comissão já liberada** — a PDD §19 chama de "o buraco mais grave do
  fluxo financeiro" e eu não confirmei nem desmenti.
- **Retenção de 180 dias** — se o expurgo roda e o que ele preserva.
- **Custos reais** (prompt §6) — nada levantado.
- **Diretivas** (prompt §7) — a §18c da PDD lista 10 constantes em código; confirmei
  que a tela de parâmetros expõe só a alíquota, mas não auditei cada uma.

## Próximos passos sugeridos, por retorno

1. **Conferir o contrato do Warley** contra a regra de comissão vigente (item 2).
   É o único achado com exposição financeira concreta hoje.
2. **Isolamento entre empresas**, que é a pergunta de segurança que sobra quando
   o produto tem mais de um cliente.
3. **Estorno de comissão liberada**, pela gravidade que a própria PDD atribui.
4. **Roteamento de fornecedor**, antes de a primeira credencial real entrar.

Nenhuma afirmação aqui equivale a "sistema auditado" ou "seguro". São quatro
conflitos resolvidos com evidência, de uma lista de dezoito.
