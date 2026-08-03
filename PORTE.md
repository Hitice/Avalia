# Porte para Laravel — regras que não podem se perder

O código Node foi arquivado em `legado/`. O CSS e as telas a gente descarta sem dó.
Este documento existe porque a **regra de negócio** não está documentada em lugar
nenhum além do código — e ela custou a maior parte do trabalho até aqui.

Cada item abaixo tem o arquivo de origem. Quando eu reimplementar em PHP, é contra
isto que vou conferir.

---

## 1. Faturamento — `legado/src/billing.js`

A conta que sustenta o produto inteiro:

```
fatura = mensalidade + max(consumo_mínimo, consumo_realizado)
```

O cliente paga a mensalidade **sempre**, e paga o maior entre o mínimo contratado e
o que de fato consumiu. Se consumiu abaixo do mínimo, a diferença (`complemento`)
entra assim mesmo.

Constantes reais, verificadas contra a tabela de preços da Bancredi:

| Constante | Valor |
|---|---|
| `FAIXA_COMISSAO_CENTS` | `90_000` (R$ 900,00) |
| `COMISSAO_ATE_FAIXA` | 20 % |
| `COMISSAO_ACIMA_FAIXA` | 15 % |
| `DIAS_ATE_VENCER` | 10 |
| `DIAS_CARENCIA` | 5 |

### A armadilha da comissão

A faixa lê o **`consumo_minimo_cents` do plano**, nunca o valor da fatura:

```js
pctComissao = (consumo_minimo_cents) =>
  consumo_minimo_cents > 90_000 ? 15 : 20
```

Isto não é detalhe de implementação. Se alguém juntar `mensalidade` e
`consumo_minimo` num campo só, o plano de R$ 900 vira R$ 949 e **cai
silenciosamente de 20 % para 15 %** — o vendedor perde comissão e ninguém percebe.
Os dois campos ficam separados no schema. Sempre.

### Dinheiro

Tudo em **centavos, inteiro**. Nunca float. No Laravel: coluna `bigInteger`, e um
cast dedicado para exibição. `brl()` formatava com espaço não-quebrável (U+00A0)
entre `R$` e o número.

---

## 2. Competência e fuso — `legado/src/tempo.js`

`FUSO = America/Sao_Paulo`.

Este arquivo nasceu de um **bug de dinheiro real**: `criado_em` era gravado em UTC
(`datetime('now')`) enquanto a competência era calculada em São Paulo. Uma consulta
feita em 31/07 às 22:00 (SP) virava 01/08 em UTC e era **cobrada no mês errado**.

No Laravel: `config/app.php → 'timezone' => 'America/Sao_Paulo'`, e nenhuma consulta
usando `NOW()` do banco sem conversão.

`fechaCompetencia()` **recusa mês em andamento** salvo `--forcar`. Fechar congela
consumo, valores e comissões.

---

## 3. Autenticação

- Hash **scrypt** (Laravel usa bcrypt/argon2 — trocar é aceitável, é upgrade).
- Cookie HttpOnly assinado com HMAC carregando `tipo:id:sessao_versao`.
- `sessao_versao` é o mecanismo de **revogação**: incrementa a coluna e todas as
  sessões daquele usuário morrem. Precisa sobreviver ao porte — é o que permite
  bloquear um cliente inadimplente na hora.
- Dois tipos de conta no mesmo login: `staff` (admin/vendedor) e `empresa` (cliente).
  No Laravel: dois guards, ou um guard com polimorfismo.

### Proteção contra força bruta — `legado/src/protecao.js`

Conta tentativas **por conta e por origem**. Castigo exponencial:
`min(30min, 60s × 2^(n − limite))`.

---

## 4. Boa Vista / Equifax — `legado/src/bureau.js`

Descoberto empiricamente, não chutado. **Não invente endpoint nem payload.**

