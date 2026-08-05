---
name: padroes
description: Padroes de construcao do Avalia. Consulte antes de criar controller, tela, regra de negocio ou migration, para que o codigo novo saia igual ao que ja existe: onde cada regra mora, dinheiro em centavos, vocabulario de UI do tema, e estilo de comentario e de texto.
---

# Como se constroi no Avalia

Laravel 12 sobre o boilerplate TailAdmin. O boilerplate foi escolhido para ser
**seguido**, nao contornado. Codigo novo deve parecer escrito pela mesma pessoa
que escreveu o resto.

## Onde cada coisa mora

| Camada | Responsabilidade | Exemplo |
|---|---|---|
| `routes/web.php` | So declaracao. Grupo por guard e por papel. | `Route::middleware('admin')` |
| `Http/Controllers` | Fluxo HTTP: montar a tela, chamar a acao, traduzir resultado em mensagem. Sem regra. | `CatalogoController` |
| `Http/Requests` | Validacao e normalizacao da entrada. | `ServicoRequest` |
| `Actions/<Modulo>` | Uma regra de negocio, classe com `__invoke`, transacional. | `GravarServicoCompleto` |
| `Support` | Calculo puro, sem banco, testavel isolado. | `Dinheiro`, `Margem`, `Comissao` |
| `Enums` | Conjunto fechado de valores, com `rotulo()` e `tentar()`. | `Categoria` |
| `Models` | Persistencia, relacoes e leitura para tela. | `Catalogo::faixasDe()` |

Regra que decide dinheiro nunca fica em Blade nem em controller. Se a acao pode
recusar o pedido, ela devolve o motivo em array (`['piso' => int|null]`) e quem
traduz para o usuario e o controller.

## Dinheiro

Centavos inteiros do banco ate a tela. Float nao entra: `0.1 + 0.2` nao da `0.3`,
e em cobranca isso vira divergencia de centavo em fatura.

- coluna termina em `_cents`; taxa em pontos base (`_bps`)
- formata com `Dinheiro::brl()`, `Dinheiro::numero()` em campo de formulario,
  `Dinheiro::faixa()` para rotulo de consumo minimo
- le entrada do operador com `Dinheiro::paraCentavos()`, que aceita
  `1.234,56`, `1234,56`, `1234.56` e `1234`
- a mesma regra de apresentacao mora em **um** lugar. Repetida em quatro telas,
  uma delas sempre fica para tras (foi o que produziu o "R$ 0,00")

## Tela

Estilo repetido vira `@utility` em `resources/css/app.css`, na familia das
utilities do tema. O Blade usa o nome semantico, e o nome aparece **literal**,
nunca montado em tempo de execucao.

Vocabulario ja existente: `cartao`, `tabela`, `tabela-cabecalho`,
`tabela-cabecalho-fixo`, `tabela-rolagem`, `tabela-th`, `tabela-td`,
`tabela-vazia`, `campo`, `campo-linha`, `campo-celula`, `rotulo-campo`,
`ajuda-campo`, `erro-campo`, `etiqueta` + `etiqueta-{neutra,alerta,erro}`,
`aviso` + `aviso-{ok,erro,alerta}`, `botao` + `botao-{primario,secundario,sm}`,
`segmento-grupo`, `segmento` + `segmento-{ativo,inativo}`, `rotulo-grupo`,
`interruptor` + `interruptor-{ligado,desligado,bolinha}`, `menu-badge-embreve`.

Componentes proprios em `resources/views/components/avalia/`: `botao`,
`segmentado`, `interruptor`, `logotipo`.

Convencoes de interacao ja decididas:

- **editar linha a linha**, um botao Editar por linha, nunca matriz de campos
- acao de um clique (ligar/desligar) usa `x-avalia.interruptor`, que grava no
  proprio clique sem abrir formulario
- botoes irmaos tem o mesmo tamanho e o mesmo peso
- tabela longa fica dentro de `tabela-rolagem` com cabecalho fixo
- rotulo de menu e curto, senao quebra na sidebar de 290px

Depois de mexer em Blade ou CSS: `npm run build`.

## Texto e comentario

- **Nenhum travessao** em codigo, tela, dado ou documento. Nem em commit.
- Codigo em portugues sem acento (`versaoNaSessao`, `precoDe`); texto de tela com
  acento correto.
- Comentario explica **por que**, nao o que. O que ja esta na linha abaixo. Vale
  registrar a decisao e o erro que ela evita, como em `Dinheiro` e em `Preco`.
- Nada de enfeite de IA: sem "robusto", sem emoji, sem parabens ao proprio
  codigo, sem repetir o obvio.

## Banco

- migration nova nunca reescreve historia: tabela renomeada mantem a migration
  antiga com o nome antigo
- nao existe exclusao onde ha historico (servico, plano). Existe desativacao:
  consulta e fatura antigas precisam continuar explicaveis
- consulta e fatura gravam preco e custo **na emissao**. E isso, e nao travar a
  tabela, que impede reajuste de hoje de mudar cobranca de ontem

## Teste

Pest, um arquivo por assunto, secoes separadas por comentario de bloco. Nome do
teste diz a regra de negocio, nao o metodo (`it('recusa o lote inteiro quando um
preco fura o piso')`). Teste de calculo puro vai em `tests/Unit`.
