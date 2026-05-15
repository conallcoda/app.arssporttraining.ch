#!/usr/bin/env bash

set -euo pipefail

if [[ -f .env ]]; then
    set -a
    # shellcheck disable=SC1091
    source .env
    set +a
fi

PHP_BIN="${PHP_BIN:-${DEPLOY_PHP_BIN:-php}}"
COMPOSER_BIN="${COMPOSER_BIN:-${DEPLOY_COMPOSER_BIN:-composer}}"

echo "Using PHP binary: ${PHP_BIN}"
echo "Using Composer binary: ${COMPOSER_BIN}"

"${COMPOSER_BIN}" install --no-dev --prefer-dist --optimize-autoloader

"${PHP_BIN}" artisan migrate --force
"${PHP_BIN}" artisan optimize:clear
"${PHP_BIN}" artisan config:cache
"${PHP_BIN}" artisan route:cache
"${PHP_BIN}" artisan view:cache
"${PHP_BIN}" artisan event:cache