- OAuth2 `client_credentials` com **Basic Auth** (client_id:secret no header).
- Caminho: `/business/reporting-orchestrator/v1/consulta`
- Headers obrigatórios: `app`, `secondaryCode`
- O Orchestrator **não está publicado no sandbox**. 401 em produção = rota existe;
  404 no sandbox = não existe. Não adianta procurar caminho alternativo.
- `secondaryCode` ainda está no placeholder `00000000`.
- O mapeamento de campos em `normaliza()` é **suposição não verificada** — as
  credenciais de TEST ainda não chegaram.

Credenciais preservadas em `legado/.env` (chaves `BOAVISTA_*`). Portar para o
`.env` do Laravel na mesma nomenclatura.

### Escopo — decisão explícita do produto

> "tudo relacionado a api é para buscar dados, não para disponibilizar dados na
> plataforma"

A Avalia **consome** o bureau. Ela **nunca expõe API pública**. Isso elimina a
necessidade de API interna e é o motivo de Blade server-rendered ser a escolha certa
aqui, e não um SPA.

---

## 5. Consulta à prova de queda — `legado/src/routes.js`

A consulta grava `status = 'processando'` **antes** de chamar o bureau.

Sem isso, uma queda no meio da chamada significa "pagou a Boa Vista e não cobrou do
cliente". No Laravel: gravar dentro de uma transação, ou usar Job com registro
prévio.

---

## 6. LGPD — `legado/src/retencao.js`

`RETENCAO_DIAS = 180`. O expurgo zera `consultas.resposta`, marca
`resposta_expurgada_em` e **mantém os metadados** (quem consultou, quando, quanto
custou) — porque metadado é registro fiscal, o retorno do bureau é dado pessoal.

Rodava no boot + a cada 24 h. No Laravel isso vira **um comando agendado** no
`routes/console.php` — bem mais limpo que o `setInterval` com `unref()`.

---

## 7. CNPJ alfanumérico

RFB 2.229/2024. DV por mod-11 sobre `ASCII − 48`. O CNPJ passa a aceitar letras.
Validador pronto em `legado/src/` — portar o algoritmo, não a sintaxe.

---

## 8. Auditoria — `legado/src/auditoria.js`

13 verbos em `ACOES`. `diferenca(antes, depois, campos)` gera `campo: antes -> depois`.
`registra()` **nunca lança exceção** — auditoria que derruba a operação auditada é
pior que auditoria nenhuma.

---

## 9. Log — `legado/src/log.js`

Filtro obrigatório de segredos:

```js
const PROIBIDOS = /senha|secret|token|authorization|api[_-]?key|cookie/i;
```

No Laravel: middleware de log + `config/logging.php` com processor equivalente.
Isto não é opcional — as credenciais da Equifax passam por essas requisições.

---

## 10. Config que recusa subir

`legado/src/config.js` **impede o boot em produção** com `SESSION_SECRET` padrão,
secret curto, cookie inseguro ou `ADMIN_SENHA=admin123`. Em dev, só avisa.

Equivalente Laravel: uma checagem no `AppServiceProvider::boot()` ou um health check
que falha o deploy.

---

## 11. Testes

199 verificações em `legado/test.mjs`, mais o `legado/stub-boavista.mjs` (Equifax
falso que fala o contrato real).

**O stub tem valor independente da linguagem** — é a única forma de testar a
integração sem credencial. Portar para um fake HTTP no Pest.

O teste isolava o banco via `DB_PATH → data-teste/`. Isso surgiu depois de o teste
ter **apagado o banco de produção duas vezes**. No Laravel: `phpunit.xml` já isola
com `DB_DATABASE=:memory:`, mas confira antes de rodar a primeira vez.

---

## Dados preservados

- `legado/data/avalia.db` — banco real (clientes e planos cadastrados)
- `legado/avalia-backup-2026-08-03.db` — cópia solta
- `legado/.env` — credenciais Equifax

A migração dos dados para Postgres/Supabase é passo separado, depois do schema
Laravel estar de pé.
