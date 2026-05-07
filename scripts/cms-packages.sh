#!/usr/bin/env bash

set -euo pipefail

DEFAULT_SHARED_ROOT="/Users/conalloreilly/Development/coda-packages"
PACKAGE_NAMES=(${CMS_PACKAGES:-cms form-kit})
PACKAGE_NAMESPACE="${CMS_PACKAGE_NAMESPACE:-coda}"
SHARED_ROOT="${SHARED_ROOT:-$DEFAULT_SHARED_ROOT}"
COMMIT_MESSAGE="${CMS_SNAPSHOT_COMMIT_MESSAGE:-Update packaged CMS snapshot}"
PUSH_AFTER_COMMIT="${CMS_SNAPSHOT_PUSH:-1}"

find_project_root() {
    local dir="${PROJECT_ROOT:-$PWD}"

    while [[ "${dir}" != "/" ]]; do
        if [[ -f "${dir}/composer.json" ]]; then
            printf '%s\n' "${dir}"
            return 0
        fi

        dir="$(dirname "${dir}")"
    done

    echo "Unable to find composer.json from ${PROJECT_ROOT:-$PWD}" >&2
    exit 1
}

PROJECT_ROOT="$(find_project_root)"
COMPOSER_FILE="${PROJECT_ROOT}/composer.json"
LOCK_FILE="${PROJECT_ROOT}/composer.lock"
PACKAGES_DIR="${PROJECT_ROOT}/packages"

package_refs=()
for package_name in "${PACKAGE_NAMES[@]}"; do
    package_refs+=("${PACKAGE_NAMESPACE}/${package_name}")
done

composer_update_packages() {
    if ! command -v composer >/dev/null 2>&1; then
        echo "Composer is required to refresh composer.lock" >&2
        exit 1
    fi

    (
        cd "${PROJECT_ROOT}"
        composer update "${package_refs[@]}"
    )
}

ensure_shared_source() {
    local package_name="$1"
    local source_dir="${SHARED_ROOT}/${package_name}"

    if [[ ! -d "${source_dir}" ]]; then
        echo "Missing shared package source: ${source_dir}" >&2
        exit 1
    fi
}

sync_packages() {
    local package_name source_dir target_dir

    mkdir -p "${PACKAGES_DIR}"

    for package_name in "${PACKAGE_NAMES[@]}"; do
        source_dir="${SHARED_ROOT}/${package_name}"
        target_dir="${PACKAGES_DIR}/${package_name}"
        ensure_shared_source "${package_name}"
        mkdir -p "${target_dir}"
        rsync -a --delete "${source_dir}/" "${target_dir}/"
    done

    echo "Shared packages synced into ${PACKAGES_DIR}"
}

set_repository_mode() {
    local mode="$1"

    php /dev/stdin "${COMPOSER_FILE}" "${SHARED_ROOT}" "${mode}" "${PACKAGE_NAMESPACE}" "${PACKAGE_NAMES[@]}" <<'PHP'
<?php
$composerFile = $argv[1];
$sharedRoot = rtrim($argv[2], '/');
$mode = $argv[3];
$namespace = $argv[4];
$packageNames = array_slice($argv, 5);

$json = json_decode(file_get_contents($composerFile), true, flags: JSON_THROW_ON_ERROR);
$repositories = $json['repositories'] ?? [];

foreach ($packageNames as $packageName) {
    $url = $mode === 'shared'
        ? $sharedRoot . '/' . $packageName
        : 'packages/' . $packageName;

    $repositories[$packageName] = [
        'type' => 'path',
        'url' => $url,
        'options' => [
            'symlink' => $mode === 'shared',
        ],
    ];
}

$json['repositories'] = $repositories;

$encoded = json_encode(
    $json,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
);

file_put_contents($composerFile, $encoded . PHP_EOL);
PHP

    echo "Composer repositories now point to ${mode} package paths"
}

current_repository_mode() {
    php /dev/stdin "${COMPOSER_FILE}" "${SHARED_ROOT}" "${PACKAGE_NAMES[@]}" <<'PHP'
<?php
$composerFile = $argv[1];
$sharedRoot = rtrim($argv[2], '/');
$packageNames = array_slice($argv, 3);

$json = json_decode(file_get_contents($composerFile), true, flags: JSON_THROW_ON_ERROR);
$repositories = $json['repositories'] ?? [];
$shared = true;
$vendored = true;

foreach ($packageNames as $packageName) {
    $repo = $repositories[$packageName] ?? null;

    if (! is_array($repo) || ($repo['type'] ?? null) !== 'path') {
        $shared = false;
        $vendored = false;
        continue;
    }

    $url = $repo['url'] ?? null;
    $symlink = $repo['options']['symlink'] ?? null;

    if ($url !== $sharedRoot . '/' . $packageName || $symlink !== true) {
        $shared = false;
    }

    if ($url !== 'packages/' . $packageName || $symlink !== false) {
        $vendored = false;
    }
}

if ($shared) {
    echo "shared";
    exit(0);
}

if ($vendored) {
    echo "vendored";
    exit(0);
}

echo "custom";
PHP
}

