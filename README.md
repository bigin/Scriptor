
![Scriptor Header](https://scriptor-cms.info/site/themes/info/images/scriptor-header.png)


# Scriptor 2.0

Scriptor is a lightweight and versatile CMS for creating microsites, blogs, or
wikis. Version 2.0 is a ground-up rewrite on top of [iManager 2.0][imanager],
SQLite and PSR-standards (PSR-3, -14, -16).

Demo: [https://demos.scriptor-cms.info](https://demos.scriptor-cms.info)

## What's new in 2.0

- **SQLite storage** with JSON columns and FTS5 full-text search
  (was: per-item flat files in `data/datasets/buffers/`).
- **Composer-based** install on top of `bigins/imanager:^2.0`
  (was: bundled `Scriptor/imanager/` library).
- **PSR-14 domain events** drive cache invalidation and file cleanup
  (was: hard-coded calls to `imanager()->sectionCache->expire()`).
- **FilePond** uploads with on-demand thumbnail generation through
  `intervention/image` (was: jQuery-fileupload + 1.x `FieldFileupload`).
- **Per-image titles** as a typed `files.title` column with markdown
  caption rendering on the frontend.
- **Single-entry routing** (`index.php` delegates `/<admin_path>/*` to
  `editor/index.php` in PHP) — works on Apache, Caddy, Nginx, php-S
  without per-server rewrite rules.

## Requirements

- PHP 8.2+ (8.3 recommended)
- Composer 2
- SQLite 3.38+ (for `json_extract`, FTS5)
- Standard PHP extensions: `mbstring`, `dom`, `json`, `gd`, `pdo_sqlite`
- A web server that routes unknown paths to `index.php` — Apache (`.htaccess`
  is shipped), Caddy, Nginx, or PHP's built-in server.

## Installation

```bash
git clone git@github.com:bigin/Scriptor.git
cd Scriptor
composer install
```

The installer creates `data/imanager.db` on the first request via the
schema-migrate step. To run migrations explicitly:

```bash
vendor/bin/imanager schema:migrate --db=data/imanager.db
```

### Try it in Docker

A bundled demo stack starts Scriptor 2.0 on `http://localhost:8080`
with one admin user (`admin / scriptor`) and one example page:

```bash
docker compose up -d --build
```

See [`docs/demo.md`](docs/demo.md) for what the seed creates, how to
reset to factory state, and what the image is (and isn't) good for.

## Admin panel

```
https://your-website.com/editor/
```

Default credentials (change them on first login):

> User: `admin`  
> Password: `gT5nLazzyBob`

## Performance

`bin/perf-smoke.php` runs four canonical timing checkpoints against the
live SQLite database. Plan §8.2 budget is `getItem < 1 ms`,
`getItems(20) < 50 ms`, FTS search `< 100 ms`. Typical results on the
bundled demo data:

```
items()->find(1)                       0.009 ms  [budget   1.0 ms] OK
findByCategory(pages, 0, 20)           0.025 ms  [budget  50.0 ms] OK
FullTextSearch::search("scriptor")     0.037 ms  [budget 100.0 ms] OK
FullTextSearch::count("scriptor")      0.009 ms  [budget 100.0 ms] OK
```

Run it yourself: `php bin/perf-smoke.php`.

## Migrating from 1.x

The iManager 2.0 CLI ships a one-shot migrator that reads the legacy
`data/datasets/buffers/` files into the new SQLite schema and copies
uploads to the post-migration layout:

```bash
# Backup first
cp -a data data.bak.$(date +%F)

# Dry-run
vendor/bin/imanager migrate:from-v1 \
  --source data \
  --target /tmp/imanager-test.db \
  --dry-run

# Real migration
vendor/bin/imanager migrate:from-v1 \
  --source data \
  --target data/imanager.db
```

After the migration finishes you can delete the `data/datasets/buffers/`
directory. The original 1.x uploads stay in `data/uploads/` (untouched);
the migrator copies them into `data/uploads-2.0/` for the 2.0 file
storage. `data/uploads/` is safe to remove once you've verified the
migration on the live site.

## Project layout

```
boot/                        PSR-4 (Scriptor\Boot\) — Frontend, Editor, Events
  Frontend/Site, Page, …     public site renderer + repos
  Editor/Editor, Router, …   admin shell + per-module wiring
  Events/                    domain-event listeners (cache, file cleanup)
  ImanagerBootstrap.php      DI container + service graph
editor/                      admin theme (templates, scripts, styles)
site/themes/<theme>/         public-site themes (basic ships in-tree)
data/
  settings/                  scriptor-config.php, custom.scriptor-config.php
  imanager.db                SQLite database
  uploads-2.0/               file storage root for FilePond uploads
  cache/sections/            FilesystemCache (page-level HTML)
bin/                         CLI helpers (currently: perf-smoke.php)
```

## Use Scriptor as a library

```php
<?php

use Scriptor\Boot\App;
use Scriptor\Boot\ImanagerBootstrap;
use Imanager\Storage\ItemRepository;

require __DIR__ . '/vendor/autoload.php';

App::set(ImanagerBootstrap::create(__DIR__));

$item = App::container()->get(ItemRepository::class)->find(1);
```

See `boot/Frontend/Site.php` for the full set of services the bundled
themes consume.

## Links

- iManager 2.0 framework: <https://github.com/bigin/imanager>
- Phase 14 migration plan: `docs/imanager-2.0-phase-14-plan.md`
  (in the [iManager repo][imanager])
- Documentation: <https://scriptor-cms.info/documentation/>
- Modules / extensions: <https://scriptor-cms.info/extensions/extensions-modules/>
- Demo: <https://demos.scriptor-cms.info>

[imanager]: https://github.com/bigin/imanager

### Header image by

[Freepik](https://www.freepik.com/free-vector/flat-cms-content-landing-page-style_11817459.htm)

### License

The [MIT License (MIT)](https://github.com/bigin/Scriptor/blob/master/LICENSE)
