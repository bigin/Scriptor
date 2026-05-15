# Installing Scriptor on shared hosting

Scriptor's webroot is `public/`. The library + configs + database live
above it (`boot/`, `vendor/`, `data/`, `editor/`, `themes/`). Most
shared hosting providers fix the webroot to `public_html/` (or
`htdocs/`, `www/`) and won't let you point it elsewhere — so you have
to bridge `public/` to that fixed location.

Two acceptable options, in order of preference.

## Option A — symlink `public_html` → `public` (preferred)

Works on every host that allows symlinks (the vast majority of
shared-hosting LAMP stacks; many cPanel-based ones; nearly all
modern SFTP-with-shell providers).

```bash
# In SSH on the host. Assumes Scriptor was cloned/uploaded to
# /home/<user>/Scriptor and the fixed webroot is /home/<user>/public_html.
cd /home/<user>
rm -rf public_html              # remove any default placeholder content
ln -s /home/<user>/Scriptor/public public_html
```

Verify:

```bash
curl -sI http://<your-domain>/   # → 200, Scriptor frontend
```

No source-code edits needed. Updates are `git pull` (or re-upload)
and the symlink keeps pointing.

## Option B — copy `public/` into `public_html/`

When symlinks are forbidden or stripped. Trade-off: every
Scriptor update means re-copying `public/`.

```bash
# Install Scriptor outside the webroot:
cd /home/<user>
git clone https://github.com/bigin/Scriptor.git   # or upload via SFTP
cd Scriptor
composer install                                  # if you have shell

# Copy public/ contents into the fixed webroot:
cp -a /home/<user>/Scriptor/public/. /home/<user>/public_html/
```

Then patch the copy's `index.php` so its relative `require` finds
`boot.php` at the original install root:

```php
// /home/<user>/public_html/index.php — only the require line changes:
require_once '/home/<user>/Scriptor/boot.php';
```

Replace `/home/<user>/Scriptor` with the absolute path to your
Scriptor install root.

When Scriptor updates, re-copy `public/` and re-apply the same edit:

```bash
cp -a /home/<user>/Scriptor/public/. /home/<user>/public_html/
# then re-edit public_html/index.php as above
```

The static assets under `public/themes/<name>/`, `public/editor-assets/`,
and the runtime `public/uploads/` directory all live inside
`public_html/` after the copy, so the web server finds them
directly. Uploads written by Scriptor will land under
`public_html/uploads/`.

## Verifying the install

After either option, probe a known good path and a known sensitive
path:

```bash
curl -sI http://<your-domain>/                            # 200
curl -sI http://<your-domain>/data/imanager.db            # 404 (data/ is outside the webroot)
curl -sI http://<your-domain>/boot/App.php                # 404
curl -sI http://<your-domain>/vendor/autoload.php         # 404
curl -sI http://<your-domain>/themes/basic/template.php   # 404 (theme PHP is outside the webroot)
curl -sI http://<your-domain>/themes/basic/css/styles.css # 200 (theme static IS inside the webroot)
```

If `data/imanager.db` returns anything other than `404`, the webroot
is wrong — re-check your symlink or your copy. The database must not
be downloadable.

## File permissions

`public/uploads/`, `data/cache/`, `data/logs/`, and
`data/settings/custom.scriptor-config.php` need to be writable by
the PHP process. Most shared hosts run PHP as your SSH/SFTP user, so
the default `0755` / `0644` permissions you get from a fresh
`git clone` + `composer install` already work.

If your provider runs PHP as a different system user (older mod_php
setups), set those directories to `0775` and the group to the PHP
user's group.

## Updates

```bash
cd /home/<user>/Scriptor
git pull
composer install --no-dev
vendor/bin/imanager schema:migrate --db=data/imanager.db
```

With Option B, also re-copy `public/` afterwards.
