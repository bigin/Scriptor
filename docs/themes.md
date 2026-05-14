# Themes in Scriptor 2.0

A theme is a public-site renderer plugged into the Scriptor `Site` runtime.
This guide covers the file layout, the bootstrap protocol, the asset-URL
helpers, and the optional config + composer pieces — using the bundled
`basic` theme as the canonical reference.

## Split layout — PHP source vs. static assets

Every theme has two physical halves:

```
themes/<name>/                    ← PHP source. Outside the webroot.
                                    Loaded only via PHP `include` /
                                    `require` from public/index.php.
  _ext.php                        Theme bootstrap (see below)
  template.php                    Page-level outer layout
  default.php, blog.php, …        Per-template page bodies
  lib/<Theme>.php                 Theme class extending Scriptor\Boot\Frontend\Site
  lib/<Theme>Router.php           Theme's request-routing logic
  resources/                      Includable partials (`_tpls.php`, chunks/)
  vendor/                         Theme-internal Composer artifacts
  composer.json, composer.lock    Composer setup for the theme

public/themes/<name>/             ← Static assets. Inside the webroot.
                                    Served directly by the web server.
  css/, fonts/, images/, scripts/
```

The split is the whole point of the public-webroot architecture: the
PHP half is physically unreachable through HTTP (no matter what the
web server thinks), and the static half is served as-is without
walking through PHP-FPM. Helper URLs (see below) bridge the two.

## Selecting a theme

`data/settings/scriptor-config.php`:

```php
'theme_path' => 'basic/',
```

The trailing slash is conventional but not required — internal code
normalises with `trim($value, '/')`. Scriptor looks for two parallel
roots:

- `<install>/themes/<theme_path>/` — for `_ext.php` and `template.php`
- `<install>/public/themes/<theme_path>/` — for static assets

Both must exist for a theme to function.

## Bootstrap protocol — `_ext.php`

`themes/<name>/_ext.php` is the theme's entry point. It runs early in
the request, in the scope of `public/index.php`, with `$config`
already populated. Its job: instantiate a `Site` (or subclass) into a
`$site` variable, and optionally short-circuit the response (cache).

Minimal `_ext.php`:

```php
<?php declare(strict_types=1);

use Scriptor\Boot\App;
use Themes\MyTheme\MyTheme;

require_once __DIR__ . '/vendor/autoload.php';

/** @var array<string, mixed> $config */
// Scriptor root = themes/<name>/ → ../../ (two `dirname` calls).
$site = new MyTheme(App::container(), $config, dirname(__DIR__, 2));

$site->execute();
```

What `public/index.php` does with `$site` after the include:

- If `$site` is `null` (set explicitly by the theme — e.g. the
  bundled basic theme does this when serving from cache), `index.php`
  returns and does NOT include `template.php`.
- Otherwise, `template.php` is included with `$site` in scope.

`template.php` is the outer layout: `<html>`, `<head>`, `<body>`,
nav, footer. It typically chooses one body partial (`default.php`,
`blog.php`, `contact.php`, …) based on the active page.

## Asset URLs — helpers, not hardcoded paths

Templates must NEVER hardcode `/themes/<name>/...` or
`/site/themes/<name>/...` or anything else server-config-dependent.
Use the helpers on `$site`:

| Helper                                    | Returns                              | Use for                                |
|-------------------------------------------|--------------------------------------|----------------------------------------|
| `$site->themeAssetUrl('css/styles.css')`  | `/themes/<active-theme>/css/styles.css` | The theme's own static assets         |
| `$site->editorAssetUrl('scripts/prism.js')` | `/editor-assets/scripts/prism.js`   | Admin-side assets reused on frontend   |
| `$site->themeUrl`                         | `/themes/<active-theme>/`            | Property — the prefix for theme assets (use the helper above when you can) |

Inside the editor (admin-side templates), the equivalent is
`$editor->assetUrl('css/styles.css')` → `/editor-assets/css/styles.css`.

Example — frontend `<head>`:

```php
<link rel="stylesheet" href="<?= $site->themeAssetUrl('css/styles.css') ?>">
<link rel="stylesheet" href="<?= $site->editorAssetUrl('css/prism.css') ?>">
<link rel="icon"      href="/favicon.ico" type="image/x-icon">
```

The favicon doesn't go through a helper because it lives at the
webroot's `/favicon.ico` (where browsers implicitly fetch it).

## Theme config

Optional. If your theme accepts user-tunable values (analytics IDs,
social-media URLs, sidebar layouts), put them in
`data/settings/<theme>-theme-config.php` and load them from your
`Site` subclass:

```php
final class MyTheme extends Site
{
    /** @var array<string, mixed> */
    public array $themeConfig = [];

    protected function init(): void
    {
        $configFile = $this->scriptorRoot . '/data/settings/my-theme-config.php';
        $this->themeConfig = is_file($configFile) ? (require $configFile) : [];
    }
}
```

Theme configs live next to the main `scriptor-config.php` so users
back them up together.

## Composer setup

Each theme has its own `vendor/` autoloader so it can ship its own
dependencies without colliding with the host:

```json
{
    "autoload": {
        "psr-4": { "Themes\\MyTheme\\": "lib/" }
    }
}
```

Run `composer install` once inside `themes/<name>/` after creating or
adding deps. The resulting `vendor/` is included by `_ext.php` via
`require_once __DIR__ . '/vendor/autoload.php'` and is physically
unreachable through HTTP because it's outside `public/`.

If your theme has no third-party deps, you still need a tiny vendor/
with the Composer autoloader so `_ext.php` can find your `lib/`
classes. The bundled basic theme is an example.

## Subscribers — tap into domain events

Themes can install PSR-14 listeners in `_ext.php`:

```php
use Imanager\Domain\Event\ItemSaved;
use Themes\MyTheme\Subscriber\Connector;

$dispatcher = App::container()->get(\Psr\EventDispatcher\ListenerProviderInterface::class);
// (Adapter pattern — see basic/lib/subscriber/Connector.php for a
// concrete example using SubscriberListenerProvider::add().)
```

Use this for:
- per-theme cache invalidation beyond the framework's defaults,
- mailing-list connectors triggered by item saves,
- analytics ping on page render.

## 404 page

`Site::throw404()` looks for `themes/<name>/404.php` and includes it
inside the standard `template.php` chrome. Override the lookup name
via `$config['404page']` if you need a different filename.

## Cache

`Site::hitCache()` returns a cached HTML body when one exists for the
current request, or `null` when one doesn't. Themes opt into the page
cache by calling `hitCache()` from `_ext.php` and short-circuiting on
hit:

```php
$cached = $site->hitCache();
if ($cached !== null) {
    echo $cached;
    $site = null;        // signal index.php not to render template.php
    return;
}
```

The bundled `PageCacheInvalidationListener` flushes the cache on every
Pages-category mutation, so cached output stays current without manual
invalidation.

## Testing your theme

```bash
# Apache (throwaway container, mount this repo read-only):
docker run --rm -d \
    -p 8091:80 \
    -v "$(pwd):/var/www/html:ro" \
    --entrypoint /bin/bash \
    php:8.3-apache \
    -c 'a2enmod rewrite && exec apache2-foreground'

# Then probe — your theme assets should 200, theme PHP source should 404:
curl -sI http://localhost:8091/themes/<your-theme>/css/styles.css   # 200
curl -sI http://localhost:8091/themes/<your-theme>/template.php     # 404

# nginx (the bundled demo image):
docker compose up -d --build

# PHP built-in:
php -S 127.0.0.1:8080 -t public public/index.php
```

If your theme PHP files are reachable through HTTP, you've put them
in the wrong half — they belong under `themes/<name>/`, not under
`public/themes/<name>/`.

## Reference

- Bundled theme source: `themes/basic/`
- Bundled theme assets: `public/themes/basic/`
- `Site` API: `boot/Frontend/Site.php`
- `Editor` API: `boot/Editor/Editor.php`
- Refactor plan / server-config templates:
  [`docs/refactor-public-webroot.md`](refactor-public-webroot.md)
