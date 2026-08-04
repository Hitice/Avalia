# Avalia

Aplicação Laravel para gestão de consultas de crédito, clientes, vendedores,
planos, faturamento e atendimento.

Os requisitos de produto e negócio estão em [PDD.md](PDD.md).

## Stack

- PHP 8.2+, Laravel 12 e Blade;
- PostgreSQL;
- Tailwind CSS, Alpine.js e Vite;
- Pest para testes.

## Pré-requisitos

- PHP 8.2 ou superior;
- Composer;
- Node.js 18 ou superior e npm;
- PostgreSQL.

## Instalação

    composer install
    npm install
    copy .env.example .env
    php artisan key:generate
    php artisan migrate --seed
    npm run build

Configure as variáveis de banco e a conta administrativa no .env. Não versione
arquivos de ambiente ou qualquer credencial.

## Desenvolvimento

    composer run dev

O comando inicia o servidor Laravel, a fila, os logs e o Vite.

## Qualidade

    composer run test
    vendor/bin/pint
    npm run build

Os testes devem usar uma base isolada. Nunca execute migrações destrutivas ou
testes apontando para uma base de produção.

## Estrutura

    app/                    Código da aplicação
    database/migrations/    Schema do banco de dados
    resources/views/        Telas Blade
    routes/                 Rotas web e comandos agendados
    tests/                  Testes Pest
    PDD.md                  Especificação de produto
    temp/                   Documentos de referência e tabelas de preço

O diretório `temp/` contém arquivos de referência usados para transcrição de
preços e contratos. Ele não deve ser usado como fonte de dados em runtime.

## Produção

- Use HTTPS, APP_DEBUG=false e variáveis de ambiente seguras.
- Execute workers de fila e o agendador quando os módulos assíncronos estiverem ativos.
- Faça backup do PostgreSQL e teste a restauração periodicamente.
- Gere os ativos com npm run build e otimize o Laravel no deploy.

## Licença

Consulte [LICENSE](LICENSE).
