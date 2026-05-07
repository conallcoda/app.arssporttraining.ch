#!/usr/bin/env bash

set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TARGET_DIR="${CMS_GLOBAL_BIN_DIR:-$HOME/.local/bin}"
TARGET_PATH="${TARGET_DIR}/cms-packages"
SOURCE_PATH="${PROJECT_ROOT}/scripts/cms-packages.sh"

mkdir -p "${TARGET_DIR}"
ln -sf "${SOURCE_PATH}" "${TARGET_PATH}"

echo "Installed cms-packages -> ${TARGET_PATH}"
echo "Add ${TARGET_DIR} to PATH if it is not already available in your shell."
