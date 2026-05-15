<?php

declare(strict_types=1);

/*
 * Theme bootstrap. Loaded by `index.php` when the active theme has an
 * `_ext.php` so the theme can install its own renderer and router.
 *
 * Phase 14b-2 wires the iManager-2.0-based BasicTheme + BasicRouter on
 * top of the container exposed via `Scriptor\Boot\App`.
 */

use Scriptor\Boot\App;
use Themes\Basic\BasicRouter;
use Themes\Basic\BasicTheme;

require_once __DIR__ . '/vendor/autoload.php';

/** @var array<string, mixed> $config */
// Scriptor root = themes/basic/ → ../../ (two `dirname` calls).
$site = new BasicTheme(App::container(), $config, dirname(__DIR__, 2));

// SuperCache: short-circuit the request when a cached body is available.
$cached = $site->hitCache();
if ($cached !== null) {
    // User actions still run so a fresh subscribe/contact submission is
    // processed even when the page itself comes from cache.
    $router = new BasicRouter($site);
    $router->actions();
    echo $cached;
    // Signal `index.php` that there is nothing left to render — without
    // this, the parent's `include $themeDir/template.php` line would run
    // template.php a second time on top of the cached body. The check at
    // the include site is `! $site instanceof Site`.
    $site = null;
    return;
}

$router = new BasicRouter($site);
$router->execute();
