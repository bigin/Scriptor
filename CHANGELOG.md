# Changelog

## 2.0.0 (2026-05-16) — Ground-up rewrite on iManager 2.0

Scriptor 2.0 replaces the embedded 1.x `imanager/` library with the
external [iManager 2.0][imanager] package and rebuilds Frontend +
Editor + Upload pipeline on PSR-standards (PSR-3 logging, PSR-14 event
dispatch, PSR-16 caching). The legacy 1.x flat-file storage is gone.

### Added

- **SQLite storage** with JSON columns and FTS5 full-text search.
- **Composer-based** install (`bigins/imanager:^2.0` is the only direct
  runtime dep besides Symfony Console for the CLI).
- **Domain-event listeners** (`Scriptor\Boot\Events\*`):
  - `ItemFileCleanupListener` — drops uploaded files (and thumbnails)
    from disk when an item is deleted, before the FK cascade clears
    the metadata rows.
  - `PageCacheInvalidationListener` — flushes the rendered-page cache
    on every Pages-category mutation.
- **FilePond** uploads with on-demand thumbnail generation through
  `intervention/image`. Endpoint: `/editor/api/upload` (POST/PATCH/DELETE).
- **Per-image titles** as a typed `files.title` column (iManager 2.0
  schema migration `0004`). Captions render through `Sanitizer::markdown`
  on the frontend so links and emphasis work in 2.0 the same way they
  did in 1.x.
- **Single-entry routing** — root `index.php` delegates `/<admin_path>/*`
  to `editor/index.php` at the PHP level, which works on Apache, Caddy,
  Nginx, and PHP's built-in server without per-server rewrite rules.
- **`bin/perf-smoke.php`** runs Plan §8.2 timing checkpoints against
  the live SQLite database.
- **`Scriptor\Boot\Editor\*`** rewrites of all admin modules (auth,
  pages, profile, settings, install) on the iManager 2.0 stack.
- **`Scriptor\Boot\Frontend\*`** — `Site`, `Page`, `PageRepository`,
  `Sanitizer`, `ImageUrlBuilder` — public-site renderer that bundled
  themes consume through the standard `$site` surface.
- **Asset-URL helpers**: `Site::themeAssetUrl()`,
  `Site::editorAssetUrl()`, `Editor::assetUrl()` — used by every
  bundled template after the public/-webroot split. Custom themes
  built against 2.0 should call these instead of hard-coding paths;
  see [`docs/themes.md`](docs/themes.md).

### Fixed

- **Pages tree: indirect-cycle guard on parent save.**
  `PageRepository::wouldCreateCycle()` walks the proposed parent's
  existing chain upwards; if it reaches the page being edited,
  `PagesModule::saveAction()` refuses the save with a localised
  error (`error_page_parent_cycle`, en + de). The direct
  self-parent case was already collapsed to root, but indirect
  cycles (a → b → c → a) could be saved silently. The frontend's
  `Site::buildPageUrl()` already tolerates cyclic data via a
  visited-set guard, so the editor now matches the frontend's
  defensive shape.
- **Trust `X-Forwarded-Proto` for site URL scheme.** Behind a
  TLS-terminating reverse proxy (nginx-proxy on the Hetzner demo,
  Caddy as a forwarding proxy), `Frontend\Site::detectSiteUrl()` and
  `Editor\Editor::detectSiteUrl()` no longer hard-code `http` from
  `$_SERVER['HTTPS']` — they read `X-Forwarded-Proto` first. Fixes
  mixed-content errors when the site is reverse-proxied.
- **Demo seed restore recreates the FTS5 index.** The first-boot
  entrypoint re-applies the FTS schema and runs `imanager fts:rebuild`
  after dropping in the SQL dump, so editor password saves (which
  trigger an FTS update) don't crash with "no such table: items_fts"
  against snapshots captured by iManager <2.0.1.

### Changed (BREAKING — webroot reorganisation)

