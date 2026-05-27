# Changelog

## Unreleased

### Added

- **Editor page-form extension events**. Two PSR-14 events let plugins
  add fields to the existing PagesModule edit form without forking
  PagesModule. `Scriptor\Boot\Events\Editor\PageFormRendering` fires
  after the core fields and before the hidden action/csrf inputs;
  listeners call `appendHtml(...)` to inject their own
  `<div class="form-control">…</div>` markup. The companion
  `Scriptor\Boot\Events\Editor\PageSaving` fires after the
  about-to-be-saved `Item` is constructed and before persistence;
  listeners call `mergeData([...])` to add extra keys from the POST
  request to the page's `data` bag. Both keys merge cleanly so a
  single `pages->save(...)` runs with the combined payload. No
  behaviour change when no plugin subscribes.
- **Docker entrypoint full-dump seed mode**. When the operator bind-mounts
  a SQL dump at `/var/www/scriptor/docker/seed-demo-full.sql`, the
  entrypoint skips `bin/scriptor install` + `seed-demo-content.sql`
  overlay and applies the dump directly via `sqlite3`. The dump is then
  authoritative for the whole DB (categories, fields, items, users).
  File-presence is the switch — no env var, no extra config. Sites that
  ship a captured `imanager dump` of their production content (e.g.
  scriptor-cms-site's `seed-site.sql`) can use this path without forcing
  the dump to be rewritten as an overlay on top of the install seed.
- **`bin/scriptor install` CLI** for greenfield setup. Seeds the
  Pages + Users categories, their fields, an admin user, and a
  minimal Home page so `/` works on the first request. Replaces
  the previous "creates the DB on the first request" behaviour
  that only ran schema migrations and left the editor unreachable.
  Password is read from `--password`, `SCRIPTOR_ADMIN_PASSWORD`
  env, or an interactive TTY prompt (8-char minimum to match
  iManager's `PasswordFieldType`, small blocklist of obvious
  defaults). Refuses to run when a Pages
  category already exists, refuses to run under any SAPI but
  `cli`. Documented in [`docs/install.md`](docs/install.md).
- **`bin/smoke-install.sh`** regression script (11 end-to-end
  checks) covering the install CLI's contracts.

### Changed

- **Docker demo entrypoint** seeds through the install CLI rather
  than a single all-in-one `seed-demo.sql` dump. The remaining
  content rows (Articles, Contact, Footer cluster) moved to
  `docker/seed-demo-content.sql` as a small content overlay.
  Admin password reads from `SCRIPTOR_ADMIN_PASSWORD` (default
  `gT5nLazzyBob`, override via host env for a private demo).
- **Build-a-Theme tutorial + shared-hosting guide** both call out
  the new install step instead of stopping at `composer install`.
- **Plugins are now opt-in.** A fresh Scriptor install ships with
  zero plugins in its `vendor/`; sites that need a plugin pull it
  in via their own `composer require` (or via the new
  `SCRIPTOR_PLUGINS` build-arg on `docker/Dockerfile`). The
  `repositories` block in `composer.json` keeps the VCS source
  for `bigins/*` plugins discoverable so the require works
  without further configuration.

### Fixed

- **Editor + Basic-theme Prism code highlighting: PHP blocks with
  `<?xml ?>` literals now highlight correctly, and four new
  languages.** `public/editor-assets/scripts/prism.js` was a 2018
  Prism 1.15.0 build with five languages (markup, css, clike,
  javascript, php). Two problems:

  1. Code blocks fenced ` ```bash `, ` ```json `, ` ```yaml `, or
     ` ```markdown ` had no tokenizer in the bundle and rendered
     as plain text.
  2. PHP blocks containing literal strings like `'<?xml
     version="1.0"?>'` (e.g. sitemap or RSS generators in user
     content) rendered entirely flat. PrismJS#1400, #2174: the PHP
     component's `before-tokenize` hook treats *any* `<?` in the
     source as a PHP open-tag, switching the rest of the block
     into markup-templating mode. The XML processing instruction
     inside a PHP string trips this; the real PHP code then gets
     markup tokenization (no matches) and the xml literal gets
     PHP tokenization.

  Bundle rebuilt from Prism 1.29.0 components: core, markup, css,
  clike, javascript, markup-templating, php, bash, json, yaml,
  markdown. ~38 KB. A one-line local patch narrows the PHP
  before-tokenize trigger from `/<\?/` to `/<\?(?:php|=)/` so
  only real PHP open-tags activate the markup swap. The patch is
  documented in the bundle header comment for future refresh
  cycles (upstream isn't going to merge the narrower trigger —
  they keep the broad form to support PHP files that start with
  HTML before the first `<?php`).

  The Basic theme inherits the fix automatically: its
  `_sidebar-right.php` / `_head.php` / page templates load the
  highlighter via `$site->editorAssetUrl('scripts/prism.js')`,
  so the editor-assets copy is the single source of truth for
  every bundled site.

- **Re-uploaded images no longer stick in the browser cache.**
  `docker/nginx.conf` shipped `/uploads/` with `Cache-Control:
  public, immutable` (plus 30-day `expires`). Upload URLs are
  *not* content-addressed; re-uploading a file under the same
  name leaves the URL stable while the bytes change, and
  `immutable` told browsers to skip revalidation on reload. The
  directive is now `must-revalidate`, so nginx's existing
  `ETag` + `Last-Modified` headers do their job: 304 on hit when
  the file on disk is unchanged (cheap), 200 with the new
  content when it changed. Misleading "content-addressed" code
  comment fixed alongside.
- **`/uploads/` no longer emits a duplicate `Cache-Control`
  header.** Follow-up to the above: the previous version used
  nginx's `expires 30d;` directive next to the explicit
  `add_header Cache-Control "public, must-revalidate"`. The two
  don't merge; nginx ended up emitting two separate
  `Cache-Control` lines on every `/uploads/` response
  (`max-age=2592000` from `expires`, then `public, must-revalidate`
  from the `add_header`). Functionally correct per RFC 7234
  (browsers combine combinable directives across header lines)
  but ugly to debug. The `max-age=2592000` is now folded into the
  single `add_header` line, `expires` is dropped, and the surrounding
  comment explains why.

### Removed

- `docker/seed-demo.sql`. The schema half is recreated by
  iManager's auto-migrate; the data half lives in the install CLI
  (categories, fields, admin, Home) and the content overlay
  (`docker/seed-demo-content.sql`) for the demo's extra pages.
- `bigins/scriptor-markdown-pages` from the default `require`. It
  was a transitive dependency of the cms-site (scriptor-cms.dev),
  not something every Scriptor install needs. The plugin pulled in
  league/commonmark + symfony/yaml (~30 transitive packages) and
  contributed a "Documentation" editor module plus five hardcoded
  top-nav entries (`user-guide`, `developer-guide`, `api`,
  `extensions`, `news`) into every install — which was a poor
  default for portfolios, blogs, or anything that wasn't the CMS
  doc site. Now sites opt in explicitly. The cms-site picks the
  plugin back up via its own compose override.

### Changed

- **Empty slug is now the home-page convention.** Previously the
  home page was identified by `id = 1`, with a fallback to the
  lowest-position page when id 1 was missing. That was a
  proxy for "which page lives at /" and it broke once
  `bin/scriptor install` started seeding the admin user at
  AUTOINCREMENT id 1 (Home then landed at id 2). It also forced
  the resolver to special-case the home page so it was reachable
  via both `/` and its slug, producing duplicate content for SEO.

  New rule: the page with the empty slug **is** the site root.
  - `PageRepository::findHome()` returns `findBySlug('')`. Returns
    `null` when no page has an empty slug — `/` then 404s, which
    is the right answer for an API-only or docs-only install.
  - `Site::getPageUrl()` emits no URL segment for an empty-slug
    page; the canonical URL collapses to `/`.
  - The page-tree resolver no longer special-cases `id === 1`.
    `/<old-home-slug>/` (e.g. `/home/`) now 404s on a fresh
    install because no page owns that slug anymore. External
    links to the old slug need to either pick a new slug for the
    home page in the editor (its URL becomes `/<new-slug>/` and
    the empty-slug page goes elsewhere) or accept the 404 and
    redirect at the web-server layer.
  - `bin/scriptor install` seeds the Home page with `slug = ''`.
    The page's display name is still "Home"; only the slug
    changes.
  - The Pages editor module enforces uniqueness: at most one
    page may have the empty slug. A second empty-slug save
    surfaces the new `error_empty_slug_taken` i18n message
    (`en_US` + `de_DE`).
  - **Convenience trade-off:** the editor's "save with empty
    slug field, derive from page name" autofill is gone. An
    empty slug input now literally means "make this the site
    root". The slug-field info-text spells this out so new users
    discover the convention. Pages with non-empty names that
    leave the slug blank no longer auto-get a slug — type the
    slug you want, or leave empty and accept site-root semantics
    (with the uniqueness check above guarding misuse).

### Added

- **`Site::redirect()` + `Editor::redirect()` helpers.** Themes
  and editor modules can now call
  `$site->redirect('/contact/', 303)` (or `$editor->redirect(...)`)
  to send a Location header and stop the request. Default status
  is 302; pass 303 for the POST-redirect-GET pattern alongside
  `flashMsg()`, 301 for permanent rewrites, 307 to preserve the
  request method. Cleanup deduplicates four
  `private function redirect()` copies that lived in EditorRouter,
  AuthModule, ProfileModule, and PagesModule — all now delegate
  to the single `Editor::redirect()` implementation.
- **PSR-3 logger surface.** Until now Scriptor had no first-class
  logger; tutorial and bundled handlers fell back to `error_log()`,
  which is hard to scope and hard to find. Adds
  `Psr\Log\LoggerInterface` as a container-bound service and a
  small `Scriptor\Boot\Logging\FileLogger` default (~80 lines, no
  new Composer deps — `psr/log:^3` is already transitive). Writes
  one line per record to `data/logs/scriptor.log` (path +
  min-level configurable via `$config['logging']`), with PSR-3
  `{placeholder}` interpolation and `LOCK_EX` for concurrent FPM
  workers. `Site::$logger` and `Editor::$logger` expose the
  instance; themes and plugins can pull `LoggerInterface` directly
  from the container. Swap the binding in `boot.php` to wire in
  Monolog or any other PSR-3 implementation without touching call
  sites.
- **Basic theme: styles for the `messages` slot.** The bundled
  `basic` theme's `styles.css` had no rules for the
  `<ul class="messages"><li class="msg msg-{type}">…</li></ul>`
  markup that `Site::renderMsgs()` emits, so flash messages
  rendered as an unstyled bullet list. Adds a six-token status
  palette (`--color-success-*`, `--color-danger-*`) on
  `:root` plus matching `ul.messages` / `.messages .msg` /
  `.msg-success` / `.msg-error` rules. Themes that want a
  different look override the three tokens per state.
- **`$site->flashMsg($type, $text, $header = '')`** for POST-redirect-GET
  on the frontend. Mirrors `Editor::flashMsg()`: writes the message
  into the session bag, the next request's `renderMsgs()` folds it
  into the in-request queue and renders it. Lets a theme respond to
  a POST with `flashMsg('success', '...')` + `header('Location: ...',
  true, 303)` instead of having to rerender on the same request.
  Session opens lazily (only when `flashMsg()` is called or a session
  cookie is already in the request) so anonymous visitors stay
  cookie-free. Cookie uses the same `IMSESSID` name + flags as the
  editor (HttpOnly, Secure when behind TLS, SameSite=Lax); the flash
  bag lives at a distinct `frontend_msgs` session key so editor and
  frontend flashes don't drain each other.

### Fixed

- **`$site->render('messages')` actually renders queued messages.**
  The frontend `Site` had two properties, `array $msgs` (filled by
  `addMsg()`) and `string $messages` (never assigned anywhere); the
  `messages` render hook read the empty string. Themes that ran
  `addMsg('success', '...')` after a successful POST got nothing on
  screen. New `Site::renderMsgs()` mirrors the editor's helper
  (`<ul class="messages"><li class="msg msg-{type}">...</li></ul>`),
  drains `$msgs[]` on render, and `render('messages')` dispatches
  through it. The dead `public string $messages` property is
  removed; the bundled `basic` theme's `_sidebar-right.php` is
  updated from `<?= $site->messages ?>` to
  `<?= $site->render('messages') ?>` to match. Themes that read
  `$site->messages` directly must migrate to `render('messages')`.
- **Profile save no longer rejects the install-default email.** The
  install CLI used to seed `admin@localhost`, which PHP's
  `FILTER_VALIDATE_EMAIL` rejects (no TLD). The profile editor's
  email sanitizer then refused to save any change against that
  default, with the misleading "Please fill in all required fields"
  message. Two changes: the install default is now
  `admin@example.com` (IANA-reserved, passes the validator), and the
  profile module distinguishes between empty fields and an
  invalid-format email (new `profile_email_invalid` i18n key) so
  the message points at the actual problem. Existing installs with
  `admin@localhost` need to set a valid email once via the profile
  form to clear the validation; the new error message tells them
  exactly that.

## 2.0.1 (2026-05-17) — Dependency refresh + doc polish

No changes to Scriptor's own source. Captures the current state of
master after five post-2.0.0 commits, so `git clone --branch v2.0.1`
pulls the up-to-date dependency pins instead of the original 2.0.0
lock. `scriptor.cms` and `demos.scriptor-cms.dev` are both running
this state.

### Changed

- `bigins/imanager` 2.0.1 → 2.0.2: auto-mkdir on first boot, nicer
  `ConnectionFactory` error.
- `bigins/imanager` 2.0.2 → 2.1.0: schema-setup ergonomics (`Field`
  factories + repository `ensure()` methods).
- `bigins/imanager` 2.1.0 → 2.2.0: honest `searchable` flag, FTS5
  indexing now respects the per-field flag.
- `bigins/imanager` 2.2.0 → 2.2.1: `fts:rebuild` + `optimize`
  auto-migrate, upgrade-path hotfix.
- README "What's new in 2.0" section renamed to "Highlights".
- README "library" example rewritten to match the real surface.
- `composer.json`: dropped "flat file" keyword, refreshed
  description.

---

## 2.0.0 (2026-05-16) — Ground-up rewrite on iManager 2.0

Scriptor 2.0 replaces the embedded 1.x `imanager/` library with the
external [iManager 2.0][imanager] package and rebuilds Frontend +
Editor + Upload pipeline on PSR-standards (PSR-3 logging, PSR-14 event
dispatch, PSR-16 caching). The legacy 1.x flat-file storage is gone.

### Added

- **SQLite storage** with JSON columns and FTS5 full-text search.
- **Composer-based** install: the third-party runtime deps are
  `bigins/imanager:^2.0` and `intervention/image`, plus Symfony
  Console for the CLI.
- **Domain-event listeners** (`Scriptor\Boot\Events\*`):
  - `ItemFileCleanupListener`: drops uploaded files (and thumbnails)
    from disk when an item is deleted, before the FK cascade clears
    the metadata rows.
  - `PageCacheInvalidationListener`: flushes the rendered-page cache
    on every Pages-category mutation.
- **FilePond** uploads with on-demand thumbnail generation through
  `intervention/image`. Endpoint: `/editor/api/upload` (POST/PATCH/DELETE).
- **Per-image titles** as a typed `files.title` column (iManager 2.0
  schema migration `0004`). Captions render through `Sanitizer::markdown`
  on the frontend so links and emphasis work in 2.0 the same way they
  did in 1.x.
- **Single-entry routing**: root `index.php` delegates `/<admin_path>/*`
  to `editor/index.php` at the PHP level, which works on Apache, Caddy,
  Nginx, and PHP's built-in server without per-server rewrite rules.
- **`bin/perf-smoke.php`** runs the canonical timing checkpoints
  against the live SQLite database.
- **`Scriptor\Boot\Editor\*`** rewrites of all admin modules (auth,
  pages, profile, settings, install) on the iManager 2.0 stack.
- **`Scriptor\Boot\Frontend\*`**: `Site`, `Page`, `PageRepository`,
  `Sanitizer`, `ImageUrlBuilder`. Public-site renderer that bundled
  themes consume through the standard `$site` surface.
- **Asset-URL helpers**: `Site::themeAssetUrl()`,
  `Site::editorAssetUrl()`, `Editor::assetUrl()`. Used by every
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
  `$_SERVER['HTTPS']`; they read `X-Forwarded-Proto` first. Fixes
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
  `public/themes/<name>/` (static assets: css, fonts, images, scripts).
  The `site/` wrapper directory is gone; `site/themes/` becomes
  `themes/`, `site/modules/` becomes `modules/`.
- **Editor static assets move to `public/editor-assets/`.** The editor
  theme's PHP files (`template.php`, `header.php`, `summary.php`) stay in
  `editor/theme/`; included only by `editor/index.php`, never web-served.
  `editor/lang/` stays in `editor/`.
- **User uploads move to `public/uploads/`** (was `data/uploads-2.0/`).
  Inside the webroot, served by the web server directly, no alias rules
  needed. The image rendering layer (`Frontend\ImageUrlBuilder`) is
  backward-compatible with both the old `data/uploads/` (1.x-migrated)
  and `data/uploads-2.0/` (2.0 pre-public-webroot) prefixes. Existing
  items in the live DB keep rendering without a path migration.
- **`public/.htaccess`** replaces the root `.htaccess`. The deny list
  collapses to dotfiles only, because the source/data directories no
  longer live inside the webroot. The `editor/`-specific rewrite is
  gone too; every request goes through `public/index.php`, which
  handles admin-path delegation in PHP.

See [`docs/refactor-public-webroot.md`](https://github.com/bigin/Scriptor/blob/22a6144/docs/refactor-public-webroot.md)
for the full migration plan including server-config templates for
Apache, Caddy, nginx and PHP's built-in server, and the
allow/deny test matrix. (Operational sections also archived in
[`bigin/scriptor-cms-ops/docs/archive/refactor-public-webroot.md`](https://github.com/bigin/scriptor-cms-ops/blob/main/docs/archive/refactor-public-webroot.md).)

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
  `editor/`-rewrite is gone; every request lands on `index.php`
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
  `imanager dump`. Ships with `admin / gT5nLazzyBob`; change
  before exposing.
- **Docker volume layout split**: the pre-refactor single
  `scriptor-app` volume mounted code AND state, which silently
  shadowed image updates; `git pull && up -d --build` didn't
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
  + `expose_php=Off`. The nginx version no longer leaks and
  `X-Powered-By` is gone.
- **Editor `IMSESSID` session cookie** ships with `Secure`
  (X-Forwarded-Proto-aware, so local HTTP dev still works),
  `HttpOnly`, `SameSite=Lax`.

### Removed

- `Scriptor/imanager/`: the entire embedded 1.x library (~850 KB).
- `data/datasets/buffers/`: flat-file storage, superseded by SQLite.
- `editor/core/`: legacy `Scriptor\Core\Scriptor`, `Module`, `Site`,
  `Pages`, `User`, `Editor`, `CSRF`, `Helper`. All replaced by
  `Scriptor\Boot\*` equivalents.
- `editor/modules/`: every legacy admin module file. Replacements live
  under `boot/Editor/Auth/`, `boot/Editor/Pages/`, `boot/Editor/Profile/`,
  `boot/Editor/Settings/`, `boot/Editor/Install/`.
- `imanager.php` (root file): old `imanager()`-bootstrap stub.
- The legacy `Scriptor::execHook()` system. Domain events on the
  iManager side replace it; if a 3rd-party module needs the old hook
  shape we can ship a Hook-Bridge listener provider in a follow-up.

### Migration from 1.x

`vendor/bin/imanager migrate:from-v1 --source data --target data/imanager.db`
performs a one-shot import. See [README.md](README.md#migrating-from-1x).
The command takes a `--dry-run` flag for previewing the import.

### Performance

Documented budgets and typical results on the bundled demo data:

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
