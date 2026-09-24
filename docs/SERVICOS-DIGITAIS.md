# Serviços digitais - QR dinâmico e NFC

Plano de construção do primeiro micro sistema da nova frente, e do padrão que
os próximos vão seguir.

Escrito em 24/09/2026, contra o repositório em `main`. Decisões da seção 2
respondidas por você em 24/09.

---

## 1. O negócio, em uma frase

Você produz plaquinhas com um código impresso, vende por R$ 79,90, e **só
depois** aponta aquele código para a URL do cliente. Quem comprou nunca
reimprime nada: quando o destino muda, muda no sistema. No ano seguinte, a
plaquinha renova por R$ 49,90.

Isso define a peça central: **o código impresso é um endereço permanente da
Avalia, não o link do cliente**. A plaquinha carrega `avaliaone.com.br/q/K7M2PX`
para sempre; o que a gente troca é para onde esse endereço redireciona.

E é isso que resolve o NFC também, sem editor nenhum: a tag NFC da plaquinha é
gravada com **a mesma URL curta** do QR. Ela nunca precisa ser regravada depois
da venda. "QR dinâmico" e "NFC dinâmico" são o mesmo sistema, com dois jeitos
de ler o mesmo código.

O editor de tag NFC é outra coisa, e é o **serviço 2**: gravar conteúdo
arbitrário (URL, vCard, Wi-Fi, texto) numa tag qualquer, pelo navegador do
celular. Não depende do sistema de plaquinhas.

### As três coisas que você vende

| | Produto | Preço | O que é |
|---|---|---|---|
| 1 | Plaquinha QR/NFC | R$ 79,90 no primeiro ano | Placa física mais o redirecionamento |
| 2 | Renovação anual | R$ 49,90/ano | O redirecionamento continua de pé |
| 3 | QR dinâmico avulso | R$ 19,90/mês | Só o código, sem placa |

A diferença entre o item 2 (R$ 49,90 por ano) e o item 3 (R$ 238,80 por ano)
fica de pé porque os dois públicos não se encontram: quem compra a placa não
entra no nosso site, compra de você e some. Vale só lembrar de **não exibir as
duas tabelas na mesma página** quando a vitrine for escrita.

---

## 2. Decisões tomadas

### 2.1 Domínio: o principal, `avaliaone.com.br/q/{codigo}`

Fechado. Domínio curto só se o negócio escalar, e aí a gente porta.

**O tamanho do código não importa, e isso muda a sua conclusão sobre os 9999.**
Fiz a conta: quem manda no tamanho do QR é o domínio, não o código.

Gravando em maiúsculas (modo alfanumérico do QR, veja 5.3):

```
HTTPS://AVALIAONE.COM.BR/Q/            27 caracteres, só o prefixo
HTTPS://AVALIAONE.COM.BR/Q/1234        31 caracteres, 4 dígitos
HTTPS://AVALIAONE.COM.BR/Q/K7M2PX      33 caracteres, 6 caracteres
```

A versão 3 do QR (29x29 módulos) no nível de correção H aguenta **35
caracteres**. Ou seja: 4 dígitos e 6 caracteres caem **exatamente no mesmo QR**,
do mesmo tamanho, com a mesma densidade. O código de 4 dígitos não economiza
nada.

Então pegamos a segurança de graça: **6 caracteres do alfabeto Crockford
base32** (`0123456789ABCDEFGHJKMNPQRSTVWXYZ`, sem `I`, `L`, `O`, `U`, que se
confundem com `1`, `0`), sorteados. Dá 1,07 bilhão de códigos, não dá para
varrer por incremento, e não tem teto para ninguém esbarrar em 2029.

**Código nunca é reciclado.** Plaquinha que venceu e não renovou não devolve o
código para o bolo. Se devolvesse, o cliente antigo que renovar em atraso
mandaria a freguesia dele para a loja de um estranho.

### 2.2 Ativação: só a Avalia, com login master

