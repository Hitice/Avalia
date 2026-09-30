---
name: enxugar
description: Diretivas de contencao para codigo, tela e documento neste repositorio. Use antes de criar arquivo, classe, tela, utility, tabela de dinheiro ou documento novo, e ao revisar o que ja existe. Traz os limites medidos que tests/Unit/DiretivaTest.php cobra.
---

# Contencao

O modo de falhar da escrita assistida por IA neste repositorio nao e erro: e
volume. Medicao de fora confirma o padrao, sobre 623 milhoes de linhas alteradas
entre 2023 e 2026, com commits de IA em um quarto do total: blocos duplicados
subiram 81%, a fatia de codigo reescrito em duas semanas foi de 33% para 40%, e
as oito metricas de manutenibilidade acompanhadas cairam juntas. Escrever de
novo sai mais barato que procurar, entao a IA escreve de novo. O resultado nao
quebra o teste; espalha a mesma regra por arquivos que discordam.

O equivalente na tela tem nome na literatura de design system: **design drift**.
A divergencia nao entra por decisao, e sim por tradutor no meio do caminho,
variante sem dono e token sobrescrito caso a caso. A correcao recomendada nao e
documentar melhor, e detectar. Documento envelhece porque ninguem reabre
documento antes de escrever a proxima classe.

Daqui sai a forma destas diretivas: cada uma tem um numero, e o numero e cobrado
por teste.

## O que este repositorio mediu

| Sintoma | Medida | Onde dói |
|---|---|---|
| Comentario sobre codigo | **30,6%** de `app/` (6.062 de 19.790 linhas) | `Support/Comissao.php` 65%, `Support/CodigoCurto.php` 62%, `Support/Rateio.php` 61%, `Support/RepartePlaquinha.php` 60%, `Support/Caixa.php` 56% |
| Tela fora do vocabulario | 1.242 usos do tema contra **1.187** de aparencia escrita a mao, em 1.011 combinacoes distintas de `class` | 108 `@utility` existem e perdem para Tailwind cru |
| Mesma regra em muitos lugares | **24 arquivos** tocam comissao, 11 tocam lucro, 10 calculam imposto | divergencia de R$ 169,84 contra R$ 148,61 na mesma tela |
| Razoes contabeis paralelos | **3** para o mesmo dinheiro | `lancamentos_360`, `lancamentos_financeiros` + `partidas_financeiras`, `faturas` + `itens_fatura` |

Um numero desses so muda para baixo. `tests/Unit/DiretivaTest.php` guarda os
limites e falha na subida, com a mensagem dizendo o que fazer.

## Diretivas

**1. Procure antes de escrever.** Regra de dinheiro, formatacao, validacao e
utility de tela quase sempre existem. `grep` no termo em portugues antes de criar
a classe. Foi a falta disso que pos comissao em 24 arquivos, e foi por rodar a
aplicacao, nao por 899 testes passando, que a divergencia apareceu.

**2. Comentario tem teto.** Explique **por que**, e o erro que a decisao evita.
Nunca o que a linha abaixo ja diz. Cabecalho de arquivo, `@param` que repete a
assinatura e secao ASCII em classe de 40 linhas saem. Ao limpar um arquivo, baixe
`TETO_DE_COMENTARIO` na mesma mudanca.

**3. Aparencia sai de `@utility`.** Cor, borda, arredondamento, sombra e peso de
fonte usam o nome semantico de `resources/css/app.css`. Layout (`flex`, `grid`,
`gap`) continua na view, de proposito. Combinacao nova de aparencia repetida duas
vezes vira utility na segunda.

**4. Uma regra, um lugar.** Calculo de dinheiro mora em `app/Support`, e tela,
relatorio e dashboard leem de la. Dois lugares que calculam o mesmo numero
divergem; a pergunta nao e se, e quando.

**5. Dinheiro entra no razao que existe.** Com subrazao, nunca em tabela propria.
O teste recusa um quarto razao.

**6. Texto de tela e curto.** Sem tutorial, sem explicar o obvio, sem elogio ao
proprio codigo, sem emoji, sem travessao. O usuario e adulto e conhece o
negocio.

**7. Documento novo so quando nenhum existente cobre.** `PDD.md` diz o negocio,
`README.md` poe para rodar, `DEPLOY.md` publica, `PLANO-FINANCEIRO.md` diz para
onde o financeiro vai. Assunto novo entra no que ja trata dele. Prosa do
repositorio tambem tem catraca, so que manual: se voce somou linha de documento,
diga quanto e por que.

## Ao terminar

```bash
php vendor/bin/pest tests/Unit/DiretivaTest.php
npm run build   # se mexeu em Blade ou CSS
```
