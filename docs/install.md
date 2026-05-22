# Installing Scriptor

A fresh Scriptor install needs three things in the SQLite database
before the frontend can render: the **Pages** category with its
fields, the **Users** category with its fields, and at least one
admin user. `bin/scriptor install` creates all three plus a minimal
Home page so `/` works on first request.

## Prerequisites

- PHP 8.2+ (8.3 recommended)
- Composer 2
- SQLite 3.38+
- Standard PHP extensions: `mbstring`, `dom`, `json`, `gd`, `pdo_sqlite`
- A web server with its document root pointed at `public/`

The Docker option below skips all of the above except Docker itself.

## Try with Docker (fastest, no local PHP needed)

If you just want to see Scriptor run without installing PHP,
Composer, or SQLite locally, the bundled demo image is one
command:

```bash
git clone https://github.com/bigin/Scriptor.git
cd Scriptor
docker compose up -d --build
```

The stack listens on `http://localhost:8080/`. When port 8080 is
already taken (ServBay, another local container, …) override the
host port:

```bash
SCRIPTOR_DEMO_PORT=8090 docker compose up -d --build
```

The container itself always binds `80`; only the host-side port
is dynamic. Default admin login is `admin / gT5nLazzyBob` (the
container runs `bin/scriptor install` on first start with the
documented demo password). For a private demo with your own
password:

```bash
SCRIPTOR_ADMIN_PASSWORD='your-strong-secret' \
  SCRIPTOR_DEMO_PORT=8090 \
  docker compose up -d --build
```

See [`docs/demo.md`](demo.md) for what the seed creates, how to
reset, and when **not** to use this image (it is built for
exploration, not production).

## Interactive install (local dev)

```bash
git clone git@github.com:bigin/Scriptor.git
cd Scriptor
composer install
php bin/scriptor install
```

The command will:

1. Confirm the database path: `About to seed /path/to/data/imanager.db. Type INSTALL to proceed:`. Type `INSTALL` and press Enter.
2. Prompt for an admin password (8 characters minimum, echo suppressed) and ask you to confirm it. The minimum matches iManager's `PasswordFieldType` so the editor's change-password form accepts the same passwords.
3. Create the Pages and Users categories with their fields, seed an `admin` user, and add a Home page.

You should see four `[n/4]` progress lines and a final summary
block with the editor URL.

Point your web server at `public/` and visit `/`. The Home page
renders; `/editor/` accepts the credentials you just set.

## Non-interactive install (Docker, CI, scripted provisioning)

When stdin is not a TTY (CI pipelines, Docker entrypoints) you must
pass `--yes` and supply the password through `--password` or the
`SCRIPTOR_ADMIN_PASSWORD` environment variable.

```bash
SCRIPTOR_ADMIN_PASSWORD='your-strong-secret' \
  php bin/scriptor install --yes
```

or equivalently:

```bash
php bin/scriptor install --yes --password='your-strong-secret'
```

Without `--yes` the command bails with a clear error rather than
hang waiting for a confirmation that will never come.

## All options

```
php bin/scriptor install [options]

Options:
  --password=<value>   Admin password (overrides SCRIPTOR_ADMIN_PASSWORD env).
  --username=<name>    Admin username (default: admin).
  --email=<addr>       Admin email (default: admin@localhost).
  --db=<path>          Database file path (default: data/imanager.db).
  --yes, -y            Skip the "type INSTALL to proceed" prompt. Required
                       when stdin is not a TTY.
  --help, -h           Show the inline help text.
```

Password sources are tried in order:

1. `--password=...` flag
2. `SCRIPTOR_ADMIN_PASSWORD` environment variable
3. Interactive TTY prompt

Exit codes:

| Code | Meaning |
|---|---|
| 0 | Install completed |
| 1 | Already installed (Pages category exists; refusing to overwrite) |
| 2 | Invalid admin password (too short or on the blocklist) |
| 3 | Invoked under a non-CLI SAPI |
| 4 | Confirmation skipped without `--yes`, or any other unexpected error |

