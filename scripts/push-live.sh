#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

GB_SSH_ALIAS="${GB_SSH_ALIAS:-greenbroom}"
APPLY=0

if [[ "${1:-}" == "--apply" ]]; then
  APPLY=1
fi

RSYNC_FLAGS=(-az --delete)

if [[ "${APPLY}" -ne 1 ]]; then
  RSYNC_FLAGS+=(--dry-run)
  echo "Dry run only. Re-run with --apply to deploy."
fi

rsync "${RSYNC_FLAGS[@]}" -e "ssh" \
  "${ROOT}/remote-sync/thegreenbroom/" \
  "${GB_SSH_ALIAS}:themes/thegreenbroom/"

rsync "${RSYNC_FLAGS[@]}" -e "ssh" \
  "${ROOT}/remote-sync/greenbroom-core/" \
  "${GB_SSH_ALIAS}:plugins/greenbroom-core/"

if [[ "${APPLY}" -eq 1 ]]; then
  echo "Deployed theme and plugin to live wp-content."
fi
