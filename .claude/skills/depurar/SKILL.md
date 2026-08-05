---
name: depurar
description: Investigar bug ou comportamento estranho no Avalia. Monta o PHP portatil, roda a suite ou um teste so, e checa primeiro as armadilhas que este projeto ja produziu (Tailwind nao reconstruido, sessao e 419, Supabase remoto, boolean no Postgres, acessador Eloquent).
---

# Depurar o Avalia

Ordem fixa: **reproduzir, isolar, corrigir na origem, provar com teste**. Relatar
sintoma sem reproduzir e adivinhacao.

## 1. Ter PHP na mao

A maquina nao tem PHP no PATH, nem WSL, nem Docker. O binario portatil vive no
scratchpad da sessao:

```bash
find /c/Users/PEDROF~1/AppData/Local/Temp/claude -iname "php.exe" 2>/dev/null | head -1
```

Se nao houver, baixe PHP NTS x64 de windows.php.net para o scratchpad e escreva
um `php.ini` com `extension_dir=ext` e as extensoes `mbstring`, `openssl`,
`pdo_sqlite`, `sqlite3`, `fileinfo`, `curl`, `zip`, `intl`. Sem `pdo_sqlite` a
suite nao roda: `phpunit.xml` usa SQLite em memoria.

```bash
PHP=<caminho>/php.exe
"$PHP" vendor/bin/pest                             # suite inteira, ~7s
"$PHP" vendor/bin/pest --filter="parte do nome"    # um teste so
"$PHP" vendor/bin/pest 2>&1 | grep -aE "FAILED|Tests:"   # so o placar
```

O `-a` no grep e obrigatorio: a saida do Pest tem bytes que o grep trata como
binario e engole o resultado.

## 2. Armadilhas ja pagas neste projeto

Antes de teorizar, elimine estas. Cada uma custou tempo aqui:

| Sintoma | Causa real |
|---|---|
| Classe CSS nova nao pinta, botao sai preto no tema escuro | Tailwind nao emitiu a classe. Ou faltou `npm run build`, ou o nome foi montado em tempo de execucao (`sprintf`, concatenacao) e o scanner nao ve. Nome de classe aparece **literal** no Blade. |
| Cabecalho de tabela desalinhado da coluna | `<th>` centraliza por padrao. Precisa de `text-left` explicito. |
| "R$ 0,00" onde deveria estar "Sem minimo" | Faixa veio do banco como string e `=== 0` falhou. Use `Catalogo::faixasDe()` e `Dinheiro::faixa()`. |
| 419 Page Expired intermitente no login | Laravel converte `TokenMismatchException` antes dos render callbacks. Tratar em `bootstrap/app.php` por `HttpExceptionInterface` com status 419. |
| Acesso nao fica salvo entre sessoes | `ConfereSessao` precisa semear a versao na sessao quando o login veio de `viaRemember()`. |
| Tela leva 6 segundos | Supabase esta em ca-central-1, ~172ms de ida e volta. Cada round trip conta: procure N+1 e sessao ou cache em banco. |
| `operator does not exist: boolean = integer` so em producao | `DB_EMULA_PREPARE=true`. Os testes em SQLite nao pegam isso. Mantenha desligado. |
| Seeder estoura o tempo | Um insert por linha contra banco remoto. Use `upsert` em lote. |

## 3. Achar de verdade quem usa um simbolo

Duas armadilhas de busca que ja causaram remocao de codigo vivo:

- **Acessador Eloquent nao aparece por nome.** `getConsumoMinimoAttribute` e
  chamado como `$plano->consumo_minimo`. Idem scope: `scopeDisponiveis` vira
  `->disponiveis()`. Procure pelo nome de uso, nao pelo nome de declaracao.
- **`grep -v` filtra a linha inteira, caminho incluido.** Excluir "planos"
  esconde tudo que vem de `paginas/catalogo/planos.blade.php`. Filtre por padrao
  ancorado, ou nao filtre.

Blade tambem conta como codigo: `resources/views` entra em toda busca.

## 4. Fechar

Bug corrigido sem teste volta. O teste vai onde a regra mora, com um comentario
dizendo **por que** ela existe, no padrao dos testes atuais (ver
`tests/Feature/CustoMargemTest.php`).

Antes de dizer pronto: `vendor/bin/pest`, `vendor/bin/pint`, e `npm run build`
se mexeu em Blade ou CSS.
