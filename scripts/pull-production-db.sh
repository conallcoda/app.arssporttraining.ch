#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENV_FILE="${ENV_FILE:-${ROOT_DIR}/.env.production}"
SSH_HOST="${SSH_HOST:-coda}"
LOCAL_DIR="${LOCAL_DIR:-${ROOT_DIR}/import/dumps}"
TIMESTAMP="$(date +%Y%m%d-%H%M%S)"

if [[ ! -f "${ENV_FILE}" ]]; then
    echo "Missing env file: ${ENV_FILE}" >&2
    exit 1
fi

mkdir -p "${LOCAL_DIR}"

set -a
# shellcheck disable=SC1090
source "${ENV_FILE}"
set +a

: "${DB_HOST:?DB_HOST is required in ${ENV_FILE}}"
: "${DB_PORT:=3306}"
: "${DB_DATABASE:?DB_DATABASE is required in ${ENV_FILE}}"
: "${DB_USERNAME:?DB_USERNAME is required in ${ENV_FILE}}"
: "${DB_PASSWORD:?DB_PASSWORD is required in ${ENV_FILE}}"

REMOTE_BASENAME="${DB_DATABASE}-${TIMESTAMP}.sql.gz"
REMOTE_PATH="/tmp/${REMOTE_BASENAME}"
LOCAL_PATH="${LOCAL_DIR}/${REMOTE_BASENAME}"

echo "Creating production dump on ${SSH_HOST}:${REMOTE_PATH}"

ssh "${SSH_HOST}" 'bash -se' <<EOF
set -euo pipefail
export DB_HOST=$(printf '%q' "${DB_HOST}")
export DB_PORT=$(printf '%q' "${DB_PORT}")
export DB_DATABASE=$(printf '%q' "${DB_DATABASE}")
export DB_USERNAME=$(printf '%q' "${DB_USERNAME}")
export DB_PASSWORD=$(printf '%q' "${DB_PASSWORD}")
export REMOTE_PATH=$(printf '%q' "${REMOTE_PATH}")

MYSQL_PWD="\${DB_PASSWORD}" mysqldump \
  --single-transaction \
  --quick \
  --skip-lock-tables \
  --default-character-set=utf8mb4 \
  --host="\${DB_HOST}" \
  --port="\${DB_PORT}" \
  --user="\${DB_USERNAME}" \
  "\${DB_DATABASE}" | gzip -c > "\${REMOTE_PATH}"
EOF

echo "Downloading dump to ${LOCAL_PATH}"
scp "${SSH_HOST}:${REMOTE_PATH}" "${LOCAL_PATH}"

echo "Removing remote dump ${REMOTE_PATH}"
ssh "${SSH_HOST}" "rm -f $(printf '%q' "${REMOTE_PATH}")"

echo "Done: ${LOCAL_PATH}"