Fechado, opção A. Não existe PIN, não existe raspadinha, não existe tela
pública de ativação. Vendeu, você abre o painel e cola a URL.

Isso simplifica bastante: sai uma tabela de token, sai uma rota pública, sai
todo o problema de alguém cadastrar plaquinha do estoque.

"Login master" no vocabulário do sistema é o papel `admin`. O vendedor não vê
o módulo.

### 2.3 O cliente não mexe

Fechado. Trocar destino é serviço seu, pedido por WhatsApp. Nenhuma tela de
cliente, nenhum link mágico, nenhum guard novo.

### 2.4 Cobrança: R$ 79,90 na venda, R$ 49,90 por ano

Fechado. Isso torna `vence_em` um campo vivo, e abre um ciclo de vida que a
seção 6 descreve inteiro: aviso antes, carência depois, página de renovação com
a marca da Avalia quando cai.

### 2.5 Gerador grátis: sim

Fechado. Cartão na seção **Serviços digitais** da página `/softwares`, levando
para `/servicos-digitais/qr-code`. QR estático, sem cadastro, sem banco. É a
isca: quem gerou um estático e descobriu que não dá para trocar o destino é o
cliente do dinâmico.

Mesmo cartão, mesmo molde, para os serviços que vierem depois.

### 2.6 Correção de erro: H, com o logo da Avalia no miolo

Fechado quanto ao logo. Mas **subi o nível de M/Q para H**, e vale explicar por
quê.

Logo no meio do QR não é enfeite: é dado apagado. O leitor reconstrói o que o
logo cobre usando a correção de erro, e o que ele gasta nisso ele não tem mais
para gastar em risco de acrílico, tinta espalhada e reflexo de vitrine. Com Q
(25%) e logo, você começa com a margem já comprometida antes de a placa sair da
oficina.

Com H (30%) sobra margem para as duas coisas. E, pela conta da 2.1, **H cabe no
mesmo QR versão 3 que o Q caberia**: 33 caracteres contra os 35 que a versão 3
em H aguenta. Não custa densidade nenhuma.

O logo fica limitado a ~10% da área, centralizado, com um anel branco de
respiro. Nunca encosta nos três quadrados dos cantos.

### 2.7 Corel: continua SVG

Você disse que se for difícil demais pode ser PNG. Não é difícil: um QR é uma
grade de quadrados, e juntar tudo num caminho só é meia dúzia de linhas. Faço
SVG **e** PNG no mesmo ZIP, e você usa o que preferir na hora da arte.

### 2.8 Maiúsculas: convertidas na geração

Fechado. A URL vai para o QR em maiúsculas; a busca no servidor é insensível a
caixa.

### 2.9 Carência de 30 dias, aviso único aos 15

Fechado. Passou de `vence_em`, a plaquinha **continua redirecionando por 30
dias**, e só então cai na página de renovação. Quem esquece de pagar não
derruba a loja do cliente no dia seguinte.

O aviso é **um só, 15 dias antes** do vencimento. Um aviso que chega sozinho é
lido; três avisos viram ruído e o terceiro é ignorado junto com o primeiro.

### 2.10 Avulso mensal, cobrado por boleto no Asaas

Fechado. R$ 19,90 por mês, assinatura recorrente no Asaas, que é o provedor que
o Avalia Gestor já usa. A seção 3 mostra o que disso já está pronto no
repositório.

---

## 3. Cobrança: o que já existe e o que falta

Boa notícia: **a máquina de assinatura mensal já está de pé.**

`AsaasClient::criarAssinatura()` já fala com `/subscriptions`, e
`AssinarServico` já cria assinatura `MONTHLY` com primeiro vencimento
respeitando o mínimo de 3 dias do provedor. O `WebhookAsaasController` já trata
`PAYMENT_RECEIVED`, `PAYMENT_CONFIRMED` e `PAYMENT_OVERDUE`, e é à prova de
reentrega.

O que muda para os serviços digitais:

