# Live Access Notes

The source of truth for the live site is this Git repository.

- GitHub repo: https://github.com/Git-Dann/thegreenbroom (private)
- Live site: https://thegreenbroom.uk

## Normal deploy workflow

Edit files in `remote-sync/`, commit, and push to `main`:

```bash
git add remote-sync/
git commit -m "describe the change"
git push
```

GitHub Actions automatically rsyncs `remote-sync/thegreenbroom/` → `themes/thegreenbroom/` and `remote-sync/greenbroom-core/` → `plugins/greenbroom-core/` on the live server. No FastHosts login needed.

Watch a deploy: https://github.com/Git-Dann/thegreenbroom/actions

## Pulling live code back to this repo

If you've made emergency edits directly on the server (e.g. via the WordPress theme/plugin editor) and need to sync them back here:

```bash
./scripts/pull-live.sh
git add remote-sync/
git commit -m "sync live edits back to repo"
git push
```

`pull-live.sh` uses key-based SSH auth — no password needed.

## Remote server details

- SSH alias: `greenbroom` (configured in `~/.ssh/config`)
- Actual host: `access-5019091254.webspace-host.com`
- Port: `22`
- Default remote directory: `/home/www/public/wp-content`

## When FastHosts credentials expire

FastHosts temporary credentials expire every 48 hours. This does **not** affect GitHub Actions deploys (the SSH key stored as a secret doesn't expire). It only affects local `pull-live.sh` runs.

When you need local SSH access again:

1. Log into FastHosts and regenerate credentials
2. Note the new username (e.g. `su123456`)
3. Run:

```bash
./scripts/rotate-credentials.sh su123456
```

4. Update the `SSH_USER` secret in GitHub so Actions keeps working:

```bash
gh secret set SSH_USER --repo Git-Dann/thegreenbroom --body "su123456"
```

The SSH key itself doesn't change — only the username needs updating.

## SSH key location

- Private key: `~/.ssh/greenbroom`
- Public key: `~/.ssh/greenbroom.pub`
- GitHub secret: `SSH_PRIVATE_KEY` (copy of the private key, for Actions deploys)

## WordPress admin access

- Admin URL: https://thegreenbroom.uk/wp-login.php
- Username: `danclindsay` (for `danielclindsay+admin@googlemail.com`)
- Secondary admin: `info`

Email login is disabled — use the WordPress username, not the email address.

Emergency file editors (no SSH needed):
- Theme editor: https://thegreenbroom.uk/wp-admin/theme-editor.php
- Plugin editor: https://thegreenbroom.uk/wp-admin/plugin-editor.php
