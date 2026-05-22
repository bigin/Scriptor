# Scriptor 2.0 — demo image

A one-command Docker stack to try Scriptor 2.0 without installing PHP,
Composer, or SQLite locally. Builds two small images
(`php:8.3-fpm-alpine` for the app, `nginx:alpine` for the front) and
restores a snapshot of the canonical Scriptor demo content on first
start.

> The image is meant for **trying the CMS**, not for production. It
> bakes the demo seed into the entrypoint and ships with predictable
> credentials. Real deployments should follow the
> [iManager deployment guide](https://github.com/bigin/imanager/blob/main/docs/deployment.md).

---

## Quickstart

```bash
git clone https://github.com/bigin/Scriptor.git
cd Scriptor
docker compose up -d --build
```

First build takes a minute or two. Docker has to pull
`php:8.3-fpm-alpine` + `nginx:alpine`, and Composer installs
dependencies during the image build. Subsequent `up -d` (without
`--build`) is instant.

Then open:

| URL | What's there |
|---|---|
| **http://localhost:8080/** | The public front. The seeded `scriptor-cms.dev` content rendered through the default theme. |
| **http://localhost:8080/editor/** | The editor. Sign in with the credentials below. |

> **Port already in use?** Override the host port via the
> `SCRIPTOR_DEMO_PORT` env var:
>
> ```bash
> SCRIPTOR_DEMO_PORT=8090 docker compose up -d --build
> ```
>
> The container itself always listens on `80`; only the host-side
> port is dynamic.

### Default credentials

```
username: admin
password: gT5nLazzyBob
```

The credentials are baked into the seed snapshot; change them
immediately if you expose the container beyond your machine.

---

## What the seed restores

The first time the container starts (no SQLite database yet) the
entrypoint loads two seed artefacts shipped with the repo:

- `docker/seed-demo.sql`: sqlite3 dump captured via
  `vendor/bin/imanager dump`. Restores schema + every category, field,
  page, file row, and the FTS5 index for full-text search.
- `docker/seed-demo-uploads.tar.gz`: `public/uploads/` for the page
  images referenced from the dump.

After the restore, `vendor/bin/imanager schema:migrate` runs as a
no-op (or applies any newer migrations the snapshot didn't include).

The seed snapshot mirrors `https://scriptor.cms` content as of the
last `chore(deps): bump bigins/imanager` PR. Restarts re-apply
pending iManager migrations but do **not** re-seed data. The
SQLite DB and uploads live in named volumes that survive
`docker compose down`.

---

## Trying things

### Add a page through the editor

1. Sign in at `/editor/`.
2. **Pages → New**, set a slug (e.g. `about`), give it some content,
   save.
3. Open `http://localhost:8080/about`. Your new page renders through
   the default theme.

### Inspect the live database

```bash
docker compose exec scriptor sqlite3 data/imanager.db \
  "SELECT i.id, i.name, c.slug FROM items i JOIN categories c ON c.id = i.category_id;"
```

You should see all seeded pages, the admin user, and anything
you've created through the editor.

### Run the iManager CLI

```bash
docker compose exec scriptor vendor/bin/imanager schema:status --db=data/imanager.db
docker compose exec scriptor vendor/bin/imanager repair       --db=data/imanager.db
docker compose exec scriptor vendor/bin/imanager fts:rebuild  --db=data/imanager.db
```

The full CLI surface is documented in the
[iManager CLI section](https://github.com/bigin/imanager#cli).

### Reset to factory state

```bash
docker compose down -v
docker compose up -d
```

`down -v` removes the named volumes (`scriptor-data` for the SQLite
DB + cache/logs, `scriptor-uploads` for `public/uploads/`); the next
`up` re-seeds.

---

## What lives where

Post the 2026-05-15 volume split, code lives in the **images**
(rebuilt fresh on every `up -d --build`); only mutable state is in
named volumes:

| Path in container | Source | Purpose |
|---|---|---|
| `/var/www/scriptor/` (everything except below) | image | Application code, vendor/, baked public/ |
| `/var/www/scriptor/data/` | volume `scriptor-data` | SQLite DB (+ WAL), cache, logs, settings |
| `/var/www/scriptor/public/uploads/` | volume `scriptor-uploads` | FileStorage |

This split means `git pull && docker compose up -d --build`
propagates code changes; the DB and uploads survive container
recreation.

The demo image ships **plugin-free** — every `vendor/bigins/*`
package you see in the container came in through the default
`composer.json`'s `require`. To add a plugin to the demo (e.g. to
exercise the *Documentation* editor module from
`bigins/scriptor-markdown-pages`), set a `SCRIPTOR_PLUGINS` build
arg in a compose override and rebuild — see
[`docs/install.md` → Installing plugins](install.md#installing-plugins)
for the recipe. The cms-site (scriptor-cms.dev) is the
production reference for this pattern.

---

## Files in this repo

| File | Role |
|---|---|
| `docker/Dockerfile` | `php:8.3-fpm-alpine` + iManager extensions + opcache + composer install of the runtime deps from Packagist. |
| `docker/Dockerfile.web` | `nginx:alpine` with `public/` baked in so SCRIPT_FILENAME paths resolve identically in both containers. |
| `docker/nginx.conf` | Front-controller routing; serves `/uploads/` directly; blocks dotfiles + any stray non-`index.php` PHP. |
| `docker/entrypoint.sh` | On every start: `schema:migrate` (idempotent). On first start (no DB): run `bin/scriptor install`, overlay the content seed, extract uploads. |
| `docker/seed-demo-content.sql` | Content overlay: the seven extra demo pages (Articles, Contact, Footer Pages, …) that `bin/scriptor install` does not seed itself. IDs start at 3 to leave room for the admin and Home rows the install command creates. |
| `docker/seed-demo-uploads.tar.gz` | `public/uploads/` snapshot for page images referenced by the content overlay. |
| `docker-compose.yml` | Two-service stack on `http://localhost:8080`. |

---

## Refreshing the seed when scriptor.cms content evolves

The content overlay is hand-curated, not auto-generated. `bin/scriptor
install` already handles categories, fields, the admin user, and a
Home placeholder, so the seed file only carries the *extra* demo
pages plus the file metadata for the images they reference.

Workflow when the canonical scriptor.cms content changes and the
demo image should pick it up:

```bash
# 1. From a working copy connected to the canonical scriptor.cms DB,
#    dump the demo page rows. The admin user (Users category) and the
#    install-created Home (id=2) are skipped on purpose.
sqlite3 data/imanager.db <<'SQL' > /tmp/items.sql
.mode insert items
SELECT * FROM items WHERE category_id = 1 AND name != 'Home';
SQL

# 2. Renumber IDs so they start at 3 (the install command takes 1
#    for admin and 2 for Home). Update parent references inside each
#    row's JSON `data` blob the same way. Compare against the current
#    docker/seed-demo-content.sql to see the exact shape, then hand-
#    edit until it matches.

# 3. Same for items_fts and files (item_id references follow items).

# 4. Tar the upload subdirs the overlay references:
COPYFILE_DISABLE=1 tar --format=ustar -czf docker/seed-demo-uploads.tar.gz \
  -C public uploads/<referenced-subdirs>
```

Referenced upload subdirs come from
`SELECT DISTINCT substr(path, 1, instr(path, '/') - 1) FROM files;`
on the canonical DB. Use `ustar` format so alpine busybox tar
doesn't warn about pax extended headers.

If you find yourself doing this often, write a `bin/scriptor
demo:dump` command that does the renumbering programmatically and
commit it instead of refining the workflow above. The doc reflects
the current cadence (a refresh every few quarters), not a long-term
target.

---

## When NOT to use this

- **Production.** The demo image bakes `admin / gT5nLazzyBob` into
  the seed and runs with `opcache.validate_timestamps = 1` (handy
  for exploration, wrong for production throughput). For a real
  deployment, follow the
  [iManager deployment guide](https://github.com/bigin/imanager/blob/main/docs/deployment.md)
  and write your own Dockerfile.
- **Developing on Scriptor itself.** Use the local
  `composer install` + `php bin/scriptor install` + ServBay / nginx
  / Caddy setup the [README](../README.md) and
  [`docs/install.md`](install.md) document; the demo image ships
  frozen code from a `docker build` snapshot.
- **Migrating real 1.x data.** Use
  [`vendor/bin/imanager migrate:from-v1`](https://github.com/bigin/imanager/blob/main/docs/migration-guide.md)
  against your real data dir, not against this demo's seed.
