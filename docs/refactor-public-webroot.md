# Refactor: `public/` Webroot

Status: **plan, not yet implemented**
Branch (target): `refactor/public-webroot`
Priority: 1 (security-driven — the current flat-root layout exposes
`data/imanager.db`, `.git/`, `vendor/`, `boot/`, `bin/` on any
Caddy/nginx that doesn't ship explicit deny rules)

This document is the single source of truth for the migration. Update
it as decisions land.

---

## 1. Goal & non-goals

### Goal

Move every web-reachable file under a single `public/` directory.
Everything else — source code, composer artifacts, the SQLite database,
configs, logs, theme PHP source, CLI scripts, git internals — lives
**outside** the webroot and is therefore physically unreachable through
HTTP regardless of server config.

After the refactor, the security posture is:

- **No server-side deny rules are required** for correctness. The
  `.htaccess` / nginx / Caddy block lists become hygienic
  defense-in-depth, not the load-bearing protection.
- Defaulting any web server's `root` to `<install>/public/` is
  sufficient.

### Non-goals

- No DB schema changes. iManager 2.0 schema v4 stays untouched.
- No iManager-library API changes. The refactor is Scriptor-only.
- No new theme-API or admin-API features. Templates change shape only
  where they have to (asset-URL helper).
- No multi-tenant or per-site path prefixing. One install = one
  public dir.

### Symlink policy

User preference: **prefer physical relocation over symlinks**. Use
symlinks only when a physical relocation would break a hard constraint
(e.g. shared hosting that can't change webroot). For the bundled basic
theme and the demo image we ship physical layout. Shared-hosting users
who can't move their webroot get a documented symlink recipe in
`docs/install-shared-hosting.md` (see §10).

---

## 2. Final layout

```
Scriptor/
├── public/                          ← THE WEBROOT (everything below is web-reachable)
│   ├── index.php                    ← thin front controller
│   ├── .htaccess                    ← Apache fallback (dotfile-deny + front-controller only)
│   ├── favicon.ico                  ← (optional, symlink from theme acceptable)
│   ├── themes/
│   │   └── basic/                   ← static-only half of the theme
│   │       ├── css/
│   │       ├── images/
│   │       ├── fonts/
│   │       └── scripts/
│   ├── editor-assets/               ← static-only half of the editor theme
│   │   ├── css/
│   │   ├── images/
│   │   ├── fonts/
│   │   └── scripts/                 ← incl. filepond/, remarkable/
│   └── uploads/                     ← user uploads, physically here (was data/uploads-2.0/)
│       └── <itemId>/<fieldId>/<file>
│
├── boot.php                         ← bootstrap include (PHP-included only)
├── boot/                            ← PSR-4 source, Scriptor\Boot\* — included only
│
├── themes/                          ← PHP source of themes (was site/themes/)
│   └── basic/
│       ├── template.php
│       ├── default.php, blog.php, contact.php, ...
│       ├── _ext.php
│       ├── lib/Basic.php, lib/BasicRouter.php, lib/subscriber/
│       ├── resources/_tpls.php, resources/chunks/
│       └── vendor/                  ← theme-internal composer artifacts (PHP-included only)
│
├── modules/                         ← user-installable site modules (was site/modules/)
│
├── editor/                          ← admin entry & PHP source (PHP-included only)
│   ├── index.php
│   ├── lang/en_US.php, de_DE.php
│   └── theme/
│       ├── template.php
│       ├── header.php
│       └── summary.php
│
├── data/                            ← runtime state, NEVER web-served
│   ├── imanager.db
│   ├── imanager.db-wal
│   ├── cache/sections/
│   ├── logs/
│   ├── settings/
│   │   ├── scriptor-config.php
│   │   ├── custom.scriptor-config.php
│   │   └── basic-theme-config.php
│   └── backups/configs/
│
├── bin/                             ← CLI scripts
├── docker/                          ← demo image
├── docs/
├── tests/                           ← (if/when we add them)
├── vendor/                          ← composer
├── composer.json, composer.lock
├── README.md, CHANGELOG.md, LICENSE
└── .git/, .gitignore, .editorconfig
```

The `site/` directory disappears. Its two children become top-level:

| 1.x / current 2.0       | After refactor       |
|-------------------------|----------------------|
| `site/themes/`          | `themes/` + `public/themes/` (static half) |
| `site/modules/`         | `modules/`           |

---

## 3. File-by-file mapping

### Move (physical relocation)

| Source                                        | Target                                    | Notes                                  |
|-----------------------------------------------|-------------------------------------------|----------------------------------------|
| `index.php`                                   | `public/index.php`                        | Rewrite contents (see §4.1)            |
| `data/uploads-2.0/`                           | `public/uploads/`                         | Bulk `git mv`; preserves history       |
| `site/themes/basic/css/`                      | `public/themes/basic/css/`                | static                                 |
| `site/themes/basic/images/`                   | `public/themes/basic/images/`             | static                                 |
| `site/themes/basic/fonts/`                    | `public/themes/basic/fonts/`              | static                                 |
| `site/themes/basic/scripts/`                  | `public/themes/basic/scripts/`            | static                                 |
| `site/themes/basic/template.php`              | `themes/basic/template.php`               | PHP                                    |
| `site/themes/basic/default.php` (+ others)    | `themes/basic/*.php`                      | PHP                                    |
| `site/themes/basic/_ext.php`                  | `themes/basic/_ext.php`                   | PHP                                    |
| `site/themes/basic/lib/`                      | `themes/basic/lib/`                       | PHP                                    |
| `site/themes/basic/resources/`                | `themes/basic/resources/`                 | PHP                                    |
| `site/themes/basic/vendor/`                   | `themes/basic/vendor/`                    | composer, PHP-included only            |
| `site/modules/`                               | `modules/`                                | unchanged content, just promoted       |
| `editor/theme/css/`                           | `public/editor-assets/css/`               | static                                 |
| `editor/theme/images/`                        | `public/editor-assets/images/`            | static                                 |
| `editor/theme/fonts/`                         | `public/editor-assets/fonts/`             | static                                 |
| `editor/theme/scripts/`                       | `public/editor-assets/scripts/`           | static (incl. filepond, remarkable)    |
| `editor/theme/favicon.ico`                    | `public/editor-assets/favicon.ico`        | static                                 |

### Stay in place

- `boot.php`, `boot/`, `editor/index.php`, `editor/lang/`,
  `editor/theme/template.php`, `editor/theme/header.php`,
  `editor/theme/summary.php`, `data/imanager.db`, `data/settings/*`,
  `data/cache/`, `data/logs/`, `data/backups/`, `bin/`, `docker/`,
  `docs/`, `vendor/`, `composer.json`, `composer.lock`, `README.md`,
  `CHANGELOG.md`, `LICENSE`, `.git/`, `.gitignore`, `.editorconfig`.

### Delete

- `site/` (after children moved out).
- The current root `.htaccess` (replaced by `public/.htaccess`).
- `imanager/` — wait, already deleted in the hygiene PR.

---

## 4. Code changes

### 4.1 `public/index.php` (new file)

```php
<?php

declare(strict_types=1);

use Scriptor\Boot\App;
use Scriptor\Boot\Frontend\Site;

require_once dirname(__DIR__) . '/boot.php';

/** @var array<string, mixed> $config */
$rootDir   = dirname(__DIR__);
$adminPath = trim((string) ($config['admin_path'] ?? 'editor/'), '/');
$path      = parse_url($_SERVER['REQUEST_URI'] ?? '/', \PHP_URL_PATH) ?? '/';

// Admin-path delegation in PHP — same trick as the current root
// index.php, just one level up. editor/index.php is OUTSIDE the
// webroot; only this file routes to it.
if ($path === '/' . $adminPath || str_starts_with($path, '/' . $adminPath . '/')) {
    require $rootDir . '/' . $adminPath . '/index.php';
    return;
}

$themeRoot = $rootDir . '/themes/' . trim((string) $config['theme_path'], '/');
$ext       = $themeRoot . '/_ext.php';

if (file_exists($ext)) {
    $site = null;
    include $ext;
    if (! $site instanceof Site) {
        return;
    }
} else {
    $site = new Site(App::container(), $config, $rootDir);
    $site->execute();
}

include $themeRoot . '/template.php';
```

### 4.2 `boot.php` — path anchor remains `__DIR__`

`boot.php` already uses `__DIR__` (= the Scriptor root) for everything.
**Nothing changes in boot.php** because it sits at the root, and the
new `public/index.php` calls it via `require dirname(__DIR__) . '/boot.php'`.

Verify after move:
- `IM_DATAPATH = __DIR__ . '/data'` → still `<root>/data` ✓
- `require __DIR__ . '/vendor/autoload.php'` → still `<root>/vendor` ✓
- `require __DIR__ . '/data/settings/scriptor-config.php'` ✓

### 4.3 `boot/ImanagerBootstrap.php`

Single-line change:

| Line | Before                                          | After                                |
|------|-------------------------------------------------|--------------------------------------|
| 42   | `$scriptorRoot . '/data/uploads-2.0'`           | `$scriptorRoot . '/public/uploads'`  |
| 43   | `'/data/uploads-2.0'`                           | `'/uploads'`                         |

### 4.4 `boot/Frontend/Site.php`

| Line | Before                                                         | After                                                            |
|------|----------------------------------------------------------------|------------------------------------------------------------------|
| 105  | `$this->siteUrl . '/site/themes/' . $config['theme_path']`     | `$this->siteUrl . '/themes/' . $config['theme_path']`            |
| 303  | `$this->scriptorRoot . '/site/themes/' . $config['theme_path']`| `$this->scriptorRoot . '/themes/' . $config['theme_path']`       |

Add new helper (see §5):

```php
public function themeAssetUrl(string $relative): string
{
    return $this->siteUrl . '/themes/' . trim($this->config['theme_path'], '/') . '/' . ltrim($relative, '/');
}
```

### 4.5 `boot/Frontend/ImageUrlBuilder.php`

| Line | Before                       | After                  |
|------|------------------------------|------------------------|
| 30   | `'data/uploads/'`            | unchanged (legacy 1.x) — used only by the legacy-promote path; documented as such |
| 31   | `'data/uploads-2.0/'`        | `'uploads/'`           |

The constructor stays parameterised — anyone overriding can keep their
own prefix. We only change the default.

**Concern:** Existing DB rows that store `path` as relative-to-storage
(`<itemId>/<fieldId>/<file>`) are unaffected — the prefix is added only
at URL/disk-resolve time. Verify by reading the FileStorage
`absolutePath()` impl and the rendered URL on the live install before
deploying.

### 4.6 `boot/Editor/Pages/PagesModule.php`

| Line | Before                  | After          |
|------|-------------------------|----------------|
| 497  | `'/data/uploads-2.0'`   | `'/uploads'`   |

(Used as the FilePond `<input data-base>` for client-side URL-prefixing
of pre-existing images on the page-edit form.)

### 4.7 `boot/Editor/Editor.php`

`Editor::langFor()` line 202 builds `<root>/<admin_path>/lang/<lang>.php`.
With admin_path = `editor/`, this resolves to `<root>/editor/lang/...`.
`editor/lang/` stays in place — **no change needed**. Verify that
admin_path is still the filesystem name of the admin folder, not just
a URL prefix. (Currently it is. We keep it that way; decoupling is a
separate refactor.)

### 4.8 `editor/index.php`

Two lines change:

```php
require_once __DIR__ . '/../boot.php';       // unchanged — still one dir up
// ...
$editor = new Editor(App::container(), $config, dirname(__DIR__));  // unchanged
// ...
include __DIR__ . '/theme/template.php';     // unchanged — template still next to it
```

**Nothing in editor/index.php has to change.** The fact that it's now
called via `require` from `public/index.php` (instead of being a
web-server-resolved entry) doesn't affect its own pathing — `__DIR__`
still resolves to `<root>/editor/`.

### 4.9 `editor/theme/template.php` (and `header.php`, `summary.php`)

Currently uses `$editor->siteUrl . '/theme/...'` to build URLs to its
own CSS/JS/images, because the editor theme lived under `editor/theme/`
relative to the admin path.

After the move, those assets live at `/editor-assets/` from the
webroot's perspective — completely decoupled from the admin URL.

Add a helper to `Editor.php`:

```php
public function assetUrl(string $relative): string
{
    // Editor static assets live at /editor-assets/ in the webroot,
    // independent of the admin_path URL prefix.
    return rtrim($this->baseUrl, '/') . '/editor-assets/' . ltrim($relative, '/');
}
```

(`baseUrl` = `detectSiteUrl()` result — host without the admin
suffix.) Then all `<?php echo $editor->siteUrl; ?>/theme/...` in
editor templates become `<?php echo $editor->assetUrl('...'); ?>`.

Affected lines (verified by grep): `editor/theme/template.php` L13–20,
22–23, 46–50; `editor/theme/summary.php` L6 (logo `src`).

### 4.10 `themes/basic/_ext.php`

```php
// Before
$site = new BasicTheme(App::container(), $config, dirname(__DIR__, 3));
require_once __DIR__ . '/vendor/autoload.php';

// After (one fewer dirname level — themes/basic/ → root is dirname(__DIR__, 2))
$site = new BasicTheme(App::container(), $config, dirname(__DIR__, 2));
require_once __DIR__ . '/vendor/autoload.php';  // unchanged
```

### 4.11 `themes/basic/lib/Basic.php`

| Line | Before                                                      | After                                          |
|------|-------------------------------------------------------------|------------------------------------------------|
| 47   | `$scriptorRoot . '/data/settings/basic-theme-config.php'`   | unchanged (config stays under data/settings/)  |
| 423  | `'data/uploads-2.0/' . ...`                                 | `'uploads/' . ...`                             |

### 4.12 `themes/basic/resources/chunks/_head.php`, `_sidebar-right.php`, `themes/basic/*.php`

URLs that pull editor-theme assets from the frontend (Prism CSS, the
favicon, jQuery) currently use
`$site->siteUrl . '/' . $site->config['admin_path'] . 'theme/...'`.
Switch to:

```php
<?php echo $site->editorAssetUrl('css/prism.css'); ?>
```

Add to `Site.php`:

```php
public function editorAssetUrl(string $relative): string
{
    return rtrim($this->siteUrl, '/') . '/editor-assets/' . ltrim($relative, '/');
}
```

Grep-able call-sites to convert (all in `themes/basic/`):
- `resources/chunks/_head.php` L7, L10
- `resources/chunks/_sidebar-right.php` L10, L12, L39, L40
- `default.php` L28
- `blog.php` L49
- `blog-post.php` L29
- `contact.php` L58

### 4.13 `data/settings/scriptor-config.php`

The `theme_path` comment can be updated to reflect that themes now
live under `<root>/themes/`, not `<root>/site/themes/`. The value
`'basic/'` itself doesn't change.

### 4.14 `data/settings/basic-theme-config.php`

Inspect for hardcoded `site/themes/...` references — convert if any.

---

## 5. Asset URL strategy

Three helper methods, one per "asset domain":

| Helper                            | Where defined              | Returns                                              | Used by                          |
|-----------------------------------|----------------------------|------------------------------------------------------|----------------------------------|
| `Site::themeAssetUrl($rel)`       | `boot/Frontend/Site.php`   | `<host>/themes/<theme>/<rel>`                        | Frontend theme templates         |
| `Site::editorAssetUrl($rel)`      | `boot/Frontend/Site.php`   | `<host>/editor-assets/<rel>`                         | Frontend templates needing admin-side CSS (prism, favicon, jquery) |
| `Editor::assetUrl($rel)`          | `boot/Editor/Editor.php`   | `<host>/editor-assets/<rel>`                         | Admin templates                  |

Theme authors no longer hardcode paths. Custom themes that ship in
post-2.0 forms MUST use these helpers (documented in the theme guide).

Bundled basic theme is the canonical example.

---

## 6. Server config templates

### 6.1 Apache (`public/.htaccess`)

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On

    AddDefaultCharset UTF-8
    Options -Indexes
    Options +SymLinksIfOwnerMatch

    # Forbid all dotfiles — .git is unreachable anyway (outside webroot),
    # but .htaccess itself is here and .env would be too if it existed.
    RewriteCond %{REQUEST_URI} (^|/)\.[^/]*($|/.*$)
    RewriteRule ^ - [F,L]

    # Front controller — everything that isn't a real file goes to index.php
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.*)$ index.php?id=$1 [L,QSA]
</IfModule>
```

Note: drops every directory deny rule. There's nothing dangerous to
deny — only static assets and the front controller exist below
`public/`. Dotfile rule kept as defense-in-depth.

### 6.2 nginx (`docker/nginx.conf`)

```nginx
server {
    listen 80;
    server_name _;
    root /var/www/scriptor/public;
    index index.php;

    client_max_body_size 16M;

    # Dotfiles
    location ~ /\.(?!well-known) {
        deny all;
        return 404;
    }

    # Front controller
    location / {
        try_files $uri /index.php?$query_string;
    }

    location ~ ^/index\.php(/|$) {
        fastcgi_pass scriptor:9000;
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT   $realpath_root;
        internal;
    }

    # No other .php is executable — nothing else under public/ is PHP anyway,
    # but defense-in-depth.
    location ~ \.php$ {
        return 404;
    }

    # Uploads — long-cache; they're immutable from the URL's perspective
    # (the path includes itemId/fieldId/<safe-filename>).
    location /uploads/ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }
}
```

The old "block /data/" rule disappears — `data/` is outside the webroot.
The old "explicitly allow /data/uploads-2.0/" rule disappears — uploads
live inside the webroot now.

### 6.3 Caddy (live install on ServBay)

ServBay's `php-rewrite-default` snippet is:

```caddy
(php-rewrite-default) {
    try_files {path} {path}/ /index.php?{query}
    php_fastcgi unix//Applications/ServBay/tmp/php-cgi-{args[0]}.sock
}
```

For Scriptor 2.0 we only change one line in the site stanza:

```caddy
https://scriptor.cms {
    encode zstd gzip
    import set-log scriptor.cms
    tls /Applications/ServBay/ssl/private/.../scriptor.cms.crt /...

    import canonical-path

    # BEFORE:
    # root * "/Applications/ServBay/www/Scriptor"
    # AFTER:
    root * "/Applications/ServBay/www/Scriptor/public"

    route {
        import php-rewrite-default 8.3
    }

    file_server
}
```

That's it. No custom blocks needed. `file_server` only sees
`public/index.php`, static assets, and `uploads/`. Everything else is
literally not in its tree.

ServBay UI rewrites the Caddyfile on site-edit, so this `root`-change
should be made via the ServBay UI's "Web Root" field, not by manual
Caddyfile edit. Document this in the install guide.

### 6.4 PHP built-in server

```bash
php -S 127.0.0.1:8080 -t public public/index.php
```

The `-t public` sets the document root; the trailing `public/index.php`
makes it the front controller for unmatched paths.

---

## 7. Test matrix

Run against all four servers after the refactor lands.

### 7.1 Servers

| Server          | How                                                                   |
|-----------------|-----------------------------------------------------------------------|
| Apache          | The throwaway container we already have: `php:8.3-apache`, mount = `public/` |
| nginx           | `docker compose up` from the demo image (already has front-controller config) |
| Caddy           | ServBay live install with the `root` change                           |
| PHP built-in    | `php -S 127.0.0.1:8092 -t public public/index.php`                    |

### 7.2 Allow / non-403 (every server)

```
GET /                                          → 200 (frontend)
GET /editor/                                   → 200/302 (admin login or session redirect)
GET /editor/auth/                              → 200
GET /editor/pages/                             → 302 (anon → login) or 200 (logged in)
GET /themes/basic/css/styles.css               → 200
GET /themes/basic/scripts/main.js              → 200
GET /themes/basic/fonts/work-sans-v18-latin-regular.woff2 → 200
GET /editor-assets/css/styles.css              → 200
GET /editor-assets/scripts/filepond/filepond.css → 200
GET /uploads/<existing>/<file>                 → 200 (after seeding test upload)
GET /this-page-does-not-exist                  → 200 or 404 (PHP-rendered 404 OK)
```

### 7.3 Deny / 403 or 404 (every server)

```
GET /boot.php                                  → 404 (file doesn't exist in webroot)
GET /boot/App.php                              → 404
GET /vendor/autoload.php                       → 404
GET /vendor/bigins/imanager/src/Imanager.php   → 404
GET /bin/perf-smoke.php                        → 404
GET /data/imanager.db                          → 404
GET /data/settings/scriptor-config.php         → 404
GET /themes/basic/template.php                 → 404 (PHP source outside webroot)
GET /themes/basic/lib/Basic.php                → 404
GET /modules/<any>                             → 404
GET /editor/index.php                          → 404 (not the webroot path)
GET /editor/lang/en_US.php                     → 404
GET /.htaccess                                 → 403 (Apache) / 404 (Caddy, nginx)
GET /.git/HEAD                                 → 404 (outside webroot)
GET /composer.json                             → 404
GET /docker/Dockerfile                         → 404
```

The key insight: most "deny" tests return **404**, not 403 — because
the files genuinely don't exist below the webroot. That's the goal.

### 7.4 Functional flow

After path tests, run a real flow:

1. `composer install --no-dev` from a fresh clone.
2. Login at `/editor/auth/` with seeded `admin/scriptor`.
3. Create a new page, save it.
4. Upload an image via FilePond on the page-edit form. Verify
   thumbnail rendered, file lands at `public/uploads/<itemId>/<fieldId>/<name>`.
5. Visit the page on the frontend; image displays.
6. Delete the page; uploaded files are cleaned up by the listener.

---

## 8. Deployment & rollback

### 8.1 Branch + PR

```
git checkout master
git pull
git checkout -b refactor/public-webroot
# commits in this order:
#   1. chore(layout): create public/ skeleton (empty dirs + .htaccess)
#   2. refactor(layout): git mv site/themes/basic to themes + public/themes
#   3. refactor(layout): git mv site/modules to modules
#   4. refactor(layout): git mv editor/theme static assets to public/editor-assets
#   5. refactor(layout): git mv data/uploads-2.0 to public/uploads
#   6. refactor(paths): boot/, editor/ path/URL updates
#   7. feat(theme-api): asset-URL helpers (themeAssetUrl, editorAssetUrl)
#   8. refactor(themes): basic theme uses new helpers
#   9. chore(docker): nginx root = /var/www/scriptor/public
#  10. chore(htaccess): public/.htaccess, drop root .htaccess
#  11. docs: README, deployment, install-shared-hosting
#  12. chore(changelog): BREAKING CHANGE entry
```

`git mv` preserves history. Reviewers can see the move as a rename.

### 8.2 Pre-merge validation

Run the **full test matrix** against:
1. The Apache test container.
2. A fresh `docker compose build` of the demo image.
3. The PHP built-in server.
4. **NOT** the live install — that's done post-merge.

### 8.3 Live deploy

1. **Snapshot DB:** `cp data/imanager.db data/imanager.db.pre-public-refactor`.
2. **Pull the merged master** on the live install.
3. **In ServBay UI:** edit the scriptor.cms site's Web Root to `…/Scriptor/public`. Reload Caddy.
4. Smoke: `curl https://scriptor.cms/`, `/editor/`, `/uploads/...`.
5. Probe the previously-exposed paths — they should all 404 now.

### 8.4 Rollback

If anything breaks:

1. **In ServBay UI:** revert the Web Root to `…/Scriptor`. Reload Caddy.
2. `git revert <merge-commit>` on master OR `git reset --hard <commit-before>` if there's no follow-up traffic yet.
3. Restore DB snapshot if any writes happened on the new layout that aren't compatible: `cp data/imanager.db.pre-public-refactor data/imanager.db`.

Note: DB writes that happen during the broken window are still
schema-compatible — the only thing that could go wrong is uploaded
files landing under `public/uploads/` in the new layout vs.
`data/uploads-2.0/` in the old. On rollback, move them back.

---

## 9. Decisions

### Resolved

1. **`favicon.ico` strategy.** Two physical copies: `public/favicon.ico`
   (root — answers the browsers' implicit `/favicon.ico` request without
   a 404 in the logs) AND `public/editor-assets/favicon.ico` (editor
   theme keeps its own asset conceptually). One byte-identical file in
   two places; sync on theme update is a non-issue (the editor favicon
   barely changes).

2. **Naming: `public/editor-assets/`.** Explicit, non-clashing with the
   admin_path URL space (which is PHP-delegated, not web-resolved).

3. **Theme-internal `vendor/` stays with the theme.**
   `themes/basic/vendor/` is included only by `themes/basic/_ext.php`
   via `require_once __DIR__ . '/vendor/autoload.php'`. It lives
   outside `public/` and is therefore not web-reachable. Drop-in theme
   installation stays autonomous (clone-into-themes-and-go), no
   composer.json patching of the host required. If a future theme
   needs heavy shared libs we can revisit.

4. **`theme_path` config value keeps its trailing slash** (`'basic/'`).
   Changing the value shape is a needless breaking change for users
   who override it in `custom.scriptor-config.php`. Internal code
   normalises with `trim($value, '/')`.

5. **`bin/` keeps its name.** Composer-create-project users expect that
   path; renaming to `tools/` is cosmetic.

6. **No migration tooling.** Live install (= the only one) gets the
   manual procedure from §8.3. CHANGELOG entry suffices for any
   future external user. Re-evaluate if external installs ever exist.

7. **Themes-API doc ships in the same PR.** New `docs/themes.md`
   explains the split layout (`themes/<name>/` PHP source +
   `public/themes/<name>/` static), the asset-URL helpers, and uses
   the bundled basic theme as the reference. Authoring this in the
   same PR forces the split-layout convention to be internally
   consistent before any external theme exists.

8. **Same-day live deploy after merge.** Single live install, ServBay
   UI Web-Root flip is reversible in seconds, full smoke + Apache +
   nginx + php-S coverage runs pre-merge anyway.

### Known limitation — accepted

- **ServBay's `php-rewrite-default` Caddy snippet has no dotfile
  block.** After the live deploy, `https://scriptor.cms/.htaccess`
  serves the file's contents byte-for-byte — Caddy's `file_server`
  has no special handling for dotfiles. The leak is informational
  only (the file just enumerates the Apache fallback rules; all
  truly-sensitive paths live outside `public/` and are physically
  unreachable). `.env` and `/.git/...` are also outside `public/`,
  so they're not affected. Decision: leave it. The Hetzner demo
  ships its own `docker/nginx.conf` which already blocks dotfiles
  via `location ~ /\.(?!well-known) { … }`. If ServBay-local hygiene
  becomes important later, add to the site's Caddy stanza:

  ```caddy
  @dotfiles {
      path_regexp dotfiles ^/(\.|.*/\.)[^/]+
      not path /.well-known/*
  }
  respond @dotfiles 404
  ```

---

## 10. Shared-hosting recipe (documentation snippet)

For hosts where the webroot is fixed (e.g. `public_html/`), the user
has two acceptable options. Both go into a new
`docs/install-shared-hosting.md`:

### Option A — symlink (preferred when supported)

```bash
# In SSH on the shared host:
cd ~  # or wherever the install sits next to public_html
ln -s /full/path/to/Scriptor/public public_html
```

### Option B — physical copy (when symlinks are forbidden)

```bash
cp -a /full/path/to/Scriptor/public/* /full/path/to/public_html/
# Edit public_html/index.php so the relative require still finds boot.php:
#    require_once '/full/path/to/Scriptor/boot.php';
```

(Document the second `require_once` path explicitly in the install
guide.)

---

## 11. Risks

| Risk                                                                          | Likelihood | Mitigation                                                                 |
|-------------------------------------------------------------------------------|------------|----------------------------------------------------------------------------|
| `ImageUrlBuilder` legacy-prefix logic mishandles already-migrated 1.x rows    | Low        | Probe `Site` rendering against the live install's existing pages before and after deploy |
| ServBay UI overwrites manual Caddyfile edits                                  | Medium     | Use the UI's "Web Root" field; document the click-path                     |
| Themes outside `bigins/basic` are broken by helper-API requirement            | Low (no external themes yet) | Bundled theme is the only one. Doc the helper API for future themes. |
| FilePond upload endpoint changes path                                         | Low        | Path is constructed server-side; only `PagesModule.php:497` needs update   |
| `editor/lang/` resolution depends on `admin_path` being a real directory      | Low        | Keep `admin_path` = `editor/` (current value). Decoupling is out of scope. |
| Hidden hardcoded `data/uploads-2.0` in some theme/module we don't see         | Low        | grep-pass over the whole tree before merge (already done for boot/site)    |
| User who runs `composer install` from inside `public/` instead of root        | Low        | The composer.json sits at the root; we don't change that.                  |

---

## 12. Effort estimate

| Phase                                | Effort  |
|--------------------------------------|---------|
| Directory moves (`git mv` × N)       | 1 h     |
| Code path updates (~10 files)        | 2 h     |
| Asset-URL helpers + template rewrite | 2 h     |
| Server-config templates              | 1 h     |
| Docker image rebuild + test          | 1 h     |
| Full test matrix run                 | 2 h     |
| Docs (README, deployment, themes)    | 2 h     |
| Live deploy                          | 0.5 h   |
| **Total**                            | **~12 h** |

One focused day. Maybe two with interruptions.

---

## 13. Sequence summary

```
1. Read this doc again, agree or amend.
2. Open refactor/public-webroot branch.
3. Move files (commits 1–5 above).
4. Update code (commits 6–8).
5. Server configs (commits 9–10).
6. Docs (commit 11).
7. Test matrix on Apache + nginx + php-S.
8. PR off master, review.
9. Merge.
10. Live deploy (ServBay UI Web Root + smoke).
11. Mark task #50 done.
12. Resume task #48 (Example-Theme).
```