cleanup_vendored_packages() {
    local package_name package_dir

    for package_name in "${PACKAGE_NAMES[@]}"; do
        package_dir="${PACKAGES_DIR}/${package_name}"

        if [[ -d "${package_dir}" ]]; then
            rm -rf "${package_dir}"
        fi
    done

    if [[ -d "${PACKAGES_DIR}" ]] && [[ -z "$(find "${PACKAGES_DIR}" -mindepth 1 -maxdepth 1 -print -quit)" ]]; then
        rmdir "${PACKAGES_DIR}"
    fi

    echo "Removed repo-local package snapshots"
}

stage_snapshot() {
    local path_args=()
    local package_name

    for package_name in "${PACKAGE_NAMES[@]}"; do
        if [[ -e "${PACKAGES_DIR}/${package_name}" ]]; then
            path_args+=("packages/${package_name}")
        fi
    done

    if [[ -f "${COMPOSER_FILE}" ]]; then
        path_args+=("composer.json")
    fi

    if [[ -f "${LOCK_FILE}" ]]; then
        path_args+=("composer.lock")
    fi

    if [[ "${#path_args[@]}" -eq 0 ]]; then
        echo "Nothing to stage" >&2
        exit 1
    fi

    (
        cd "${PROJECT_ROOT}"
        git add -- "${path_args[@]}"
    )
}

snapshot_has_staged_changes() {
    (
        cd "${PROJECT_ROOT}"
        ! git diff --cached --quiet -- packages composer.json composer.lock
    )
}

commit_snapshot() {
    if ! snapshot_has_staged_changes; then
        echo "No snapshot changes to commit"
        return 1
    fi

    (
        cd "${PROJECT_ROOT}"
        git commit -m "${COMMIT_MESSAGE}"
    )
}

push_snapshot() {
    if [[ "${PUSH_AFTER_COMMIT}" != "1" ]]; then
        echo "Skipping push because CMS_SNAPSHOT_PUSH=${PUSH_AFTER_COMMIT}"
        return 0
    fi

    (
        cd "${PROJECT_ROOT}"
        git push
    )
}

restore_shared_workspace() {
    set_repository_mode "shared"
    composer_update_packages
    cleanup_vendored_packages
}

run_release() {
    local committed=0
    local pushed=0
    local cleanup_needed=0

    release_cleanup() {
        if [[ "${cleanup_needed}" -eq 1 ]]; then
            restore_shared_workspace
        fi
    }

    trap release_cleanup EXIT

    sync_packages
    set_repository_mode "vendored"
    cleanup_needed=1
    composer_update_packages
    stage_snapshot

    if commit_snapshot; then
        committed=1
    fi

    push_snapshot
    pushed=1

    restore_shared_workspace
    cleanup_needed=0
    trap - EXIT

    if [[ "${committed}" -eq 1 && "${pushed}" -eq 1 ]]; then
        echo "Release snapshot committed, pushed, and shared mode restored"
    elif [[ "${committed}" -eq 1 ]]; then
        echo "Release snapshot committed and shared mode restored"
    elif [[ "${pushed}" -eq 1 ]]; then
        echo "No snapshot changes to commit; pushed current branch and restored shared mode"
    else
        echo "Shared mode restored; there were no snapshot changes to commit"
    fi
}

print_status() {
    local mode
    mode="$(current_repository_mode)"

    echo "CMS package configuration"
    echo "  project: ${PROJECT_ROOT}"
    echo "  shared root: ${SHARED_ROOT}"
    echo "  repository mode: ${mode}"

    if [[ -d "${PACKAGES_DIR}" ]]; then
        echo "  repo-local packages: ${PACKAGES_DIR}"
    else
        echo "  repo-local packages: none"
    fi
}

usage() {
    cat <<'EOF'
Usage: ./scripts/cms-packages.sh <command>

Commands:
  status              Show current package repository mode
  sync                Copy shared package code into ./packages
  use-shared          Point composer.json at the shared package workspace
  use-vendored        Point composer.json at repo-local ./packages snapshots
  refresh-lock        Run composer update for the configured CMS packages
  snapshot-commit     Sync packages, switch to vendored paths, refresh lock, stage and commit
  cleanup             Switch back to shared paths, refresh lock, delete ./packages
  release             Snapshot, commit, push, then restore shared mode and remove ./packages
EOF
}

command="${1:-status}"

case "${command}" in
    status)
        print_status
        ;;
    sync)
        sync_packages
        ;;
    use-shared)
        set_repository_mode "shared"
        ;;
    use-vendored)
        set_repository_mode "vendored"
        ;;
    refresh-lock)
        composer_update_packages
        ;;
    snapshot-commit)
        sync_packages
        set_repository_mode "vendored"
        composer_update_packages
        stage_snapshot
        commit_snapshot
        ;;
    cleanup)
        restore_shared_workspace
        ;;
    release)
        run_release
        ;;
    *)
        usage >&2
        exit 1
        ;;
esac