- **Sem split.** `AssinarServico` reparte com a rede de produtores via
  `Cascata::split()`. Aqui o dinheiro é 100% da Avalia: `split` vai vazio, e é
  o caso mais simples que existe.
- **`externalReference` com prefixo próprio.** Hoje o webhook casa o evento
  com a parcela pelo prefixo `a360-`. A etiqueta precisa do seu, `qr-`, e o
  webhook precisa **despachar por prefixo** em vez de assumir que todo evento é
  do Gestor. Sem isso, um pagamento de QR vira uma parcela órfã.
- **Renovação anual da placa** é cobrança avulsa (`criarCobranca`), não
  assinatura: é uma vez por ano e quase sempre negociada no WhatsApp.

### O boleto de R$ 19,90, e o ajuste que eu recomendo

Uma observação, e depois sigo com a sua decisão.

Boleto é o pior instrumento justo para o valor mais baixo: tem taxa fixa por
emissão (que em R$ 19,90 come perto de 10% da receita), e depende de o cliente
lembrar de pagar todo mês. Assinatura de valor baixo morre de esquecimento,
não de cancelamento.

O conserto é uma palavra: mandar `billingType` como **`UNDEFINED`** em vez de
`BOLETO`. O Asaas então abre uma página onde o próprio cliente escolhe entre
boleto, Pix e cartão. **O boleto continua lá para quem quiser**, e quem
escolher cartão passa a pagar sozinho todo mês, sem lembrete e sem
inadimplência por esquecimento.

Recomendo `UNDEFINED` para o avulso. A assinatura do Gestor fica como está, que
é decisão de outro produto.

---

## 4. As regras que não podem falhar

Não são preferência. Errar qualquer uma quebra o produto.

1. **O redirecionamento é `302`, nunca `301`.** `301` é permanente: navegador e
   provedor guardam para sempre, e a plaquinha congela no primeiro destino
   mesmo depois de você trocar. É o oposto do produto. Junto, mandar
   `Cache-Control: no-store, must-revalidate`.
2. **Código sorteado, nunca sequencial**, no alfabeto Crockford de 6
   caracteres, com unicidade garantida por índice único no banco.
3. **Código nunca é reciclado**, nem depois de vencido, nem depois de baixado.
4. **Busca insensível a maiúscula.** O QR grava em MAIÚSCULAS de propósito, e
   ninguém digita igual ao impresso.
5. **Nenhum estado devolve 404.** Em branco, suspensa, vencida e inexistente
   têm, cada uma, sua página da Avalia. A página do comprador que leu a
   plaquinha antes de você ativar é vitrine, não erro.
6. **Plaquinha não é apagada**, pela regra da casa. Só suspensa ou baixada.
7. **Toda troca de destino fica no histórico**, com quem, quando, de onde para
   onde. `Auditar::registrar` faz metade; a tabela de destinos faz a outra.
8. **`/q/*` fora do sitemap e com `noindex`.**
9. **IP de leitura não é guardado cru.** É dado pessoal.
10. **Regeneração idêntica.** O mesmo código produz o mesmo QR daqui a três
    anos, quando o cliente pedir reimpressão da placa quebrada. Sai de graça se
    a geração for determinística, e nenhuma imagem precisa ser guardada.

---

## 5. Geração dos códigos

### 5.1 No navegador, não no servidor

Ditado pela hospedagem: o servidor da Hostinger **não tem composer nem npm**, e
`vendor/` não vai no git. Instalar uma biblioteca PHP de QR significaria subir
`vendor` à mão, sem SSH. Já `public/build/` é versionado, então **biblioteca JS
empacotada pelo Vite chega pronta em produção**.

O servidor gera e guarda os **códigos**; o navegador desenha o QR, monta o ZIP
e baixa. De quebra tira 100 renderizações do CPU compartilhado e dá prévia
instantânea.

- `qrcode-generator` (MIT, minúsculo, sem dependências) devolve a matriz de
  módulos, e a gente desenha o SVG do nosso jeito.
