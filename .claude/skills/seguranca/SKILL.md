---
name: seguranca
description: Revisar seguranca no Avalia antes de publicar tela, rota, acao ou integracao nova, e em varredura periodica. Checklist das portas desta casa (guards, permissoes, isolamento por carteira, segredos, webhooks) com o comando que prova cada item.
---

# Revisao de seguranca

Prova, nao opiniao: cada item abaixo tem um teste ou um comando que o confirma.
Item sem prova entra na secao 16 da PDD como pendencia, nao como "ok".

## As portas desta casa

| Porta | Quem passa | Onde |
|---|---|---|
| `auth:staff` / `empresa` / `produtor` | conta do guard | `routes/web.php`, grupos |
| `sessao:<guard>` | sessao com `versao_<guard>` igual ao banco | `ConfereSessao` |
| `admin` | `papel = admin` ou `super` | `SomenteAdmin` |
| `financeiro`, `socios` | `pode_financeiro`, `pode_socios` (nascem negadas) | `SomenteFinanceiro`, `SomenteSocios` |
| `produto` | `acessa_one` / `acessa_sales` por rota (`ROTAS_SALES`) | `AcessoAoProduto` |
| dono do codigo | `dono_tipo`/`dono_id` da etiqueta | `Etiqueta::visiveis()` |
| carteira | `vendedor_id === auth id`, sem parametro de rota | `CarteiraController` |

## Checklist por mudanca

1. **Rota nova esta no grupo certo?** Rota fora de grupo e publica. `grep -n "Route::" routes/web.php` e confira o `middleware` do bloco.
2. **Acao de dinheiro exige `admin` no minimo** (trocar vendedor, cancelar venda, corrigir valor, pagar comissao, entregar placa).
3. **Nada de id de outro na URL** para quem nao e admin: carteira, consulta, fatura, placa. `PortasFechadasTest`, `RegistroAlheioTest` e `CarteiraDeClientesTest` cobram; mudanca nessas telas ganha caso novo la.
4. **Entrada do usuario**: `Request` valida; `LIKE` recebe termo cru (basta nao concatenar SQL); `{!! !!}` so para markup que o sistema gera (`Marca`, QR), nunca para dado de pessoa.
5. **Formulario publico** tem throttle e campo armadilha (`assunto`), como `/cadastro` e o link de avaliacao.
6. **Segredo** so em `conexoes` (cifrado) ou no `.env`. `grep -rn "sk_\|api_key\|senha" app resources --include=*.php` nao pode achar literal. Log nunca recebe token, cookie ou senha.
7. **Webhook** confere o token (`WebhookAsaasController`) e e idempotente (`eventos_asaas`).
8. **Redirect por referer** so para esta casa, com `host + '/'` (um dominio que comeca igual passava).
9. **Sessao**: trocar papel, desativar ou remover revoga (`revogaSessoes`). Operador nao tem "manter conectado".
10. **Auditoria**: acao nova de dinheiro ou de acesso registra em `Auditar` e tem rotulo em `Rotulos` (teste derruba sem rotulo).

## Varredura periodica

```bash
php vendor/bin/pest --filter="PortasFechadas|RegistroAlheio|AreaRestrita|AcessoAoProduto|Carteira"
php artisan avalia:ambiente                      # debug, cookie, e-mail, .env fora da web
curl -sI https://avaliaone.com.br/.env | head -1  # 403 ou 404, nunca 200
curl -sI https://avaliaone.com.br/storage/logs/laravel.log | head -1
composer audit                                   # CVE nas dependencias (local; producao nao tem composer)
npm audit --omit=dev
```

## O que ainda nao esta provado (PDD, secao 16)

Isolamento entre empresas e carteiras como matriz papel x acao x recurso;
conciliacao com o provedor; consulta duplicada por clique; expurgo e exportacao
por titular. Nao chame o sistema de "auditado" com isso em aberto.
