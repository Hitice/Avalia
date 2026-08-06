# Deploy

Servidor próprio (VPS) em São Paulo, com PostgreSQL na mesma máquina.

O banco local é o motivo principal da escolha. Contra o Supabase em ca-central-1,
uma consulta simples leva cerca de 2 segundos incluindo a abertura de conexão;
no mesmo servidor, é socket local. Confira com `php artisan avalia:ambiente`,
que mede e avisa quando o banco é remoto.

## O que o servidor precisa ter

| | Por quê |
| --- | --- |
| PHP 8.2+ com `pdo_pgsql` | As migrations são de PostgreSQL |
| PostgreSQL 14+ | Não MySQL: valores em centavos usam `bigint` e o catálogo depende de tipos do Postgres |
| Cron | Quatro rotinas diárias, todas ligadas a dinheiro ou a prazo legal |
| Worker supervisionado | A fila é `database` |
| Certificado TLS | O cookie de sessão é `secure` em produção e não viaja sem HTTPS |

Hospedagem compartilhada não serve: não roda worker e quase sempre só oferece
MySQL.

## Raiz do servidor

O documento raiz aponta para `public`, e não para a pasta do projeto. Apontar
para a raiz deixa `.env`, `storage` e `vendor` acessíveis pela web.

```
root /var/www/avalia/public;
```

`avalia:ambiente` acusa quando `APP_URL` contém `/public`, que é o sinal de que
isso ficou errado.

## Rotinas

Uma linha no cron. O Laravel decide o que roda em cada horário.

```cron
* * * * * cd /var/www/avalia && php artisan schedule:run >> /dev/null 2>&1
```

| Horário | Rotina | Se não rodar |
| --- | --- | --- |
| 00:05 | Vencimento e suspensão | Fatura vencida nunca muda de situação |
| 00:15 | Fechamento de competência | Mês não fecha e não há o que cobrar |
| 02:00 | Cópia do banco | Fica sem backup do dia |
| 03:00 | Expurgo da retenção | Dado pessoal passa dos 180 dias, contra a LGPD |
| 04:00 | Conferência de integridade | Divergência entre consulta, fatura e cobrança passa despercebida |

## Worker da fila

Serviço do sistema, para voltar sozinho depois de reinício ou queda.

```ini
[Unit]
Description=Avalia: fila
After=network.target postgresql.service

[Service]
User=www-data
Restart=always
RestartSec=5
WorkingDirectory=/var/www/avalia
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=3 --max-time=3600

[Install]
WantedBy=multi-user.target
```

`--max-time=3600` recicla o processo de hora em hora: worker longo acumula
memória e segura código antigo depois de um deploy.

## Sequência de deploy

```bash
php artisan down --render=errors::503

git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build

php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache

php artisan avalia:ambiente          # falha aqui não deixa subir
sudo systemctl restart avalia-fila   # o worker precisa recarregar o código novo

php artisan up
```

`avalia:ambiente` antes do `up`, e não depois: erro de configuração não quebra
nada visível, então descobrir depois significa descobrir tarde.

## Trazendo os dados do Supabase

O arquivo de exportação tem só dados. A estrutura vem das migrations, o que
torna a cópia portátil entre provedores.

```bash
# Na origem, ainda apontando para o Supabase
php artisan avalia:exportar

# No servidor novo, com o .env já apontando para o Postgres local
php artisan migrate --force
php artisan avalia:importar backup/avalia-AAAA-MM-DD-HHMM.sql
```

A importação roda em transação: ou o banco fica inteiro, ou fica como estava.

Depois, confira que as contagens batem com a origem e rode `avalia:conferir`.

## Backup

No servidor próprio o backup deixa de ser do provedor.

A rotina das 02:00 grava em `backup/` e apaga cópias com mais de 14 dias. Isso
cobre engano humano, e **não** cobre perda da máquina: a cópia está no mesmo
disco que ela protege.

Falta contratar um destino externo e enviar o arquivo para lá. Enquanto isso não
existir, o backup é parcial, e vale dizer isso em voz alta em vez de contar com ele.

A pasta `backup/` está no `.gitignore` e fora de `public`: os arquivos têm dado
de cliente e hash de senha.

## Configuração do ambiente

Nunca versionar `.env`. Em produção, além do que o `.env.example` traz:

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://SEUDOMINIO

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1

SESSION_SECURE_COOKIE=true
QUEUE_CONNECTION=database
```

`DB_PERSISTENT` fica desligado: com vários processos php-fpm, cada um segura uma
conexão aberta e o limite do Postgres estoura antes do esperado.

`DB_EMULA_PREPARE` fica desligado, e o motivo está em `config/database.php`: ele
quebra booleano no Postgres, e a suíte roda em SQLite, onde o mesmo SQL funciona.
O teste passaria e a produção recusaria a consulta.