- `jszip` monta o pacote.

### 5.2 O SVG tem que ser bom para o Corel

- **Um único `<path>`** com todos os módulos e `fill-rule="evenodd"`, não um
  `<rect>` por módulo. Mil retângulos viram mil objetos no Corel; um path vira
  uma curva só.
- **Tamanho absoluto em milímetros** (`width="30mm"` mais `viewBox`), para cair
  no Corel no tamanho físico e não em pixels de 96dpi.
- **Preto puro `#000000`**, sem stroke, sem opacidade, sem filtro.
- **Zona de silêncio de 4 módulos dentro da geometria.** Sem ela no desenho,
  quem monta a arte encosta o QR na borda da placa e ele para de ler.
- O logo entra como grupo separado, para você poder apagar no Corel se a placa
  pedir outra coisa.
- PNG junto, 2000px, fundo branco chapado, para quem preferir.

### 5.3 Por que a URL vai em maiúsculas

O QR tem um modo alfanumérico que só aceita maiúsculas, dígitos e alguns
símbolos, e ele é bem mais compacto que o modo byte. Em minúsculas, a mesma URL
não cabe no modo alfanumérico e o código cresce de versão.

Domínio é insensível a caixa por definição, então nenhum leitor se importa. O
caminho não é, e por isso a regra 4.

### 5.4 Geração em massa

Tela de lote: quantidade (1 a 1000), título, tipo, observação. Grava numa
transação e leva para a página do lote.

ZIP:

```
lote-0007/
  0001-K7M2PX.svg
  0001-K7M2PX.png
  0002-R4T8W2.svg
  ...
  lote-0007.csv
```

O CSV traz `sequencia`, `codigo`, `url`. É ele que alimenta o **Print Merge do
Corel**: você monta a placa uma vez, aponta para o CSV e para a pasta de SVGs,
e o Corel numera e troca o QR na tiragem inteira.

O ZIP é regerável a qualquer momento a partir da página do lote, porque nada é
guardado: tudo é determinístico a partir do código.

E uma tela de **prova**: o QR no tamanho real declarado, para você ler da tela
com o celular antes de mandar cortar 500 placas de acrílico.

---

## 6. Ciclo de vida da plaquinha

O que a resposta 2.4 abriu. Seis estados, e cada um tem uma tela.

| Situação | Quando | O que o leitor vê |
|---|---|---|
| `em_branco` | Recém gerada, no estoque | Página "plaquinha ainda não ativada", com a marca e o que é o produto |
| `ativa` | Vendida e apontada | Redirecionamento `302` |
| `carencia` | Passou de `vence_em`, dentro dos 30 dias | Redirecionamento `302` normal, e você é avisado |
| `vencida` | Passou dos 30 dias de carência | Página de renovação, R$ 49,90, marca da Avalia |
| `suspensa` | Você suspendeu à mão | Página "fora do ar, fale com o responsável" |
| `baixada` | Placa quebrada, cliente saiu | Página neutra. O código não volta ao bolo |

`carencia` não é coluna: é `ativa` com `vence_em` no passado e dentro dos 30
dias. Estado que se calcula não sai de sincronia com a data.

**O aviso de 15 dias** roda num comando `artisan` diário
(`avalia:etiquetas-vencendo`), no mesmo cron que já publica. Comando, e não
job, porque a hospedagem compartilhada não mantém processo de pé e a fila roda
em modo síncrono. O comando é idempotente: grava `avisada_em` e nunca manda o
mesmo aviso duas vezes, porque cron repetido é a regra e não a exceção.

**Renovar** é uma ação de uma linha no painel: empurra `vence_em` em um ano,
grava a renovação com o valor cobrado e registra na auditoria.

