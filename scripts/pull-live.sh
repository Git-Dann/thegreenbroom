#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

GB_SSH_ALIAS="${GB_SSH_ALIAS:-greenbroom}"

mkdir -p "${ROOT}/remote-sync/thegreenbroom" "${ROOT}/remote-sync/greenbroom-core"

rsync -az --delete -e "ssh" \
  "${GB_SSH_ALIAS}:themes/thegreenbroom/" \
  "${ROOT}/remote-sync/thegreenbroom/"

rsync -az --delete -e "ssh" \
  "${GB_SSH_ALIAS}:plugins/greenbroom-core/" \
  "${ROOT}/remote-sync/greenbroom-core/"

echo "Pulled live theme and plugin into remote-sync/."