## Re-installing

`bin/scriptor install` refuses to run a second time against the
same database, by design. To start over, delete the database file:

```bash
rm data/imanager.db
php bin/scriptor install
```

There is intentionally no `--force` flag. If the install ever
needs to recover from a half-written state, fix the data with
direct SQL or the editor. The CLI is for greenfield seeding.

## Installing plugins

A default Scriptor install ships with **zero plugins**. Plugins
are Composer packages of `type: scriptor-plugin` that you add per
install. Discovery is automatic: Scriptor scans
`vendor/composer/installed.json` on every boot, so the next request
picks the plugin up after the `composer require` completes.

### Host install (local PHP, shared hosting)

From the Scriptor root:

```bash
composer require bigins/scriptor-markdown-pages
```

The `repositories` block in Scriptor's `composer.json` already
points Composer at the VCS sources for `bigins/*` plugins, so no
extra setup is needed.

### Docker

Container filesystems are immutable below the volumes, so the
Docker workflow is to **bake the plugin into the image** via a
build arg in your compose override:

```yaml
services:
  scriptor:
    build:
      args:
        SCRIPTOR_PLUGINS: "bigins/scriptor-markdown-pages:^0.1"
```

Then `docker compose up -d --build`. Scriptor's Dockerfile runs
`composer require $SCRIPTOR_PLUGINS` during image build, so the
plugin lands in `vendor/` and survives every restart, recreation,
or deploy.

Multiple plugins go in the same arg as a space-separated list:

```yaml
SCRIPTOR_PLUGINS: "bigins/scriptor-markdown-pages:^0.1 vendor/other-plugin:^2"
```

> **Trap: `docker exec scriptor composer require ...`** Works
> immediately because the discovery cache invalidates from
> `installed.json` mtime, but the change lives in the running
> container's layer and is wiped by the next
> `docker compose down && up`. Use it for "does this plugin even
> boot" probes; never as an install path for anything you want to
> keep.

### Disabling a plugin without uninstalling it

Set `$config['plugins']['disabled'] = ['vendor/name']` in
`data/settings/custom.scriptor-config.php`. The plugin stays in
`vendor/` but `PluginManager` skips it. Useful when bisecting a
suspected plugin bug without touching Composer.

## Security notes

- The install command only runs from the command line. A misconfig
  that exposed `bin/scriptor` over HTTP would still be refused at
  the SAPI check on the first line of the script.
- There is no default admin password. The command rejects passwords
  under 8 characters and a small blocklist of obvious defaults.
  The editor's login flow rate-limits failed attempts via
  `LoginAttempts`, so the 8-char floor plus lockout is the real
  brute-force defence; the blocklist is a copy-paste catch.
- The "already installed" check looks at the actual database, not a
  lock-file. An attacker who can delete files under `data/` cannot
  trigger a re-install that would overwrite credentials.

For the full security rationale and the design alternatives that
were considered, see
[`docs/scriptor-install-cli-plan.md`](scriptor-install-cli-plan.md).

## Troubleshooting

### "Category with slug 'pages' not found"

You started the web server before running `bin/scriptor install`.
iManager auto-creates the schema on first PDO connection, but it
does not seed any rows. Stop the server, run the install command,
then start the server again. Or just delete `data/imanager.db` and
run the install command, which is faster.

### "Refusing to proceed: stdin is not a TTY"

Add `--yes` and supply a password via `--password` or
`SCRIPTOR_ADMIN_PASSWORD`. The confirmation prompt only makes sense
when a human is at the keyboard.

### "Invalid admin password: too short"

The minimum is 8 characters, matching iManager's
`PasswordFieldType`. There is no override; pick a longer one.
The editor's login rate-limiter is the real brute-force defence,
but the floor stops the most obvious typos.
