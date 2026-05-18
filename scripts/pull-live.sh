#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

GB_SSH_HOST="${GB_SSH_HOST:-access-5019091254.webspace-host.com}"
GB_SSH_PORT="${GB_SSH_PORT:-22}"

if [[ -z "${GB_SSH_USER:-}" ]]; then
  echo "Set GB_SSH_USER to the current temporary SSH/SFTP username." >&2
  exit 1
fi

SSH_OPTS="ssh -p ${GB_SSH_PORT} -o StrictHostKeyChecking=accept-new"

mkdir -p "${ROOT}/remote-sync/thegreenbroom" "${ROOT}/remote-sync/greenbroom-core"

rsync -az --delete -e "${SSH_OPTS}" \
  "${GB_SSH_USER}@${GB_SSH_HOST}:themes/thegreenbroom/" \
  "${ROOT}/remote-sync/thegreenbroom/"

rsync -az --delete -e "${SSH_OPTS}" \
  "${GB_SSH_USER}@${GB_SSH_HOST}:plugins/greenbroom-core/" \
  "${ROOT}/remote-sync/greenbroom-core/"

echo "Pulled live theme and plugin into remote-sync/."

