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

Vocabulario ja existente: `titulo-pagina`, `subtitulo-pagina`, `titulo-cartao`,
`titulo-secao`, `flutuante` (menu da conta, modal, caixa de login), `cartao`, `tabela`, `tabela-cabecalho`,
`tabela-cabecalho-fixo`, `tabela-rolagem`, `tabela-th`, `tabela-td`,
`tabela-vazia`, `campo` (44px, igual ao `botao`; select ganha a seta da casa por `select.campo`), `campo-linha`, `campo-celula`, `rotulo-campo`,
`ajuda-campo`, `erro-campo`, `etiqueta` + `etiqueta-{neutra,alerta,erro}`,
`aviso` + `aviso-{ok,erro,alerta}`, `botao` + `botao-{primario,secundario,sm}`,
`segmento-grupo`, `segmento` + `segmento-{ativo,inativo}`, `rotulo-grupo`,
`interruptor` + `interruptor-{ligado,desligado,bolinha}`, `menu-badge-embreve`.

Componentes proprios em `resources/views/components/avalia/`: `botao`,
`segmentado`, `interruptor`, `logotipo`, `medidor`, `tema`, `icone`,
`cartao-indicador`, `grafico-vendas-dia`, `paginacao`.

Icone vem de **um** mapa, `App\Support\Icones`, por nome em portugues
(`<x-avalia.icone nome="lapis" />`); o menu lateral usa o mesmo. Icone novo
entra no mapa, nunca inline na view, e `IconesTest` cobra que todo item de menu
aponte para um nome que existe.

### Tela nova nasce com as tres partes

Toda tela, publica ou nao, repete a mesma moldura. Nao ha licenca para inventar
uma variacao por pagina: quem navega entre elas percebe a diferenca e conclui
que saiu do site.

- **Topo** de 60px (`h-[60px]`), conteudo em `mx-auto w-full max-w-[87rem] px-6`,
  com a marca a esquerda, assinando com o produto da tela (`marca="..."`).
  A direita fica a acao da tela (Voltar, Sair, Entrar), com
  `pr-12 sm:pr-14 min-[1550px]:pr-0` para reservar o canto.
- **Interruptor de tema em toda tela DO SISTEMA**, via `<x-avalia.tema />`, e sempre na
  PONTA EXTREMA: `class="absolute top-1/2 right-3 size-11 -translate-y-1/2
  sm:right-4"` dentro do `<header>` (que precisa ser `relative` ou `fixed`).
  Fora do alinhamento das colunas de proposito, porque e ferramenta da pagina e
  nao passo do funil, e a posicao diz isso. Tela sem topo usa
  `absolute top-4 right-3 size-11 sm:right-4`.

  O componente existe porque o botao vivia escrito a mao dentro da pagina
  inicial: as telas do 360 nasceram sem ele, e o tema escuro existia sem jeito
  de alternar. Depois nasceram com ele no lugar errado, dentro do nav, porque
  este arquivo dizia isso.
- **Rodape** com a marca a esquerda, contato a direita e, abaixo, uma linha de
  registro: `© ano`, razao social, CNPJ e `Empresa::localidade()`. Endereco
  completo so em documento (fatura, laudo, contrato), nunca no pe da pagina.

O texto institucional sai sempre de `App\Support\Empresa` e de
`config/empresa.php`, e nunca escrito na view: CNPJ e endereco copiados em tela
foi exatamente o que deixou tres lugares com o numero velho quando a empresa
trocou de CNPJ.

### O site institucional e a excecao do tema

A casa tem um site publico, em `paginas/site/`, sobre `layouts/site.blade.php`.
Ele **vive no tema claro e so nele**, e por isso nao leva `<x-avalia.tema />` e
nao escreve variante `dark:`. O preto que ele usa e superficie escolhida dentro
do tema claro, e nao o tema escuro ligado: bastaria o navegador lembrar do
escuro do CRM para o site abrir com metade de cada tema.

A excecao e reconhecida pelo caminho em `TemaEscuroTest`, entao pagina nova do
site nasce coberta. Fora desse diretorio a regra do interruptor continua
valendo inteira, e `RevisaoDeSegurancaTest` cobra.

### Quatro marcas, e nenhuma escrita a mao

- **Avalia**, a casa: `Empresa::marca()`
- **Avalia One**, pesquisa de score: `Empresa::marcaCredito()`
- **Avalia Gestor**, venda parcelada: `Empresa::marcaCobranca()`
- **Avalia Sales**, vendas de rua: `Empresa::marcaVendas()`
- **Avalia Gestão**, o back office: `Empresa::marcaGestao()`

O logotipo sai de `<x-avalia.logotipo marca="casa|credito|cobranca|vendas|gestao" />`:
o desenho vem de `App\Support\Marca` (arco e ponteiro do dono) e o nome escrito
de `texto-bureau`. A lateral escolhe marca e menu pela rota
(`MenuHelper::naSales()`). As marcas ja mudaram juntas uma vez: escritas a mao
nas telas, sobrou "Avalia 360" em pagina que ja falava de outro produto.

Porta fechada (403) devolve a pessoa para a tela de origem com aviso; tela
inteira so para quem digitou o endereco. Item de menu que leva a 403 nao
existe: `papeis`, `exigeFinanceiro`, `exigeSocios`, `exigeSales`.

Nome de classe, tabela, rota e migration **nao** acompanham a troca de marca:
`Produto360`, `Lancamento360` e `/cobranca` continuam com o nome que tem.
Migration nao reescreve historia, e URL publica trocada e link quebrado no
bolso de quem ja recebeu o antigo.

Convencoes de interacao ja decididas:

- **modal e `<x-avalia.modal aberto="..." fechar="...">`**, sempre: no celular
  vira folha que sobe do pe da tela, no desktop caixa centrada; Esc, clique fora e
  o X fecham. `ModalTest` recusa overlay escrito a mao. Modal sem recheio proprio
  (`:recheio="false"`) quando o conteudo traz o cabecalho.

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
