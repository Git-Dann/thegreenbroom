#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

GB_SSH_HOST="${GB_SSH_HOST:-access-5019091254.webspace-host.com}"
GB_SSH_PORT="${GB_SSH_PORT:-22}"
APPLY=0

if [[ "${1:-}" == "--apply" ]]; then
  APPLY=1
fi

if [[ -z "${GB_SSH_USER:-}" ]]; then
  echo "Set GB_SSH_USER to the current temporary SSH/SFTP username." >&2
  exit 1
fi

SSH_OPTS="ssh -p ${GB_SSH_PORT} -o StrictHostKeyChecking=accept-new"
RSYNC_FLAGS=(-az --delete)

if [[ "${APPLY}" -ne 1 ]]; then
  RSYNC_FLAGS+=(--dry-run)
  echo "Dry run only. Re-run with --apply to deploy."
fi

rsync "${RSYNC_FLAGS[@]}" -e "${SSH_OPTS}" \
  "${ROOT}/remote-sync/thegreenbroom/" \
  "${GB_SSH_USER}@${GB_SSH_HOST}:themes/thegreenbroom/"

rsync "${RSYNC_FLAGS[@]}" -e "${SSH_OPTS}" \
  "${ROOT}/remote-sync/greenbroom-core/" \
  "${GB_SSH_USER}@${GB_SSH_HOST}:plugins/greenbroom-core/"

if [[ "${APPLY}" -eq 1 ]]; then
  echo "Deployed theme and plugin to live wp-content."
fi

