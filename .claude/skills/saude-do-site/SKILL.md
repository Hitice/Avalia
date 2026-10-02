---
name: saude-do-site
description: Diagnosticar erro, lentidao e 503 em producao (avaliaone.com.br) sem SSH. Mede de fora, le o que a hospedagem e o repositorio contam, separa publicacao de limite do plano, e diz o que fazer em cada caso.
---

# Saude do site

Producao nao tem SSH. O que da para ver: resposta de fora, logs que o proprio
sistema escreve, o que o `deploy.sh` fez, e o que a API da Hostinger conta.

## 1. Meca antes de supor

```bash
# Um minuto de batidas, uma por segundo: status e tempo. 503 cai em qual segundo?
for i in $(seq 1 60); do printf "%s " "$(date +%T)"; curl -s -o /dev/null -w "%{http_code} %{time_total}s\n" -m 10 https://avaliaone.com.br/; sleep 1; done
```

| Padrao | Causa provavel | O que fazer |
|---|---|---|
| 503 por 4 a 8 s logo depois de um push | publicacao (`artisan down`) | esperado; agrupar publicacoes |
| 503 todo minuto, no mesmo segundo | `deploy.sh` derrubando sem commit novo | ja corrigido em 01/10/2026 (sai cedo); confira `git log -1` no servidor por cron |
| 503 em rajada sob trafego, com `Retry-After` | limite do plano compartilhado (CPU, processos de entrada) | ver uso no hPanel/API; reduzir trabalho por requisicao |
| 500 | erro da aplicacao | `storage/logs/laravel-<dia>.log`, pela tela de Auditoria ou cron de leitura |
| 419 | sessao/CSRF expirado | tratado em `bootstrap/app.php`; se recorrente, `SESSION_LIFETIME` |
| 2 s ou mais numa tela | N+1, consulta sem indice, QR sendo desenhado no servidor | `DB::listen` em teste; Vendas e paineis somam em PHP, ver PDD 15 fase 5 |

## 2. O que o repositorio conta

- `git log --since="1 day" --oneline`: cada commit em `main` e uma publicacao, ~6 s fora do ar cada.
- `deploy.sh` ao vivo no servidor: cron de leitura pela API (`DEPLOY.md`, "Rodar um comando avulso") com `git log -1 --format=%h_%s` e `git reflog | head`.
- `php artisan avalia:conferir` roda as 04:00 e lista divergencia de fechamento, cobranca, webhook e trilha.

## 3. O que a hospedagem conta

MCP `hostinger-hosting`: `search` por "resource usage", "cron jobs", "ssl",
"error log". Uso de CPU e processos de entrada e o que distingue limite do plano
de erro nosso. Certificado precisa cobrir `avaliaone.com.br` e `www`.

## 4. Lentidao: onde este projeto gasta

- QR e desenhado no navegador de proposito (nao mover para o servidor).
- Paineis somam documento a documento em PHP (`PainelController`, `Caixa`, `VendasPlaquinhasController`); com volume, e o primeiro lugar a demorar. Saida: ler do razao.
- `site:aquecer` e `cron:batida` em `routes/console.php` mantem cache e sessao quentes; se o cron de 5 min parar, a primeira visita paga.
- Sem fila: nada roda em segundo plano; e-mail sai na requisicao.

## 5. Ao terminar

Diga o que foi medido, o padrao visto, a causa provada e o que ficou sem prova.
"Sazonal" nao e diagnostico: e um padrao de horario ainda nao medido.
