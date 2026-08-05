---
name: codigo-morto
description: Varrer codigo morto no Avalia sem apagar coisa viva. Procedimento para achar view, componente, utility CSS, metodo e rota que ninguem usa, com as verificacoes que evitam falso positivo.
---

# Varredura de codigo morto

O projeto nasceu de um boilerplate cheio de telas que nunca vao existir aqui.
Sobra CSS de biblioteca desinstalada, componente de demo e metodo orfao. Tudo
isso e ruido que faz o proximo leitor procurar sentido onde nao ha.

**Regra unica: so apaga com prova de que ninguem usa.** Falso positivo aqui
quebra tela em producao, e tela quebrada custa mais do que arquivo sobrando.

## O que procurar, em ordem

1. **Dependencia que saiu do `package.json`, CSS que ficou.** Ja foi o caso de
   apexcharts, flatpickr, fullcalendar, prism, swiper e jsvectormap: 500 linhas
   de `app.css` para bibliotecas que nao existem mais.
2. **Seletor que nao casa com nada.** `.sidebar:hover` nunca funcionou: a barra
   e `<aside id="sidebar">`, sem classe.
3. **`@utility` sem consumidor no Blade.**
4. **Componente de demo do boilerplate** em `app/View/Components` e
   `resources/views/components`.
5. **Metodo publico sem chamador.**
6. **Definicao duplicada.** `app.css` chegou a ter `menu-dropdown-item` dois
   blocos, com corpos ligeiramente diferentes.

## Como provar que esta morto

Toda busca cobre `app`, `resources/views`, `resources/js`, `routes`, `tests`,
`database`. Blade e codigo.

Contagem por utility:

```bash
grep -o "^@utility [a-z0-9-]*" resources/css/app.css | sed 's/@utility //' \
  | while read u; do
      echo "$(grep -rc "\b$u\b" resources/views --include="*.blade.php" \
        | awk -F: '{s+=$2} END {print s}') $u"
    done | sort -n
```

Definicao duplicada:

```bash
grep -n "^@utility" resources/css/app.css | awk '{print $2}' | sort | uniq -d
```

## Os dois erros que ja aconteceram aqui

1. **Acessador e scope Eloquent nao aparecem pelo nome de declaracao.**
   `getConsumoMinimoAttribute` e usado como `$plano->consumo_minimo`;
   `scopeDisponiveis` como `->disponiveis()`. Uma varredura por
   `nomeDoMetodo(` marca os dois como mortos, e `planos.blade.php` para de
   mostrar mensalidade. Antes de apagar membro de Model, procure tambem a forma
   de uso.

2. **`grep -v` filtra a linha inteira, caminho incluido.** Excluir "planos" da
   saida esconde toda ocorrencia vinda de
   `paginas/catalogo/planos.blade.php`. Foi assim que o item 1 passou pela
   conferencia. Se precisar filtrar, ancore o padrao.

Terceira defesa, que pega o que as duas primeiras deixaram passar: rode
`vendor/bin/pest` **depois de cada remocao**, nao no fim de todas.

## Sobre remover com `preg_replace` em lote

Se o padrao nao compilar, `preg_replace` devolve `null` e o `file_put_contents`
seguinte esvazia o arquivo. Ja aconteceu com `app/Models/Servico.php`. Ou edite
arquivo por arquivo com a ferramenta de edicao, ou verifique o retorno antes de
gravar. `git checkout --` recupera, desde que o arquivo estivesse limpo.

## Duas coisas que nao sao codigo morto

- **Rota, campo ou tela que ainda nao tem consumidor mas ja tem regra escrita**:
  parte do modulo em construcao. Confirme com o Pedro antes.
- **Comentario que explica uma decisao**: e o unico registro de por que a regra
  existe. Nao apague junto com o codigo que ele explica sem ler.

Ao terminar, diga o que saiu e quanto: numero de linhas e de arquivos, para o
resultado ser conferivel.