- **`public/` is now THE webroot.** Source code, the SQLite DB, configs,
  composer artifacts, the editor's PHP source, theme PHP source and CLI
  scripts all live OUTSIDE the webroot now. Defense-in-depth is no longer
  load-bearing: a misconfigured Caddy/nginx that lacks deny rules can no
  longer expose `data/imanager.db`, `.git/`, `vendor/`, `boot/`, or
  `bin/` simply because those paths are not below the document root.
  Server admins point `root` at `<install>/public/` and that's it.
- **Themes split into two halves.**
  `themes/<name>/` (PHP source, includes only) +
  `public/themes/<name>/` (static assets — css, fonts, images, scripts).
  The `site/` wrapper directory is gone; `site/themes/` becomes
  `themes/`, `site/modules/` becomes `modules/`.
- **Editor static assets move to `public/editor-assets/`.** The editor
  theme's PHP files (`template.php`, `header.php`, `summary.php`) stay in
  `editor/theme/` — included only by `editor/index.php`, never web-served.
  `editor/lang/` stays in `editor/`.
- **User uploads move to `public/uploads/`** (was `data/uploads-2.0/`).
  Inside the webroot, served by the web server directly, no alias rules
  needed. The image rendering layer (`Frontend\ImageUrlBuilder`) is
  backward-compatible with both the old `data/uploads/` (1.x-migrated)
  and `data/uploads-2.0/` (2.0 pre-public-webroot) prefixes — existing
  items in the live DB keep rendering without a path migration.
- **`public/.htaccess`** replaces the root `.htaccess`. The deny list
  collapses to dotfiles only, because the source/data directories no
  longer live inside the webroot. The `editor/`-specific rewrite is
  gone too — every request goes through `public/index.php`, which
  handles admin-path delegation in PHP.

See [`docs/refactor-public-webroot.md`](docs/refactor-public-webroot.md)
for the full migration plan including server-config templates for
Apache, Caddy, nginx and PHP's built-in server, and the
allow/deny test matrix.

### Changed

- **Composer dep on iManager: Packagist `^2.0` (locked to 2.0.1).**
  With `bigins/imanager` published on Packagist, `composer.json` no
  longer declares the `../imanager` path repository or the
  `minimum-stability: dev` / `prefer-stable: true` pair. The lock
  pins **2.0.1**, which fixes a `dump`-skips-FTS5 bug that broke
  seed-restore round-trips. `composer install` now works against a
  fresh clone without any sibling iManager checkout.
- **Demo image: drop the composer-rewrite workaround.** With
  `bigins/imanager` resolvable from Packagist directly,
  `docker/Dockerfile` no longer needs the `composer-rewrite.php`
  helper (which patched the `repositories` array at build time to
  side-step the missing path-repo). The Dockerfile now runs a
  plain `composer install --no-dev`. `docker/composer-rewrite.php`
  is removed.
- **`.htaccess` refreshed for the 2.0 layout.** The Apache fallback
  rules now match what actually lives in the tree: directory deny
  list switched from the gone `imanager`/`modules`/`core` to the
  current `boot`/`vendor`/`bin` (real source dirs); legacy
  `imanager/upload/server/php` exception removed; the literal
  `editor/`-rewrite is gone — every request lands on `index.php`
  which delegates `/<admin_path>/*` in PHP, so changing
  `admin_path` no longer requires editing `.htaccess`. The static
  asset whitelist gained `woff/woff2/ttf/eot` for theme fonts.
  Caddy and nginx ignore `.htaccess` entirely; this is purely an
  Apache-fallback hygiene pass.
- **`scriptor-config.php` admin_path comment** no longer claims
  the user must update `.htaccess` after changing `admin_path`.
  The 2.0 PHP-level delegation in `index.php` makes that obsolete.
- **Domain rebrand: `scriptor-cms.info` → `scriptor-cms.dev`.** All
  in-repo references updated; new banner at
  `docs/images/scriptor-banner-2.0.png`.
