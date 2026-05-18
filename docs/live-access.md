# Live Access Notes

This repository keeps a durable copy of the live WordPress code that is reachable through temporary Fasthosts/IONOS SSH/SFTP credentials.

The host-issued credentials expire, so do not treat them as the source of truth. The source of truth should be this Git repository.

## Remote

- Host: `access-5019091254.webspace-host.com`
- Port: `22`
- Default remote directory: `/home/www/public/wp-content`
- Mirrored theme: `themes/thegreenbroom`
- Mirrored plugin: `plugins/greenbroom-core`

## Pull Latest Live Code

Set the temporary SSH username in your shell, then run:

```bash
export GB_SSH_USER=suXXXXXXX
./scripts/pull-live.sh
```

SSH will prompt for the current temporary password. The password is not stored by the scripts.

## Deploy Code

Preview first:

```bash
export GB_SSH_USER=suXXXXXXX
./scripts/push-live.sh
```

Apply the deploy:

```bash
export GB_SSH_USER=suXXXXXXX
./scripts/push-live.sh --apply
```

The deploy syncs only:

- `remote-sync/thegreenbroom/` to `themes/thegreenbroom/`
- `remote-sync/greenbroom-core/` to `plugins/greenbroom-core/`

## Long-Term Access

When the temporary webspace login expires, regenerate SSH/SFTP credentials in Fasthosts/IONOS and re-run the scripts with the new `GB_SSH_USER`.

For the codebase itself, use Git history and a private remote repository so we always retain the working copy even when hosting access rotates.

## WordPress Admin Access

WordPress admin is the day-to-day access route and does not require logging into Fasthosts.

- Admin URL: `https://thegreenbroom.uk/wp-login.php`
- Current admin username for `danielclindsay+admin@googlemail.com`: `danclindsay`
- Secondary administrator account present on the site: `info`

Email login is disabled on this site, so use the WordPress username rather than the email address.

After login, administrators are redirected directly to `wp-admin/users.php` so the Fasthosts landing page is skipped.

For small emergency code edits, use the WordPress admin file editors:

- Theme editor: `https://thegreenbroom.uk/wp-admin/theme-editor.php`
- Plugin editor: `https://thegreenbroom.uk/wp-admin/plugin-editor.php`

For normal code work, edit this repository first, commit the change, then deploy with `scripts/push-live.sh` while temporary SSH/SFTP credentials are available.
