# Deploy

Dois destinos possíveis, e a aplicação roda nos dois. Ela não depende de recurso
exclusivo de banco nenhum, e a prova é a suíte inteira passando em SQLite.

| | Hospedagem compartilhada (Premium) | VPS |
| --- | --- | --- |
| Banco | MySQL | PostgreSQL ou MySQL |
| Node no servidor | só Business+ | sim |
| Processo de pé | não | sim |
| Custo | já contratado | a mais |

**Hospedagem compartilhada atende hoje.** O único recurso que ela não oferece e
que costuma ser eliminatório é o processo permanente da fila, e a aplicação não
enfileira nada: não existe um `ShouldQueue` nem um `dispatch()` no código. O dia
em que existir, a fila passa a precisar de VPS.

O que se perde é latência de banco: no VPS, aplicação e banco ficam na mesma
máquina. `avalia:ambiente` mede a ida e avisa quando o banco está longe.

## Compartilhada: o que muda

O banco é MySQL. Para trazer os dados que hoje estão no PostgreSQL, a exportação
escreve no dialeto do destino:

```bash
php artisan avalia:exportar --destino=mysql
```

Sem `--destino`, o arquivo sai com aspas duplas e comandos de sequência, que são
do PostgreSQL. O MySQL recusa os dois.

O front é montado pelo GitHub e enviado pronto, porque o Premium não tem Node. O
`deploy.sh` percebe a ausência e não tenta montar.

A raiz do domínio precisa apontar para `public`. Com o projeto em `~/avalia`:

```bash
rm -rf ~/domains/SEUDOMINIO/public_html
ln -s ~/avalia/public ~/domains/SEUDOMINIO/public_html
```

O `.env` fica em `~/avalia`, fora do que a web alcança. Deixá-lo dentro de
`public_html` publica as credenciais do banco.

As rotinas entram no cron do hPanel, uma linha só:

```
* * * * * cd ~/avalia && php artisan schedule:run >> /dev/null 2>&1
```

Confira o intervalo mínimo que o plano permite. Se for maior que um minuto,
ajuste os horários em `routes/console.php` para múltiplos desse intervalo: uma
rotina agendada às 00:05 num cron de 15 em 15 minutos nunca roda.

O resto deste documento descreve o VPS.

---

## VPS

Servidor próprio em São Paulo, com PostgreSQL na mesma máquina.

## Antes de tudo: fechar o acesso

A VPS nasce com senha de root e login por senha liberado. Nesse estado ela recebe
tentativa de invasão automatizada em minutos, e uma senha de dezesseis caracteres
não resiste a semanas de tentativa contínua.

A senha de root não vai para lugar nenhum: nem para o `.env`, nem para o
repositório, nem para conversa. O `.env` é lido pela aplicação web, então uma
página de erro com depurador ligado entrega junto o acesso ao servidor inteiro.
Guarde em gerenciador de senhas, e depois desta seção você não precisa mais dela.

**Na sua máquina**, gere um par de chaves só para isto:

```bash
ssh-keygen -t ed25519 -C "avalia-deploy" -f ~/.ssh/avalia
ssh-copy-id -i ~/.ssh/avalia.pub root@SEU_IP
```

**No servidor**, crie o usuário da aplicação e passe a chave para ele:

```bash
adduser --disabled-password --gecos "" avalia
usermod -aG www-data avalia

install -d -m 700 -o avalia -g avalia /home/avalia/.ssh
cp ~/.ssh/authorized_keys /home/avalia/.ssh/
chown avalia:avalia /home/avalia/.ssh/authorized_keys
chmod 600 /home/avalia/.ssh/authorized_keys
```

Confirme que entra como `avalia` **numa segunda janela**, sem fechar a primeira.
Só depois de confirmar, feche o resto:

```bash
# /etc/ssh/sshd_config
PermitRootLogin no
PasswordAuthentication no
KbdInteractiveAuthentication no
```

```bash
systemctl restart ssh
```

Fechar antes de confirmar é como você se tranca do lado de fora.

Por último, o firewall:

```bash
ufw allow OpenSSH
ufw allow 'Nginx Full'
ufw --force enable
```

O PostgreSQL fica sem regra de propósito: ele só escuta em `127.0.0.1` e não
precisa estar acessível de fora.

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

## Instalação inicial

Como `avalia`, não como root:

```bash
sudo apt update
sudo apt install -y nginx postgresql php8.3-{fpm,pgsql,mbstring,xml,curl,zip,intl} \
                    composer git unzip
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash - && sudo apt install -y nodejs

sudo -u postgres createuser --pwprompt avalia
sudo -u postgres createdb --owner=avalia avalia

sudo install -d -o avalia -g www-data /var/www/avalia
git clone SEU_REPOSITORIO /var/www/avalia
cd /var/www/avalia

composer install --no-dev --optimize-autoloader
npm ci && npm run build

cp .env.example .env && php artisan key:generate
# edite o .env: APP_ENV, APP_URL, DB_*, SESSION_SECURE_COOKIE

php artisan migrate --force
php artisan db:seed --force
php artisan db:seed --class=DocumentosSeeder --force

chmod +x deploy.sh
sudo chown -R avalia:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

O certificado sai do Certbot: `sudo certbot --nginx -d SEUDOMINIO`.

O `deploy.sh` reinicia o worker, e isso é a única coisa que ele precisa de root.
Libere só esse comando, e nada mais:

```
# /etc/sudoers.d/avalia
avalia ALL=(root) NOPASSWD: /bin/systemctl restart avalia-fila
```

## Publicação contínua

Push na `main` dispara a suíte no GitHub. Passando os 382 testes, ele entra por
SSH e roda o `deploy.sh`. Falhando, não publica nada.

Quatro segredos em **Settings → Secrets and variables → Actions**:

| Segredo | O que é |
| --- | --- |
| `SSH_SERVIDOR` | IP ou domínio do servidor |
| `SSH_USUARIO` | `avalia` no VPS, ou o usuário do hPanel na compartilhada |
| `SSH_CHAVE` | Conteúdo de `~/.ssh/avalia`, a chave **privada**, inteira |
| `SSH_IDENTIDADE` | Saída de `ssh-keyscan SEU_IP` |
| `SSH_CAMINHO` | `/var/www/avalia` no VPS, ou `~/avalia` na compartilhada |
| `SSH_PORTA` | Só se não for 22. A Hostinger usa 65002 na compartilhada |

`SSH_IDENTIDADE` existe para o GitHub reconhecer o servidor antes de entrar.
Sem ele, a alternativa é aceitar a chave que aparecer na hora, inclusive a de
quem estiver no meio do caminho, e a sessão seguinte carrega a chave privada que
publica em produção.

Para publicar à mão, é o mesmo arquivo:

```bash
ssh avalia@SEU_IP 'cd /var/www/avalia && ./deploy.sh'
```

O `deploy.sh` volta sozinho para a versão anterior se qualquer passo falhar,
incluindo o `avalia:ambiente`. O que ele **não** desfaz é o banco: migration
aplicada continua aplicada.

Por isso migration que remove coluna ou tabela nunca sobe junto com o código que
para de usá-la. Primeiro publica o código que já não usa; depois, em outra
versão, remove a coluna. Migration que só adiciona é sempre segura.

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