- **Demo image rewired** for the public/-webroot layout:
  `nginx.conf` `root` is `/var/www/scriptor/public`, the `/data/`
  alias + deny rules are dropped (`data/` is outside the webroot
  now), `/uploads/` location added with long-cache headers.
- **Demo image: two-image stack.** A tiny `nginx:alpine` web
  image (`docker/Dockerfile.web`) bakes `public/` so
  `SCRIPT_FILENAME` paths resolve identically in both containers
  and the front isn't shadowed by a single shared volume.
- **Demo seed mirrors `https://scriptor.cms` content** instead of
  the previous synthetic programmatic seed. Snapshot captured via
  `imanager dump`. Ships with `admin / gT5nLazzyBob` — change
  before exposing.
- **Docker volume layout split**: the pre-refactor single
  `scriptor-app` volume mounted code AND state, which silently
  shadowed image updates — `git pull && up -d --build` didn't
  propagate code changes without `down -v` (which wiped state).
  Now `scriptor-data` (DB, cache, logs, settings) and
  `scriptor-uploads` (FileStorage) hold state; code lives in the
  images, rebuilt on every `--build`.

### Security

- **Hardened response headers** on every response: tight
  `Content-Security-Policy` (no `script-src 'unsafe-inline'`,
  allow-lists `cdn.jsdelivr.net` for the basic theme's UIkit),
  `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`,
  `Referrer-Policy: strict-origin-when-cross-origin`,
  `Permissions-Policy` opting out of
  geolocation/camera/microphone/payment/FLoC. `server_tokens off`
  + `expose_php=Off` — the nginx version no longer leaks and
  `X-Powered-By` is gone.
- **Editor `IMSESSID` session cookie** ships with `Secure`
  (X-Forwarded-Proto-aware, so local HTTP dev still works),
  `HttpOnly`, `SameSite=Lax`.

### Removed

- `Scriptor/imanager/` — the entire embedded 1.x library (~850 KB).
- `data/datasets/buffers/` — flat-file storage; superseded by SQLite.
- `editor/core/` — legacy `Scriptor\Core\Scriptor`, `Module`, `Site`,
  `Pages`, `User`, `Editor`, `CSRF`, `Helper`. All replaced by
  `Scriptor\Boot\*` equivalents.
- `editor/modules/` — every legacy admin module file. Replacements live
  under `boot/Editor/Auth/`, `boot/Editor/Pages/`, `boot/Editor/Profile/`,
  `boot/Editor/Settings/`, `boot/Editor/Install/`.
- `imanager.php` (root file) — old `imanager()`-bootstrap stub.
- The legacy `Scriptor::execHook()` system. Domain events on the
  iManager side replace it; if a 3rd-party module needs the old hook
  shape we can ship a Hook-Bridge listener provider in a follow-up.

### Migration from 1.x

`vendor/bin/imanager migrate:from-v1 --source data --target data/imanager.db`
performs a one-shot import. See [README.md](README.md#migrating-from-1x).
The command takes a `--dry-run` flag for previewing the import.

### Performance

Plan §8.2 budgets and typical results on the bundled demo data:

| Operation                          | Result    | Budget    | Headroom |
|------------------------------------|-----------|-----------|----------|
| `items()->find($id)`               | 0.009 ms  | 1.0 ms    | 110×     |
| `findByCategory(pages, 0, 20)`     | 0.025 ms  | 50.0 ms   | 2 000×   |
| `FullTextSearch::search`           | 0.037 ms  | 100.0 ms  | 2 700×   |

[imanager]: https://github.com/bigin/imanager

---

## 1.x

Pre-2.0 history lives on the `1.x-final` tag (created at the
imanager-2.0 cutover). The 1.x branch was a flat-file CMS with an
embedded `imanager/` library shipping its own `Imanager\ItemManager`,
`FieldFileupload`, `TemplateParser`, etc. See the git log on
`1.x-final` for the per-version detail.
