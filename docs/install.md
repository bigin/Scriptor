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

## Interactive install (local dev)

```bash
git clone git@github.com:bigin/Scriptor.git
cd Scriptor
composer install
php bin/scriptor install
```

The command will:

1. Confirm the database path: `About to seed /path/to/data/imanager.db. Type INSTALL to proceed:`. Type `INSTALL` and press Enter.
2. Prompt for an admin password (12 characters minimum, echo suppressed) and ask you to confirm it.
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

## Security notes

- The install command only runs from the command line. A misconfig
  that exposed `bin/scriptor` over HTTP would still be refused at
  the SAPI check on the first line of the script.
- There is no default admin password. The command rejects passwords
  under 12 characters and a small blocklist of obvious defaults
  (including this project's old Docker demo password).
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

The minimum is 12 characters. There is no override; pick a longer
one. Length is the cheapest defence against brute-force.
