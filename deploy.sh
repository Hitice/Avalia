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

falhou() {
    echo ""
    echo "FALHOU. Voltando para ${anterior:0:8}."

    git reset --hard "$anterior"
    composer install --no-dev --optimize-autoloader --no-interaction --quiet
    npm run build --silent
    php artisan config:cache --quiet
    php artisan route:cache --quiet
    php artisan view:cache --quiet
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
composer install --no-dev --optimize-autoloader --no-interaction --quiet
npm ci --silent

echo "==> Front"
npm run build --silent

echo "==> Migrations"
php artisan migrate --force

echo "==> Caches"
php artisan config:cache --quiet
php artisan route:cache --quiet
php artisan view:cache --quiet

# Antes de subir, e nao depois: erro de configuracao nao quebra nada visivel,
# entao descobrir depois significa descobrir tarde, com gente usando.
echo "==> Conferindo o ambiente"
php artisan avalia:ambiente

# O worker segura o codigo antigo em memoria: sem reiniciar, a fila continua
# rodando a versao que acabou de sair.
echo "==> Fila"
sudo systemctl restart avalia-fila

echo "==> Subindo"
php artisan up

trap - ERR

echo ""
echo "No ar: $(git rev-parse --short HEAD)"