**Um cuidado na página de renovação.** Quem lê uma plaquinha vencida no balcão
da loja é o freguês do lojista, não o lojista. Uma página que diz "renove por
R$ 49,90" para o freguês deixa o seu cliente numa situação ruim. O desenho que
resolve os dois lados: o aviso neutro em cima ("este endereço está fora do ar
no momento"), com a marca da Avalia, e a chamada de renovação embaixo,
endereçada a quem é responsável pela plaquinha. A marca aparece do mesmo jeito,
que era o que você queria, e ninguém passa vergonha.

---

## 7. Isso pesa na Hostinger?

Você perguntou. Respondendo com número, não com "deve dar".

Uma leitura de plaquinha é: uma rota, uma busca em cache, um `302`. Sem view,
sem sessão, sem Blade. O custo real não é a consulta, é o boot do Laravel, que
com `config:cache` e `route:cache` fica na casa de 20 a 40ms na compartilhada.
Hoje o site responde em ~1,6ms com cache quente.

Mil plaquinhas em campo, 20 leituras por dia cada uma, dão **20 mil requisições
por dia**, ou 0,23 por segundo na média. Mesmo concentrando tudo em três horas
de pico, dá menos de 2 por segundo. A compartilhada engole isso sem perceber.

O que pesaria, e que o plano já evita:

- **Uma linha por leitura no banco.** Uma placa em porta de loja movimentada
  geraria dezenas de milhares de linhas por mês. Por isso o agregado diário:
  um `upsert` indexado somando 1, e a tabela cresce uma linha por placa por dia.
- **Robô inflando tudo.** WhatsApp, Telegram e Instagram buscam a URL para
  montar a prévia do link. Filtrar user agent conhecido e ignorar `HEAD`, senão
  o cliente vê 40 leituras de uma placa que ninguém olhou.
- **Renderizar QR no servidor.** Não vai acontecer: é tudo no navegador.

O limite que realmente pode doer na compartilhada não é CPU, é o teto de
processos PHP simultâneos do plano. Se um dia uma plaquinha entrar em anúncio
de TV, o gargalo vai ser esse. Vale saber qual é o teto do plano Premium antes
de prometer disponibilidade a alguém.

**Custo adicional hoje: zero.** Nenhum serviço novo, nenhum pacote pago,
nenhuma fila.

---

## 8. Modelo de dados

Cinco tabelas. MySQL em produção: nada de tipo exclusivo de um dialeto.

### `lotes_etiquetas`
`id`, `codigo` (`LT-0007`), `titulo`, `quantidade`, `tipo` (`qr`, `nfc`,
`qr_nfc`), `observacao`, `staff_id`, `timestamps`.

### `etiquetas`
`id`, `codigo` (6 chars, único), `lote_id` (nulo se avulsa), `sequencia`,
`tipo`, `situacao`, `destino` (nulo enquanto em branco), `titulo` (apelido
interno: "Padaria do Zé - balcão"), `cliente_nome`, `cliente_contato`,
`vendida_em`, `vence_em`, `avisada_em`, `valor_cents`, `gravada_em` (quando a
tag NFC foi escrita), `asaas_subscription_id` (só no avulso mensal),
`total_acessos`, `ultimo_acesso_em`, `staff_id`, `timestamps`.

`total_acessos` e `ultimo_acesso_em` são cópias, para a listagem não somar nada.

### `destinos_etiqueta`
`etiqueta_id`, `destino`, `vigorou_de`, `vigorou_ate`, `staff_id`. A linha
aberta é a atual; o campo `destino` da etiqueta é a cópia rápida para o
redirecionamento não precisar de join.

### `renovacoes_etiqueta`
`etiqueta_id`, `valor_cents`, `de`, `ate`, `staff_id`, `created_at`. Responde
"quanto essa placa já rendeu" sem inventar relatório depois.

### `acessos_etiqueta`
Agregado **diário**: `etiqueta_id`, `dia`, `total`, único em
(`etiqueta_id`, `dia`).

---

## 9. Método de implantação, e o padrão para os próximos

Você disse que virão outros micro sistemas. O que vale definir agora não é o
QR: é o molde.

### 9.1 Um catálogo, dois consumidores

`config/servicos-digitais.php`, no espírito de `config/softwares.php`: uma
entrada por serviço, com `titulo`, `resumo`, `texto`, `icone`, `rota`,
`publico` e `interno`. Dela saem, sem duplicação:

- a seção **Serviços digitais** na página `/softwares`;
- a página pública de cada serviço;
- o submenu **Serviços digitais** na lateral do CRM.

É o problema que `config/softwares.php` já resolveu: o cartão prometia um nome
e a seção entregava outro.

### 9.2 Onde cada coisa fica

```
config/servicos-digitais.php
app/Models/Etiqueta.php                 + LoteEtiqueta, DestinoEtiqueta, RenovacaoEtiqueta
app/Enums/SituacaoEtiqueta.php          com rotulo() e tentar(), como Categoria
app/Support/CodigoCurto.php             alfabeto, sorteio, normalização
app/Actions/Etiquetas/GerarLote.php     transacional
app/Actions/Etiquetas/TrocarDestino.php
app/Actions/Etiquetas/AtivarEtiqueta.php
app/Actions/Etiquetas/RenovarEtiqueta.php
app/Console/Commands/EtiquetasVencendo.php
app/Http/Controllers/EtiquetaController.php
app/Http/Controllers/RedirecionadorController.php
resources/js/qr.js                      matriz, SVG, PNG, ZIP
resources/js/nfc.js                     NDEFReader
resources/views/paginas/servicos-digitais/
```

Regra do molde: regra de negócio em `Actions`, cálculo puro em `Support`,
controller só traduz HTTP. Nada de regra em Blade.

### 9.3 Rotas

```php
// Pública, fora de qualquer grupo, e a mais curta possível.
Route::get('/q/{codigo}', RedirecionadorController::class)
    ->where('codigo', '[0-9A-Za-z]{6}')
    ->name('q');

// A vitrine.
Route::prefix('servicos-digitais')->name('digitais.')->group(function () {
    Route::get('/', ...)->name('index');
    Route::get('/qr-code', ...)->name('qr');    // gerador estático grátis
    Route::get('/nfc', ...)->name('nfc');       // editor de tag
});

// A gestão.
Route::middleware(['auth:staff', 'sessao:staff', 'admin'])
    ->prefix('etiquetas')->name('etiquetas.')->group(function () { ... });
```

`/q/` fora do grupo e sem middleware pesado: é a rota que mais vai rodar.
Cachear `codigo => destino` por 60 segundos evita ida ao MySQL em toda leitura,
e 60 segundos é atraso aceitável numa troca de destino (a tela avisa).

### 9.4 Menu

`MenuHelper::getMainNavItems()` ganha um item com `subItems`, que a barra
lateral já sabe desenhar (o Blade suporta, nenhum item usa ainda):

```php
['icon' => 'qr', 'name' => 'Serviços digitais', 'papeis' => ['admin'], 'subItems' => [
    ['name' => 'Plaquinhas', 'path' => '/etiquetas'],
    ['name' => 'Lotes',      'path' => '/etiquetas/lotes'],
    ['name' => 'Gravar NFC', 'path' => '/etiquetas/nfc'],
]],
```

Ícone novo em `getIconSvg`. Rótulo curto, senão quebra na lateral de 290px.

### 9.5 Ordem de construção

1. `CodigoCurto` e os testes dele.
2. Migrações, modelos e enum.
3. **A rota `/q/{codigo}`** e os seis estados. É o coração: primeiro a ser
   construído e primeiro a ser testado.
4. Lote, geração em massa, ZIP, CSV, tela de prova.
5. Listagem, ativação, troca de destino com histórico e auditoria.
6. Vencimento: comando de aviso aos 15 dias, carência de 30, renovação e
   página de renovação.
7. Gravação NFC em lote, no celular.
8. Vitrine no site e gerador grátis.
9. Assinatura do avulso mensal no Asaas, e o despacho por prefixo no webhook.
10. Editor de tag NFC (serviço 2).

---

## 10. NFC: o que dá e o que não dá

**Gravar tag pelo navegador só funciona no Chrome do Android** (89 ou mais
novo) e nos outros navegadores Chromium do Android. **iPhone não grava**: a
Apple tem NFC nativo para app, mas nunca colocou Web NFC no Safari, em nenhuma
versão, nem no iPad, nem no Mac.

**Ler, o iPhone lê.** Do iPhone XS em diante, encostar numa tag NDEF com URL
abre uma notificação, sem app nenhum. **A plaquinha funciona para o cliente de
iPhone.** O que não funciona no iPhone é a ferramenta de gravação, que é sua e
fica na sua bancada, com um Android.

- A tela de gravação detecta `'NDEFReader' in window`. Sem ele, mostra o aviso
  e **um QR da própria página**, para continuar no Android. A mensagem para
  iPhone tem que ser específica, senão vira chamado de suporte.
- Exige HTTPS e gesto do usuário. `scan()`, `write()` e `makeReadOnly()` cada
  um dispara pedido de permissão.
- **Capacidade importa.** NTAG213 tem 144 bytes úteis, NTAG215 tem 504,
  NTAG216 tem 888. A URL curta cabe em qualquer uma; vCard e Wi-Fi estouram a
  213 com facilidade. O editor calcula o payload e avisa **antes** de gravar.
- **`makeReadOnly()` é irreversível.** Trava a tag para sempre: não tem reset
  de fábrica, não tem gravador que desfaça. Nunca é o padrão. Caixinha marcada
  de propósito, com confirmação escrita.
- Antes de gravar, **ler**: mostrar o que já está na tag. Regravar por cima do
  trabalho de outro é o erro mais caro da bancada.
- Web NFC só fala NDEF. Tag crua de outro sistema não vai.
- `scan()` fica ativo: abortar com `AbortController` ao sair da tela.
- Tag travada, permissão negada, tag cheia e tag afastada cedo demais são
  quatro problemas diferentes, e "erro ao gravar" não ajuda ninguém.

**Gravação em lote**, que é a parte que vale dinheiro: abre a página do lote no
Chrome do Android, ela lista os códigos em fila, você encosta a tag, ela grava
a URL curta, marca `gravada_em` e pula para o próximo. Cem tags sem tocar na
tela.

---

## 11. O que ainda precisa de atenção na construção

- **O webhook do Asaas precisa despachar por prefixo.** Hoje ele assume que
  todo evento é do Avalia Gestor e casa por `a360-`. Assim que a etiqueta
  emitir cobrança, evento de QR vira parcela órfã se isso não for ajustado
  antes.
- **Destino precisa de validação.** Só `http` e `https`. Bloquear
  `javascript:`, `data:`, endereço que aponte de volta para o encurtador
  (laço) ou para rota interna do painel.
- **Atalhos de destino** valem mais que campo de URL livre: WhatsApp (monta
  `https://wa.me/55...?text=`), Instagram, **avaliação no Google** (o maior
  vendedor de plaquinha que existe), cardápio em PDF, vCard, link próprio.
- Cardápio em PDF e logo do cliente exigem upload, e upload exige
  `storage:link` funcionando em produção. Conferir antes de prometer.
- **Código legível impresso embaixo do QR.** Quando o cliente liga, a primeira
  pergunta é "qual o código da sua plaquinha".
- **Privacidade**: a página de privacidade precisa mencionar o serviço de
  redirecionamento e a contagem de leituras.
- **Se `avaliaone.com.br` cair, toda plaquinha em campo morre.** Você está
  ciente. No mínimo, monitoramento da rota `/q/`.
- **Testes Pest**, no mínimo: `302` e não `301`; `no-store` no cabeçalho;
  código em minúsculo encontra a etiqueta; em branco devolve a página de
  ativação e não 404; suspensa não redireciona; vencida dentro da carência
  ainda redireciona; vencida fora da carência mostra renovação; lote de 100
  gera 100 códigos distintos; troca de destino grava histórico e auditoria;
  código nunca é reciclado.

---

## 12. O prompt

Para colar numa sessão nova.

```
Leia docs/SERVICOS-DIGITAIS.md e .claude/skills/padroes/SKILL.md antes de
escrever qualquer linha.

Construa o módulo Serviços digitais do Avalia, começando pelo QR dinâmico de
plaquinha física, seguindo aquele documento: ciclo de vida da seção 6, modelo
de dados da seção 8, geração no navegador da seção 5, estrutura e rotas da
seção 9, e na ordem da seção 9.5.

As dez regras da seção 4 não são negociáveis. A primeira, redirecionamento 302
com Cache-Control no-store, quebra o produto inteiro se sair errada: escreva o
teste dela antes do código.

Decisões já fechadas (seção 2):
- domínio avaliaone.com.br/q/{codigo}, código de 6 caracteres Crockford base32
  sorteado, URL gravada em maiúsculas, QR nível H com logo da Avalia no miolo
- só o admin cadastra e altera destino. Sem PIN, sem ativação pública, sem
  tela de cliente
- venda R$ 79,90 com um ano incluso, renovação R$ 49,90 por ano
- gerador de QR estático público e grátis em /servicos-digitais/qr-code
- SVG e PNG no mesmo ZIP

- carência de 30 dias ainda redirecionando, e um aviso único 15 dias antes
- avulso de R$ 19,90 por mês como assinatura no Asaas, reaproveitando
  AsaasClient::criarAssinatura, sem split, externalReference com prefixo qr-,
  e billingType UNDEFINED para o cliente escolher entre boleto, Pix e cartão

Nada está em aberto. Se algo no documento parecer ambíguo, pergunte antes de
escolher por conta própria.

Siga o padrão da casa: regra de negócio em Actions com __invoke e transação,
cálculo puro em Support, controller só traduz HTTP, nada de regra em Blade.
Vocabulário de CSS existente (cartao, tabela, campo, etiqueta, botao), utility
nova em resources/css/app.css se precisar, nome literal no Blade. Português sem
acento no código, com acento na tela. Nenhum travessão em lugar nenhum.
Comentário explica por quê, não o quê. Nada de exclusão onde há histórico.
Migração compatível com MySQL.

Não instale pacote composer: o servidor não tem composer e vendor não vai no
git. Biblioteca JS pode, porque public/build é versionado; use
qrcode-generator e jszip, e rode npm run build ao final.

Entregue nas dez etapas da seção 9.5, parando para eu conferir depois de cada
uma. Ao final de cada etapa: php -d memory_limit=1G vendor/bin/pest,
npm run build, e um resumo do que mudou. Na suíte, só `failed` importa: o PHP
8.5 marca centenas de testes como `deprecated` e isso é ruído esperado.
```

---

## 13. Serviço 2: editor de tag NFC

Independente do sistema de plaquinhas, construível em paralelo. Uma página só,
em `/servicos-digitais/nfc`, sem login.

- Detecta suporte. Sem `NDEFReader`, explica e mostra o QR da própria página.
- **Ler tag**: tipo do registro, conteúdo, bytes usados, capacidade, se está
  travada.
- **Gravar**: URL, texto, telefone, e-mail, SMS, geo, Wi-Fi (SSID, segurança,
  senha), vCard (nome, empresa, cargo, telefone, e-mail, site).
- Compara o tamanho do payload com a capacidade lida **antes** de gravar.
- Travar permanentemente: caixinha separada, desmarcada, confirmação escrita, e
  o aviso de que não tem volta.
- Histórico do que foi gravado na sessão, em memória, para regravar em série
  sem redigitar.

Serve de isca para a plaquinha e já é útil sozinho para quem compra tag avulsa.
