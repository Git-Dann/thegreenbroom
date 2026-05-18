#!/usr/bin/env bash
# Run this after regenerating SSH credentials in FastHosts.
# Usage: ./scripts/rotate-credentials.sh suNEWUSERNAME
set -euo pipefail

NEW_USER="${1:-}"
if [[ -z "${NEW_USER}" ]]; then
  echo "Usage: $0 <new-ssh-username>" >&2
  exit 1
fi

SSH_CONFIG="${HOME}/.ssh/config"
CURRENT_USER="$(awk '/^Host greenbroom/{found=1} found && /^\s+User /{print $2; exit}' "${SSH_CONFIG}")"

if [[ -z "${CURRENT_USER}" ]]; then
  echo "Could not find 'Host greenbroom' block in ${SSH_CONFIG}" >&2
  exit 1
fi

if [[ "${CURRENT_USER}" == "${NEW_USER}" ]]; then
  echo "Username is already ${NEW_USER} — no change needed."
  exit 0
fi

sed -i '' "/^Host greenbroom/,/^Host /{s/^\(  *User \)${CURRENT_USER}$/\1${NEW_USER}/}" "${SSH_CONFIG}"
echo "Updated greenbroom SSH user: ${CURRENT_USER} → ${NEW_USER}"

# Test key-based auth; if it fails, re-upload the public key with a password prompt
echo "Testing key-based auth..."
if ssh -o BatchMode=yes -o ConnectTimeout=10 greenbroom true 2>/dev/null; then
  echo "Key auth works — no password needed."
else
  echo "Key auth failed (authorized_keys may have been reset)."
  echo "Enter the new FastHosts password to re-install the key:"
  SSHPASS_BIN="$(which sshpass 2>/dev/null || true)"
  if [[ -n "${SSHPASS_BIN}" ]]; then
    read -rs NEW_PASS
    SSHPASS="${NEW_PASS}" sshpass -e ssh-copy-id \
      -i "${HOME}/.ssh/greenbroom.pub" \
      -p 22 \
      -o StrictHostKeyChecking=accept-new \
      "${NEW_USER}@$(awk '/^Host greenbroom/{found=1} found && /^\s+HostName /{print $2; exit}' "${SSH_CONFIG}")"
    echo "Key re-installed. Future connections will be passwordless."
  else
    echo "sshpass not found. Run manually:"
    echo "  ssh-copy-id -i ~/.ssh/greenbroom.pub -p 22 ${NEW_USER}@<host>"
  fi
fi
