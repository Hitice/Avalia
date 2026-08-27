#!/usr/bin/env bash
#
# Publica a versao que esta em origin/main.
#
# Chamado pelo GitHub depois de a suite passar, ou a mao por SSH. Os dois
# caminhos usam este mesmo arquivo de proposito: publicacao manual que segue
# outro roteiro e a que quebra, porque e sempre a que ninguem testou.
#
# Se qualquer passo falhar, volta para a versao anterior e sobe ela. O padrao do
# `set -e` sozinho deixaria o site fora do ar esperando alguem perceber.
#
# LIMITE, e vale saber: o retorno e do CODIGO, nao do banco. Migration que ja
# rodou continua aplicada. Por isso migration que remove coluna ou tabela nunca
# entra junto com o codigo que para de usa-la: primeiro publica o codigo, depois,
# noutra versao, remove a coluna.

set -euo pipefail

cd "$(dirname "$0")"

anterior="$(git rev-parse HEAD)"

# O Composer depende de proc_open, e a hospedagem compartilhada da Hostinger
# desabilita essa funcao junto com exec e shell_exec. Node so existe no VPS e nos
# planos Business para cima. Onde nao rodarem, vendor e front chegam prontos,
# enviados pelo GitHub antes desta chamada.
#
# Uma funcao so, usada tambem no retorno: a rotina de emergencia que segue outro
# caminho e a que falha justo quando e chamada.
dependencias() {
    if php -r 'exit(function_exists("proc_open") ? 0 : 1);' && command -v composer > /dev/null 2>&1; then
        composer install --no-dev --optimize-autoloader --no-interaction --quiet
    else
        echo "    dependencias enviadas prontas (composer nao roda neste servidor)"
    fi

    if command -v npm > /dev/null 2>&1; then
        npm ci --silent && npm run build --silent
    else
        echo "    front enviado pronto (sem node neste servidor)"
    fi

    # Quando o composer nao roda aqui, o script que monta a lista de pacotes do
    # Laravel tambem nao roda. Sem ela os caches saem incompletos.
    php artisan package:discover --quiet
}

caches() {
    php artisan config:cache --quiet
    php artisan route:cache --quiet
    php artisan view:cache --quiet
}

falhou() {
    echo ""
    echo "FALHOU. Voltando para ${anterior:0:8}."

    git reset --hard "$anterior"
    dependencias
    caches
    php artisan up

    echo "Versao anterior no ar. O banco NAO foi revertido: confira as migrations."
    exit 1
}

trap falhou ERR

echo "==> Tirando do ar"
# `|| true` porque `down` falha quando ja esta em manutencao, e uma publicacao
# repetida depois de erro nao pode parar por causa disso.
php artisan down --render=errors::503 --retry=15 || true

echo "==> Buscando a versao nova"
git fetch --quiet origin main
git reset --hard --quiet origin/main
echo "    $(git rev-parse --short HEAD)  $(git log -1 --format=%s)"

echo "==> Dependencias"
dependencias

echo "==> Migrations"
php artisan migrate --force

# Carga da base de prospeccao. Idempotente: so insere codigo que ainda nao
# existe, entao repetir a publicacao nao duplica lead nem desfaz correcao feita
# na tela. Fica aqui, e nao numa migration, porque migration de mil linhas de
# dado rodaria tambem na suite de testes.
echo "==> Base de leads"
php artisan db:seed --class=LeadsSeeder --force

echo "==> Caches"
caches

# Antes de subir, e nao depois: erro de configuracao nao quebra nada visivel,
# entao descobrir depois significa descobrir tarde, com gente usando.
echo "==> Conferindo o ambiente"
php artisan avalia:ambiente

# O worker segura o codigo antigo em memoria: sem reiniciar, a fila continua
# rodando a versao que acabou de sair.
#
# Hospedagem compartilhada nao mantem processo de pe. La a fila roda em modo
# sincrono, o que hoje nao custa nada: nenhuma acao da aplicacao e enfileirada.
if systemctl list-unit-files 2> /dev/null | grep -q '^avalia-fila'; then
    echo "==> Fila"
    sudo systemctl restart avalia-fila
fi

echo "==> Subindo"
php artisan up

trap - ERR

echo ""
echo "No ar: $(git rev-parse --short HEAD)"
