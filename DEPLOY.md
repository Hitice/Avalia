# Deploy

Produção é a hospedagem compartilhada da Hostinger: `avaliaone.com.br`, projeto
em `~/avalia`, MySQL na mesma máquina, PHP sem `proc_open`. Não há SSH (o shell
da conta é `/sbin/nologin`), não há Composer nem Node no servidor, e não há
processo de pé. A aplicação foi feita para caber nisso: não enfileira nada, o
front chega pronto no repositório e tudo que precisa rodar no servidor roda por
cron.

## Como uma versão chega ao ar

1. `git push origin main`.
2. Um cron **de minuto em minuto** no servidor executa `./deploy.sh`. Ele não
   aparece na lista de crons da API da Hostinger; está lá mesmo assim.
3. O script faz `git fetch` e compara `HEAD` com `origin/main`. **Iguais, imprime
   `nada novo` e termina**: o site nem sai do ar. Foi o conserto dos 503 de
   01/10/2026, quando ele derrubava o site 4 a 6 segundos a cada minuto sem ter
   o que publicar.
4. Com commit novo: `artisan down`, `git reset --hard origin/main`,
   `package:discover`, `migrate --force`, `LeadsSeeder`, caches,
   `avalia:ambiente`, `artisan up`. Uns 6 segundos fora do ar.
5. Se qualquer passo falhar, volta para o commit anterior e sobe ele. **O banco
   não volta**: migration aplicada continua aplicada.

Daí três regras:

- **Agrupe publicações.** Cada uma derruba o site por alguns segundos, e tem
  gente usando do outro lado.
- **Migration que remove coluna ou tabela nunca sobe com o código que para de
  usá-la.** Primeiro o código que já não usa; noutra versão, a remoção. Migration
  que só adiciona é sempre segura.
- **`vendor/` não é publicado pelo cron.** O Composer não roda no servidor e
  `vendor/` não está no repositório. Dependência nova do `composer.json` exige
  enviar a pasta `vendor/` pronta pelo gerenciador de arquivos do hPanel antes do
  push. Até lá, prefira resolver sem pacote novo.

`./deploy.sh --forcar` (ou `AVALIA_FORCAR=1`) publica sem commit novo, quando só
os caches precisam ser refeitos. `flock` garante uma publicação por vez: duas
juntas criavam a mesma tabela e a segunda morria com a migration sem registro
(29/09/2026).

## Rodar um comando avulso em produção

Sem SSH, o caminho é um cron criado **pela API** (MCP `hostinger-hosting`,
operação `hosting_cron-jobs_create`), com o comando cru:

```
/bin/bash -lc 'cd $HOME/avalia && php artisan avalia:relancar-plaquinhas --simular' 2>&1
```

Leia a saída com `hosting_cron-jobs_output` e **apague o cron** com
`hosting_cron-jobs_delete`. O campo de cron do hPanel não serve: ele prefixa
`/usr/bin/php /home/<usuario>/` ao que você digita e o comando morre calado.

Limites que já custaram tempo: `bash -lc` é obrigatório (o cron não tem `php` nem
`git` no PATH); o `command` tem 255 caracteres; a API engole barra invertida e só
há dois níveis de aspas, então nada de `tinker --execute` com namespace: ponha a
consulta num comando artisan com nome e chame por nome. A saída é da **última**
execução, e o cron de minuto roda mais de uma vez; `migrate:status | grep`
fecha a dúvida.

## Rotinas

Um cron a cada cinco minutos chama `php artisan schedule:run`, e
`routes/console.php` decide o que roda:

| Horário | Rotina | Se não rodar |
|---|---|---|
| 00:05 | Vencimento, atraso e bloqueio | Fatura vencida nunca muda de situação |
| 00:15 | Fechamento de competência | Mês não fecha e não há o que cobrar |
| 02:00 | Cópia do banco (`avalia:exportar --enviar`) | Fica sem backup do dia |
| 03:00 | Expurgo da resposta do bureau | Dado pessoal passa dos 180 dias |
| 04:00 | `avalia:conferir` | Divergência entre consulta, fatura e cobrança passa despercebida |
| 09:00 | Aviso de vencimento próximo | Cliente não é avisado |

A cópia das 02:00 fica no mesmo disco que ela protege. Cobre engano humano, não
perda da máquina.

## Raiz, `.env` e certificado

A raiz do domínio aponta para `~/avalia/public`; o `.env` fica em `~/avalia`,
fora do que a web alcança. `avalia:ambiente` confere isso pelo disco, e não pelo
`APP_URL`, e também recusa subir com depurador ligado, cookie fora de HTTPS ou
e-mail desligado.

O certificado precisa cobrir `avaliaone.com.br` **e** `www.avaliaone.com.br`;
faltou o `www` uma vez e parte dos clientes viu alerta por dias, porque o CDN
segura o certificado antigo.

Em produção, além do `.env.example`:

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://avaliaone.com.br
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
SESSION_SECURE_COOKIE=true
```

## Medição e verificação do Google

Duas variáveis no `.env`, vazias por padrão. Vazias, a página não emite nada.

```
GA4_ID=G-XXXXXXXXXX              # tag do Google Analytics 4; liga o gtag e os eventos clique_whatsapp e envio_formulario
GOOGLE_SITE_VERIFICATION=xxxx    # meta tag do Search Console, alternativa ao registro TXT no DNS
```

A hospedagem sobrescreve o `Content-Security-Policy` da aplicação por
`upgrade-insecure-requests` (medido em 03/10/2026 em `/`, `/entrar` e `/q/...`).
A política completa só vale se a opção Forçar HTTPS do hPanel for desligada,
e aí o redirecionamento de HTTP para HTTPS precisa entrar no `.htaccess` antes.

## GitHub

`.github/workflows/testes.yml` roda estilo e suíte a cada push e pull request. É
barreira, não publicação: a publicação é o cron acima. O job que entrava por SSH
saiu em 02/10/2026 porque nunca rodou (não há SSH).

## Restaurar

```bash
php artisan migrate --force
php artisan avalia:importar backup/avalia-AAAA-MM-DD-HHMM.sql   # transação: inteiro ou nada
php artisan avalia:conferir
```
