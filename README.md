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

## Avalia 360 (cobrança)

O Avalia 360 é a estrutura de venda parcelada em boleto e Pix da Avalia One.
A fase entregue é a captação: a página pública e o pré-cadastro de produtor.

Rotas:

    GET  /cobranca                 apresentação do produto
    POST /cobranca/pre-cadastro    pré-cadastro, com teto de 10 por minuto

Para rodar só os testes do módulo:

    vendor/bin/pest --filter=Cobranca

O pré-cadastro grava em `interessados_cobranca` e avisa por e-mail o endereço
de `config/empresa.php`. Nenhuma variável de ambiente nova foi introduzida:
o envio usa a configuração de e-mail que já existe, e o destinatário sai do
cadastro da empresa.

Documento e WhatsApp são gravados cifrados (cast `encrypted`), então dependem
de `APP_KEY`. Trocar a chave da aplicação torna esses campos ilegíveis, sem
erro visível na tela: quem for rotacionar `APP_KEY` precisa reescrever esses
registros antes.

### Modelo de dados

    produtores           quem vende parcelado; guarda a subconta no provedor
      +- produtos_360    o que ele vende
           +- ofertas_360    condições de venda (valor, parcelas, entrada, slug)
                +- pedidos_360    a venda, com preço e taxa COPIADOS da oferta
                     +- parcelas_360      número 0 é a entrada
                     +- lancamentos_360   o razão, imutável

Regras que o schema carrega:

- **Preço e taxa são copiados** da oferta para o pedido na compra. Reajuste de
  hoje não mexe em venda de ontem, a mesma regra que vale para consulta e
  fatura no resto do sistema.
- **A parcela só nasce depois de `efetivado`**, que exige contrato assinado e
  entrada confirmada (`Pedido360::podeParcelar()`). Emitir boleto antes disso é
  cobrar por um contrato que ninguém assinou.
- **O razão é imutável**: `lancamentos_360` não tem `updated_at`, e estorno é
  lançamento de sinal contrário. Saldo não é coluna em lugar nenhum, é a soma
  da tabela.
- **Os quatro lançamentos de um pagamento somam zero** (bruto, taxa do
  provedor, taxa da plataforma, repasse). É a invariante que pega erro de
  arredondamento sem ninguém reconferir extrato: `tests/Unit/RateioTest.php`.
- **O split vai em percentual**, não em valor: se a cobrança mudar de valor
  depois de criada, a divisão continua certa.
- **Vencido vem do provedor**, não do nosso calendário. Relógio de servidor
  decidindo dinheiro é como nasce divergência com o extrato.

O dado pessoal do cliente final mora no pedido, cifrado, e não vira cadastro:
quem compra de um produtor não entra na base da Avalia One.

Testes da fase: `vendor/bin/pest --filter=Cobranca360` e `--filter=Rateio`.
Nenhum deles fala com o provedor de verdade; a integração é mockada com
`Http::fake`, porque teste que cria cobrança real cria cobrança que ninguém
apaga.

## Licença

Consulte [LICENSE](LICENSE).
