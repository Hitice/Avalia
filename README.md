# Avalia

Software house de Uberlândia e os produtos que ela opera numa aplicação só:

| Marca | O que é | Área |
|---|---|---|
| **Avalia One** | Pesquisa de score: plano mensal com franquia de consultas | `/painel` |
| **Avalia Gestor** | Venda parcelada: carnê e cobrança para quem vende a prazo | `/cobranca` (produtor) |
| **Avalia Sales** | Vendas de rua: plaquinha de QR e NFC, encurtador, base de negócios | `/sales` |
| **Avalia** | O site, os serviços de software sob contrato e o back office | `/`, `/socios`, `/equipe` |

A regra de negócio mora na [PDD.md](PDD.md). Publicar está na [DEPLOY.md](DEPLOY.md).
Este arquivo põe para rodar.

## Stack

PHP 8.2+, Laravel 12, Blade, Tailwind 4, Alpine.js, Vite, Pest 4. MySQL em
produção, SQLite em memória nos testes. Sem fila, sem worker: a hospedagem é
compartilhada e a aplicação não enfileira nada.

## Rodar

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
npm run build
composer run dev
```

Banco e conta administrativa saem do `.env`, que não é versionado.

## Conferir antes de publicar

```bash
vendor/bin/pint                                   # estilo
php vendor/bin/pest                               # suíte inteira; leia a linha Tests:
php vendor/bin/pest tests/Unit/DiretivaTest.php   # catraca: comentário, tema, razão único
npm run build                                     # obrigatório depois de Blade ou CSS
```

`public/build` é versionado de propósito: o servidor não tem Node, e o front
precisa chegar pronto. Sob PHP 8.5 a suíte marca quase tudo como `DEPR`; vem do
framework, não daqui. O que vale é `0 failed`.

## Comandos da casa

```bash
php artisan avalia:ambiente                  # o ambiente está seguro para produção
php artisan avalia:conferir                  # fechamento, cobranças, webhooks, trilha
php artisan avalia:inventario                # linhas por tabela
php artisan avalia:exportar                  # cópia do banco, sem mysqldump
php artisan avalia:importar <arquivo>        # restaura a cópia
php artisan avalia:conferir-exclusao --email= --empresa=
php artisan avalia:lastrear-plaquinhas --simular    # vendas de placa que entrariam no razão
php artisan avalia:relancar-plaquinhas --simular    # relança as que mudaram de regra
php artisan avalia:etiquetas-limpar
```

Têm nome próprio porque produção não tem SSH: o único caminho é cron, e o campo
de comando do provedor não aceita aspas em três níveis ([DEPLOY.md](DEPLOY.md)).

## Onde as coisas ficam

```
app/Actions/<Modulo>/     uma regra de negócio por classe, transacional
app/Contabil/             o razão: único escritor (Lancar), partidas, competência, livro-caixa
app/Support/              cálculo puro, sem banco (Dinheiro, Margem, Comissao, RepartePlaquinha, Marca)
app/Services/Conectores/  bureaus, atrás do contrato ConectorBureau
app/Helpers/MenuHelper    qual lateral e qual marca cada rota mostra
resources/views/          telas Blade; componentes em components/avalia e components/site
resources/css/app.css     @utility do tema, o vocabulário de tela da casa
database/seeders/dados/   dados transcritos (preços, custos, leads) e a fonte deles; tools/ regenera
tests/                    Pest, um arquivo por assunto
```

Duas regras sustentam o faturamento: dinheiro em centavos inteiros do banco até
a tela, e preço e custo gravados na emissão. A PDD diz por quê.

## Documentos

| Arquivo | Assunto |
|---|---|
| [PDD.md](PDD.md) | regra de negócio, papéis, dinheiro, rumo e decisões pendentes |
| [DEPLOY.md](DEPLOY.md) | como a produção publica, roda e se recupera |
| [PRECOS-FORNECEDOR.md](PRECOS-FORNECEDOR.md) | custo de aquisição por serviço, provisório |

Como se escreve código e tela: skills `padroes`, `enxugar`, `depurar` e
`codigo-morto`, em `.claude/skills/`. Licença em [LICENSE](LICENSE).
