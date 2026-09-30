# Avalia

Software house e as tres operacoes que ela vende: **Avalia One** (consultas de
credito), **Avalia Gestor** (venda parcelada e cobranca) e **QR dinamico**
(plaquinha com NFC e encurtador).

Regra de negocio nao mora aqui, mora na [PDD.md](PDD.md). Este arquivo poe para
rodar.

## Stack

PHP 8.2 ou superior (producao roda 8.5), Laravel 12, Blade, Tailwind 4,
Alpine.js, Vite, Pest 4. **MySQL em producao, SQLite em memoria nos testes.**

## Rodar

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run build
composer run dev      # servidor, fila, logs e Vite juntos
```

Banco e conta administrativa saem do `.env`, que nao e versionado, e nem ele nem
credencial nenhuma entram em commit.

## Conferir

```bash
php vendor/bin/pest                               # suite inteira
php vendor/bin/pest tests/Unit/DiretivaTest.php    # catraca das diretivas
vendor/bin/pint                                    # estilo
npm run build                                      # obrigatorio depois de Blade ou CSS
```

A suite usa SQLite em memoria por configuracao do `phpunit.xml`, entao nao ha
como apontar teste para producao por engano.

Sob PHP 8.5 a saida marca quase todo teste como `DEPR`. **Nao e falha nossa**:
vem de `vendor/laravel/framework/config/database.php`, que ainda le
`PDO::MYSQL_ATTR_SSL_CA`. Leia a linha `Tests:` no fim, que diz `0 failed`.

## Diagnostico

```bash
php artisan avalia:inventario        # linhas por tabela
php artisan avalia:ambiente          # ambiente seguro para producao
php artisan avalia:conferir          # fechamento, cobrancas, webhooks, trilha
php artisan avalia:conferir-exclusao --email= --empresa=
php artisan avalia:exportar          # copia do banco, sem mysqldump
php artisan avalia:importar          # restaura o que avalia:exportar gerou
php artisan avalia:lastrear-plaquinhas --simular   # vendas que entrariam no razao
```

Existem com nome proprio porque producao nao tem SSH: o unico caminho e cron, e
o campo de comando do provedor nao aceita aspas em tres niveis. Detalhe na
[DEPLOY.md](DEPLOY.md).

## Onde as coisas ficam

```
app/Actions/<Modulo>/   uma regra de negocio por classe, transacional
app/Support/            calculo puro, sem banco (Dinheiro, Margem, Comissao)
app/Services/Conectores/ bureau externo, atras de contrato
resources/views/        telas Blade
resources/css/app.css   @utility do tema, o vocabulario da casa
database/migrations/    schema; migration nao reescreve historia
tests/                  Pest, um arquivo por assunto
temp/                   referencia para transcricao, nunca fonte em runtime
```

Dinheiro em centavos inteiros do banco ate a tela, e preco e custo gravados na
emissao. Essas duas sustentam o faturamento inteiro; a [PDD.md](PDD.md) diz por
que.

## Documentos

| Arquivo | Assunto |
|---|---|
| [PDD.md](PDD.md) | regra de negocio, papeis, precos, decisoes pendentes |
| [PLANO-FINANCEIRO.md](PLANO-FINANCEIRO.md) | avaliacao ERP/CRM e para onde o financeiro vai |
| [DEPLOY.md](DEPLOY.md) | publicar sem SSH |
| [PRECOS-FORNECEDOR.md](PRECOS-FORNECEDOR.md) | custo de aquisicao, provisorio |
| [AUDITORIA.md](AUDITORIA.md) | achados da auditoria de 29/09/2026 |

Como se escreve codigo e tela: skills `padroes` e `enxugar`, em
`.claude/skills/`.

## Licenca

[LICENSE](LICENSE).
