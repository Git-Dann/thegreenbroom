# Live Access Notes

This repository keeps a durable copy of the live WordPress code that is reachable through SSH.

## Remote

- Host alias: `greenbroom` (configured in `~/.ssh/config`)
- Actual host: `access-5019091254.webspace-host.com`
- Port: `22`
- Default remote directory: `/home/www/public/wp-content`
- Mirrored theme: `themes/thegreenbroom`
- Mirrored plugin: `plugins/greenbroom-core`

SSH key-based auth is set up — no password prompt, no FastHosts login needed for normal use.

## Pull Latest Live Code

```bash
./scripts/pull-live.sh
```

## Deploy Code

Preview first:

```bash
./scripts/push-live.sh
```

Apply the deploy:

```bash
./scripts/push-live.sh --apply
```

The deploy syncs only:

- `remote-sync/thegreenbroom/` to `themes/thegreenbroom/`
- `remote-sync/greenbroom-core/` to `plugins/greenbroom-core/`

## When FastHosts Credentials Expire

FastHosts temporary credentials expire every 48 hours. When that happens:

1. Log into FastHosts once and regenerate the SSH/SFTP credentials
2. Note the new username (e.g. `su123456`)
3. Run:

```bash
./scripts/rotate-credentials.sh su123456
```

The script patches `~/.ssh/config` with the new username and tests key auth. If the key survived the regeneration (common), you're done immediately. If FastHosts reset `authorized_keys`, the script will prompt for the new password once to re-install the key — after that, passwordless access is restored.

## SSH Key Location

- Private key: `~/.ssh/greenbroom`
- Public key: `~/.ssh/greenbroom.pub`

The `~/.ssh/config` `Host greenbroom` block points at the key and the current username.

## WordPress Admin Access

WordPress admin is the day-to-day access route and does not require logging into FastHosts.

- Admin URL: `https://thegreenbroom.uk/wp-login.php`
- Current admin username for `danielclindsay+admin@googlemail.com`: `danclindsay`
- Secondary administrator account present on the site: `info`

Email login is disabled on this site, so use the WordPress username rather than the email address.

After login, administrators are redirected directly to `wp-admin/users.php` so the FastHosts landing page is skipped.

For small emergency code edits, use the WordPress admin file editors:

- Theme editor: `https://thegreenbroom.uk/wp-admin/theme-editor.php`
- Plugin editor: `https://thegreenbroom.uk/wp-admin/plugin-editor.php`

For normal code work, edit this repository first, commit the change, then deploy with `scripts/push-live.sh`.
