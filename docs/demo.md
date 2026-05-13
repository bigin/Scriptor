# Scriptor 2.0 — demo image

A one-command Docker stack to try Scriptor 2.0 without installing PHP,
Composer, or SQLite locally. Builds a small `php:8.3-fpm-alpine` image
with iManager 2.0, applies the schema migrations on first start, and
seeds a minimal demo dataset (one admin user, one example page).

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

First boot takes a minute or two — Docker has to pull `php:8.3-fpm-alpine`
+ `nginx:alpine` and Composer has to install dependencies inside the
image. Subsequent starts are instant.

Then open:

| URL | What's there |
|---|---|
| **http://localhost:8080/** | The public front. The single seeded page rendered through Scriptor's default theme. |
| **http://localhost:8080/editor/** | The editor. Sign in with the credentials below. |

> **Port already in use?** If something else on the host (a local web
> stack, another container) is already on `8080`, override the host
> port via the `SCRIPTOR_DEMO_PORT` env var:
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
password: scriptor
```

The credentials are baked into the seed and intentionally short — change
them immediately if you expose the container beyond your machine.

---

## What the seed creates

The first time the container starts (no SQLite database yet) the
entrypoint runs `vendor/bin/imanager schema:migrate` and then
`docker/seed-demo.php`, which creates:

- **`Pages` category** + 7 fields: `slug`, `parent`, `pagetype`,
  `menu_title`, `content`, `template`, `images`.
- **`Users` category** + 3 fields: `role`, `email`, `password`.
- One **admin user** (`admin` / `scriptor`).
- One **example page** (`hello-world`) rendered at `/`.

The seed is idempotent — restarting the container re-applies any
pending iManager migrations but does **not** re-seed data.

---

## Trying things

A handful of explorations that show off the moving parts:

### Add a page through the editor

1. Sign in at `/editor/`.
2. **Pages → New**, set a slug (e.g. `about`), give it some content,
   save.
3. Open `http://localhost:8080/about` — your new page renders through
   the default theme.

### Inspect the live database

```bash
docker compose exec scriptor sqlite3 data/imanager.db \
  "SELECT i.id, i.name, c.slug FROM items i JOIN categories c ON c.id = i.category_id;"
```

You should see your seeded page, the admin user, and anything you've
created through the editor.

### Run the iManager CLI

```bash
docker compose exec scriptor vendor/bin/imanager schema:status --db=data/imanager.db
docker compose exec scriptor vendor/bin/imanager repair       --db=data/imanager.db
docker compose exec scriptor vendor/bin/imanager fts:rebuild  --db=data/imanager.db
```

The full CLI surface is documented in the
[iManager CLI section](https://github.com/bigin/imanager/tree/main/docs/api).

### Reset to factory state

```bash
docker compose down -v
docker compose up -d
```

`down -v` removes the named volume, which is where the SQLite DB and
the uploads live — the next `up` re-seeds.

---

## What lives where

| In the container | Purpose |
|---|---|
| `/var/www/scriptor/` | Application code + `vendor/`. |
| `/var/www/scriptor/data/imanager.db` | SQLite DB (+ WAL sidecars). |
| `/var/www/scriptor/data/uploads-2.0/` | Item file attachments. |
| `/var/www/scriptor/data/cache/sections/` | PSR-16 fragment cache. |

The whole `/var/www/scriptor/` directory is on the named volume
`scriptor-app`. Both services (php-fpm and nginx) mount it — nginx
read-only.

---

## Files in this repo

| File | Role |
|---|---|
| `docker/Dockerfile` | `php:8.3-fpm-alpine` + iManager extensions + production-shaped opcache + composer install with VCS-overridden iManager dep. |
| `docker/nginx.conf` | Front-controller routing; serves `/data/uploads-2.0/` directly; blocks the rest of `data/` and any stray `imanager/`. |
| `docker/entrypoint.sh` | Applies schema migrations on every start; seeds on first start (no DB present). |
| `docker/seed-demo.php` | Idempotent seed (Pages + Users categories, admin user, example page). |
| `docker-compose.yml` | Two-service stack on `http://localhost:8080`. |

---

## When NOT to use this

- **Production.** The demo image bakes `admin / scriptor` into the
  seed and runs with `opcache.validate_timestamps = 1` (handy for
  exploration, wrong for production throughput). For a real
  deployment, follow the
  [iManager deployment guide](https://github.com/bigin/imanager/blob/main/docs/deployment.md)
  and write your own Dockerfile.
- **Developing on Scriptor itself.** Use the local
  `composer install` + ServBay / nginx / Caddy setup the
  [README](../README.md) documents — the demo image ships frozen
  code from a `docker build` snapshot.
- **Migrating real 1.x data.** Use
  [`vendor/bin/imanager migrate:from-v1`](https://github.com/bigin/imanager/blob/main/docs/migration-guide.md)
  against your real data dir, not against this demo's seed.
