# Deploying a site as a Scriptor fork

Scriptor is a **`type: project` application**, not a Composer library — you
can't `composer require` it into your own project. The clean way to build and
maintain your own site on top of Scriptor is therefore to **fork Scriptor** and
layer your site on as *additive* files. Upstream updates come in via a normal
`git merge`.

This is the same upstream/downstream pattern behind GitHub forks, starter
templates and framework skeletons — it just fits Scriptor especially well,
because the application root *is* the deployable unit.

## The two remotes

Your site repository tracks two remotes:

```
origin     git@github.com:you/your-site.git     # your site (push here)
upstream   git@github.com:bigin/Scriptor.git     # the engine (pull updates here)
```

Set it up once by forking/cloning Scriptor and re-pointing the remotes:

```bash
git clone git@github.com:bigin/Scriptor.git your-site
cd your-site
git remote rename origin upstream                 # Scriptor = upstream
git remote add origin git@github.com:you/your-site.git
git push -u origin master                          # seed your repo
```

## The golden rule: add new files, never edit Scriptor's

Everything that makes *your* site is added as **new files** that Scriptor does
not ship. You never edit a Scriptor-owned file. That single discipline is what
keeps upstream merges conflict-free.

| Your site (new files) | Scriptor's (leave untouched) |
|---|---|
| `themes/your-theme/` | `themes/basic/` |
| `public/themes/your-theme/` | `boot/`, `editor/`, `public/index.php` |
| `data/settings/custom.scriptor-config.php` | `data/settings/scriptor-config.php` (generated) |
| `docker/seed-demo-full.sql` (your seed) | `docker/Dockerfile`, `docker/entrypoint.sh`, `docker/nginx.conf` |
| `docker-compose.override.yml` | `docker-compose.yml` |
| `docker-compose.prod.yml` | `composer.json`, `README.md` |

> **Don't edit a Scriptor-owned file.** The moment you do, a future
> `git merge upstream` can conflict on exactly that file. Need a build tweak?
> Put it in your own `docker-compose.override.yml`, not in Scriptor's
> `docker-compose.yml`. Need config? Use `custom.scriptor-config.php`, which
> Scriptor loads *on top of* the generated `scriptor-config.php`
> (`array_replace_recursive`), not the generated file itself.

## How a 3-way merge treats your files

`git merge upstream/<ref>` compares three states per file — the common
ancestor, upstream's version, and yours:

- **Only upstream changed** (you never touched it: `boot/`, `docker/Dockerfile`,
  …) → updated to the new Scriptor version. This is the point.
- **Only you added it** (`themes/your-theme/`, `docker-compose.override.yml`,
  …) → left exactly as-is.
- **Both changed the same file** → a conflict. Happens only if you broke the
  golden rule.

So your site files are never overwritten; only Scriptor's own files pull the
updates forward.

## Updating Scriptor

```bash
git fetch upstream
git merge upstream/v2.2.0        # prefer a tag over bleeding `master`
# rebuild + smoke locally, then:
git push origin master
```

Pinning to a **tag** (`git tag -l 'v*'` lists them) keeps updates reproducible
and on your schedule. Database schema migrations ship inside Scriptor and are
applied automatically — the Docker entrypoint runs `imanager schema:migrate` on
every start, so a carried-over database catches up by itself.

## Wiring your site through Docker

Scriptor's bundled `docker-compose.yml` builds the `scriptor` (php-fpm) and
`web` (nginx) services. Compose **automatically** loads a sibling
`docker-compose.override.yml`, which is where your site customisation lives —
no Scriptor file is edited:

```yaml
# docker-compose.override.yml (yours)
services:
  scriptor:
    image: your-site:latest            # own image name, no clash with demo tags
    build:
      args:
        # Plugins are installed at build time by Scriptor's Dockerfile.
        SCRIPTOR_PLUGIN_REPOS: "https://github.com/you/your-plugin"
        SCRIPTOR_PLUGINS:      "you/your-plugin:^1.0"
    volumes:
      # data/ is a named volume that SHADOWS anything baked under it, so the
      # config overlay has to be mounted in over the volume layer:
      - ./data/settings/custom.scriptor-config.php:/var/www/scriptor/data/settings/custom.scriptor-config.php:ro
  web:
    image: your-site-web:latest
```

Theme, assets and seed need **no** mounts — they live natively in the tree and
the Dockerfile's `COPY . .` bakes them into the image. Only files that land
under the `data/` named volume (the config overlay) need mounting, because the
volume hides image content at that path.

Add a second `docker-compose.prod.yml` for production specifics (reverse-proxy
hostname, TLS, dropping the host port) and chain it explicitly there:

```bash
# local
docker compose up -d --build
# production
docker compose -f docker-compose.yml -f docker-compose.override.yml \
  -f docker-compose.prod.yml --env-file .env up -d --build
```

## Plugins

Plugins are pulled at **build time** through Scriptor's Dockerfile build args —
no edit to Scriptor's files:

- `SCRIPTOR_PLUGIN_REPOS` — space-separated VCS URLs for packages not on
  Packagist (each registered with `composer config repositories`, `no-api`).
- `SCRIPTOR_PLUGINS` — space-separated `composer require` specs.

Set them in your `docker-compose.override.yml` (see above). Match plugin
versions to your pinned Scriptor version — a plugin built against a newer
Scriptor API can fail to register on an older engine.

## Deploying / updating on a server

```bash
git pull origin master
docker compose -f docker-compose.yml -f docker-compose.override.yml \
  -f docker-compose.prod.yml --env-file .env up -d --build
```

The database and uploads live in named volumes
(`<project>_scriptor-data` / `<project>_scriptor-uploads`) and survive the
rebuild; only the code in the images is replaced. Back the volumes up before a
risky change:

```bash
docker run --rm -v <project>_scriptor-data:/v -v "$PWD":/b alpine \
  tar czf /b/scriptor-data-backup.tgz -C /v .
```

## See also

- [`docs/install.md`](install.md) — the non-fork install paths.
- [`docs/themes.md`](themes.md) — building the theme your fork ships.
- [`docs/plugin-lifecycle.md`](plugin-lifecycle.md) — installing/removing plugins.
